<?php

namespace App\Http\Controllers\Messaging;

use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\MessagingService;
use App\Services\MessageAttachmentService;
use App\Support\SellerInbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MessageController extends MessagingController
{
    public function index(
        Request $request,
        Conversation $conversation,
        MessagingService $messaging,
    ): JsonResponse {
        $portal=$this->authorizeConversation($request, $conversation, $messaging);
        $validated = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0','prohibits:before_id'],
            'before_id' => ['nullable', 'integer', 'min:1','prohibits:after_id'],
        ]);

        $conversation->load('participants');
        $attachments=app(MessageAttachmentService::class)->available();
        $older=isset($validated['before_id']);
        $query = $conversation->messages()
            ->with('sender:id,name,first_name,last_name')
            ->when($attachments,fn ($q) => $q->with('attachments'))
            ->when(
                isset($validated['after_id']),
                fn ($query) => $query->where('id', '>', $validated['after_id'])
            )
            ->when($older,fn ($q) => $q->where('id','<',$validated['before_id']))
            ->reorder('messages.id',$older ? 'desc' : 'asc');
        $messages=$query->limit($older ? 50 : 100)->get();
        if ($older) $messages=$messages->reverse()->values();

        return response()->json([
            'messages' => $messages->map(fn (Message $message) => $this->serializeMessage($conversation, $message, $portal['name'] === 'seller', $attachments ? $portal['route_prefix'] : null)),
            'latest_id' => $messages->last()?->id,
            'conversation_status' => $conversation->status->value,
            ...($older ? ['has_older'=>$messages->isNotEmpty() && $conversation->messages()->where('id','<',$messages->first()->id)->exists()] : []),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(
        Request $request,
        Conversation $conversation,
        MessagingService $messaging,
    ): JsonResponse|RedirectResponse {
        $portal = $this->authorizeConversation($request, $conversation, $messaging, 'send');
        $hasImages=$request->has('images');
        if ($hasImages) {
            abort_unless(in_array($portal['name'],['buyer','seller'],true),403);
            app(MessageAttachmentService::class)->assertUploadsAvailable();
        }
        $validated = $request->validate([
            'body' => [$hasImages ? 'nullable' : 'required', 'string', 'max:10000'],
            'reply_to_id' => ['nullable', 'integer'],
            ...($hasImages ? ['images'=>['required','array','min:1','max:5']] : []),
        ]);

        $replyTo = isset($validated['reply_to_id'])
            ? $conversation->messages()->findOrFail($validated['reply_to_id'])
            : null;
        $message = $messaging->sendMessage(
            $conversation,
            $request->user(),
            $validated['body'] ?? '',
            MessageType::Text,
            $replyTo,
            $validated['images'] ?? [],
        );

        if ($request->expectsJson()) {
            $conversation->load('participants');

            return response()->json([
                'message' => $this->serializeMessage($conversation, $message, $portal['name'] === 'seller', app(MessageAttachmentService::class)->available() ? $portal['route_prefix'] : null),
            ], 201)->header('Cache-Control', 'private, no-store');
        }

        return redirect()
            ->route($portal['route_prefix'].'.show', $conversation->reference)
            ->with('success', 'Message sent.');
    }

    private function serializeMessage(Conversation $conversation, Message $message, bool $seller = false, ?string $attachmentPrefix = null): array
    {
        $participant = $conversation->participants
            ->first(fn ($candidate) => (int) $candidate->user_id === (int) $message->sender_id);

        return [
            ...($attachmentPrefix ? ['attachments'=>$message->loadMissing('attachments')->attachments->map(fn ($file) => app(MessageAttachmentService::class)->metadata($file,$conversation,$attachmentPrefix))->values(),
                'preview'=>MessageAttachmentService::preview($message)] : []),
            ...($seller ? ['display'=>app(SellerInbox::class)->messageDisplay($message)] : []),
            'id' => $message->id,
            'type' => $message->type->value,
            'body' => $message->body,
            'reply_to_id' => $message->reply_to_id,
            'sent_at' => $message->sent_at?->toIso8601String(),
            'sender' => [
                'id' => $message->sender_id,
                'name' => $message->sender?->full_name ?: $message->sender?->name ?: 'Former user',
                'role_context' => $participant?->role_context ?? 'SYSTEM',
            ],
        ];
    }
}
