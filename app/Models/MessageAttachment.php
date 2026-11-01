<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageAttachment extends Model
{
    protected $fillable = ['message_id','position','disk','path','original_name','mime_type','size','width','height'];
    protected $hidden = ['disk','path'];

    protected function casts(): array
    {
        return ['position'=>'integer','size'=>'integer','width'=>'integer','height'=>'integer'];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
