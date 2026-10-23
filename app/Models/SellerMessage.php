<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SellerMessage extends Model
{
    protected $fillable = ['conversation_id', 'sender_role', 'sender_user_id', 'submission_key', 'body'];

    public function attachments(): HasMany
    {
        return $this->hasMany(SellerMessageAttachment::class, 'message_id')->orderBy('position');
    }

    protected function casts(): array
    {
        return ['read_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function ($message) {
            $conversation = SellerConversation::findOrFail($message->conversation_id);
            $author = $message->sender_role === 'buyer' ? $conversation->buyer_id
                : ($message->sender_role === 'seller' ? Seller::whereKey($conversation->seller_id)->value('user_id') : null);
            if (! $author || (int) $author !== (int) $message->sender_user_id) {
                throw new \LogicException('Message author must be a conversation participant.');
            }
        });
        static::updating(function ($message) {
            if ($message->isDirty(['conversation_id', 'sender_role', 'sender_user_id', 'submission_key', 'body', 'created_at'])) {
                throw new \LogicException('Saved message authorship and content are immutable.');
            }
        });
    }
}
