<?php

namespace App\Http\Controllers\Messaging;

use App\Models\Conversation;
use App\Services\MessagingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ConversationReadController extends MessagingController
{
    public function store(
        Request $request,
        Conversation $conversation,
        MessagingService $messaging,
    ): Response {
        $this->authorizeConversation($request, $conversation, $messaging, 'markRead');
        $validated = $request->validate([
            'message_id' => ['nullable', 'integer'],
        ]);
        $message = isset($validated['message_id'])
            ? $conversation->messages()->findOrFail($validated['message_id'])
            : null;

        $messaging->markRead($conversation, $request->user(), $message);

        return response()->noContent();
    }
}
