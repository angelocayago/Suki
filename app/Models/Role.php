<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    /**
     * Fields that can be mass assigned.
     */
    protected $fillable = [
        'name',
    ];

    /**
     * Users assigned to this role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}