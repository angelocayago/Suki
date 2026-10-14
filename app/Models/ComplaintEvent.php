<?php

namespace App\Models;

use App\Enums\ComplaintStatus;
use App\Enums\ComplaintWaitingOn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintEvent extends Model
{
    protected $fillable = [
        'complaint_id',
        'actor_id',
        'action',
        'previous_status',
        'new_status',
        'previous_assignee_id',
        'new_assignee_id',
        'waiting_on',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'previous_status' => ComplaintStatus::class,
            'new_status' => ComplaintStatus::class,
            'waiting_on' => ComplaintWaitingOn::class,
            'metadata' => 'array',
        ];
    }

    public function complaint(): BelongsTo { return $this->belongsTo(Complaint::class); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
    public function previousAssignee(): BelongsTo { return $this->belongsTo(User::class, 'previous_assignee_id'); }
    public function newAssignee(): BelongsTo { return $this->belongsTo(User::class, 'new_assignee_id'); }
}
