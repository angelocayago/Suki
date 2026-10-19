<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionReview extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['hidden_at' => 'datetime', 'edited' => 'boolean'];
    }
}
