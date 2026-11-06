<?php

namespace App\Http\Controllers\Messaging;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Services\MessagingService;
use App\Services\MessageAttachmentService;
use App\Support\SellerInbox;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class ConversationController extends MessagingController
{
    public function index(Request $request, MessagingService $messaging): View
    {
        $portal = $this->portal($request);
        $user = $request->user();
        $attachmentsAvailable=app(MessageAttachmentService::class)->available();

        if ($portal['name'] === 'seller') {
            return view('seller.messages.inbox', app(SellerInbox::class)->listing($request) + ['portal'=>$portal,'conversation'=>null,'attachmentsAvailable'=>$attachmentsAvailable]);
        }

        $memberships = ConversationParticipant::query()
            ->select('conversation_participants.*')
            ->where('user_id', $user->id)
            ->whereIn('role_context', $portal['role_contexts'])
            ->whereNull('left_at')
            ->whereHas('conversation')
            ->with([
                'conversation.latestMessage'=>fn ($q) => $q->when($attachmentsAvailable,fn ($q) => $q->withCount('attachments'))->with('sender:id,name,first_name,last_name'),
                'conversation.participants.user:id,name,first_name,last_name',
            ])
            ->addSelect([
                'unread_count' => Message::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('messages.conversation_id', 'conversation_participants.conversation_id')
                    ->whereRaw('messages.id > COALESCE(conversation_participants.last_read_message_id, 0)')
                    ->where(function ($query) use ($user) {
                        $query->whereNull('messages.sender_id')
                            ->orWhere('messages.sender_id', '!=', $user->id);
                    }),
            ])
            ->orderByDesc(
                Conversation::query()
                    ->selectRaw('COALESCE(last_message_at, created_at)')
                    ->whereColumn('conversations.id', 'conversation_participants.conversation_id')
                    ->limit(1)
            )
            ->paginate(15)
            ->withQueryString();

        $this->removeStaleMemberships($memberships, $user, $portal['role_contexts'], $messaging);

        return view('messages.index', compact('memberships', 'portal','attachmentsAvailable'));
    }

    public function show(
        Request $request,
        Conversation $conversation,
        MessagingService $messaging,
    ): View {
        $portal = $this->authorizeConversation($request, $conversation, $messaging);
        $files=app(MessageAttachmentService::class);
        $attachmentsAvailable=$files->available();
        $canAttach=in_array($portal['name'],['buyer','seller'],true) && $conversation->status->value==='ACTIVE' && $files->canUpload();
        $photoUnavailableReason=$canAttach ? null : $files->uploadUnavailableReason();
        $conversation->load([
            'participants.user:id,name,first_name,last_name',
            'participants.seller:id,name',
            'participants.rider:id,user_id',
            'participants.logisticsProvider:id,name',
        ]);

        $messages = $conversation->messages()
            ->with('sender:id,name,first_name,last_name')
            ->when($attachmentsAvailable,fn ($q) => $q->with('attachments'))
            ->reorder('messages.id', 'desc')
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        if ($portal['name'] === 'seller') {
            $hasOlder=$messages->isNotEmpty() && $conversation->messages()->where('id','<',$messages->first()->id)->exists();
            return view('seller.messages.inbox', app(SellerInbox::class)->listing($request) + compact('conversation','messages','portal','hasOlder','attachmentsAvailable','canAttach','photoUnavailableReason'));
        }

        return view('messages.show', compact('conversation', 'messages', 'portal','attachmentsAvailable','canAttach','photoUnavailableReason'));
    }

    private function removeStaleMemberships(
        LengthAwarePaginator $memberships,
        mixed $user,
        array $roleContexts,
        MessagingService $messaging,
    ): void {
        $memberships->setCollection(
            $memberships->getCollection()
                ->filter(fn (ConversationParticipant $membership) => $messaging->canAccessAs($membership->conversation, $user, $roleContexts)
                )
                ->values()
        );
    }
}
