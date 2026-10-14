<?php

namespace App\Http\Controllers\Messaging;

use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\MessagingService;
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
        $this->authorizeConversation($request, $conversation, $messaging);
        $validated = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $conversation->load('participants');
        $messages = $conversation->messages()
            ->with('sender:id,name,first_name,last_name')
            ->when(
                isset($validated['after_id']),
                fn ($query) => $query->where('id', '>', $validated['after_id'])
            )
            ->orderBy('id')
            ->limit(100)
            ->get();

        return response()->json([
            'messages' => $messages->map(fn (Message $message) => $this->serializeMessage($conversation, $message)),
            'latest_id' => $messages->last()?->id,
            'conversation_status' => $conversation->status->value,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(
        Request $request,
        Conversation $conversation,
        MessagingService $messaging,
    ): JsonResponse|RedirectResponse {
        $portal = $this->authorizeConversation($request, $conversation, $messaging, 'send');
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'reply_to_id' => ['nullable', 'integer'],
        ]);

        $replyTo = isset($validated['reply_to_id'])
            ? $conversation->messages()->findOrFail($validated['reply_to_id'])
            : null;
        $message = $messaging->sendMessage(
            $conversation,
            $request->user(),
            $validated['body'],
            MessageType::Text,
            $replyTo
        );

        if ($request->expectsJson()) {
            $conversation->load('participants');

            return response()->json([
                'message' => $this->serializeMessage($conversation, $message),
            ], 201)->header('Cache-Control', 'private, no-store');
        }

        return redirect()
            ->route($portal['route_prefix'].'.show', $conversation->reference)
            ->with('success', 'Message sent.');
    }

    private function serializeMessage(Conversation $conversation, Message $message): array
    {
        $participant = $conversation->participants
            ->first(fn ($candidate) => (int) $candidate->user_id === (int) $message->sender_id);

        return [
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
