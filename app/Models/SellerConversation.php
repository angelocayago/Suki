<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerConversation extends Model
{
    protected $fillable = ['buyer_id', 'seller_id', 'last_message_at'];

    protected function casts(): array
    {
        return ['last_message_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function ($conversation) {
            if ($conversation->isDirty(['buyer_id', 'seller_id'])) {
                throw new \LogicException('Conversation participants cannot be reassigned.');
            }
        });
    }
}
