<?php

namespace App\Http\Controllers\Messaging;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Services\MessagingService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class ConversationController extends MessagingController
{
    public function index(Request $request, MessagingService $messaging): View
    {
        $portal = $this->portal($request);
        $user = $request->user();

        $memberships = ConversationParticipant::query()
            ->select('conversation_participants.*')
            ->where('user_id', $user->id)
            ->whereIn('role_context', $portal['role_contexts'])
            ->whereNull('left_at')
            ->whereHas('conversation')
            ->with([
                'conversation.latestMessage.sender:id,name,first_name,last_name',
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

        return view('messages.index', compact('memberships', 'portal'));
    }

    public function show(
        Request $request,
        Conversation $conversation,
        MessagingService $messaging,
    ): View {
        $portal = $this->authorizeConversation($request, $conversation, $messaging);
        $conversation->load([
            'participants.user:id,name,first_name,last_name',
            'participants.seller:id,name',
            'participants.rider:id,user_id',
            'participants.logisticsProvider:id,name',
        ]);

        $messages = $conversation->messages()
            ->with('sender:id,name,first_name,last_name')
            ->latest('id')
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        return view('messages.show', compact('conversation', 'messages', 'portal'));
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
