<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerViolationEvidence extends Model
{
    protected $table = 'seller_violation_evidence';

    protected $fillable = [
        'seller_violation_id',
        'uploaded_by',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'checksum',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function violation(): BelongsTo
    {
        return $this->belongsTo(SellerViolation::class, 'seller_violation_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
