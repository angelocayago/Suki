<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountVerification extends Model
{
    protected $fillable = ['user_id', 'channel', 'code_hash', 'attempts', 'max_attempts', 'expires_at', 'last_sent_at', 'verified_at'];

    protected function casts(): array
    {
        return ['attempts' => 'integer', 'max_attempts' => 'integer', 'expires_at' => 'datetime', 'last_sent_at' => 'datetime', 'verified_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
