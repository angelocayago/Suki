<?php

namespace App\Policies;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\User;
use App\Services\MessagingService;

class ConversationPolicy
{
    public function __construct(private readonly MessagingService $messaging) {}

    public function view(User $user, Conversation $conversation): bool
    {
        return $this->messaging->canAccess($conversation, $user);
    }

    public function send(User $user, Conversation $conversation): bool
    {
        return $conversation->status === ConversationStatus::Active
            && $this->messaging->canAccess($conversation, $user);
    }

    public function markRead(User $user, Conversation $conversation): bool
    {
        return $this->messaging->canAccess($conversation, $user);
    }
}
