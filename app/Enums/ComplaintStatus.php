<?php

namespace App\Enums;

enum ComplaintStatus: string
{
    case Open = 'OPEN';
    case UnderReview = 'UNDER_REVIEW';
    case WaitingResponse = 'WAITING_RESPONSE';
    case Resolved = 'RESOLVED';
    case Dismissed = 'DISMISSED';

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Open => [self::UnderReview, self::Dismissed],
            self::UnderReview => [self::WaitingResponse, self::Resolved, self::Dismissed],
            self::WaitingResponse => [self::UnderReview, self::Resolved, self::Dismissed],
            self::Resolved, self::Dismissed => [],
        }, true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Resolved, self::Dismissed], true);
    }
}
