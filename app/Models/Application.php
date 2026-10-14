<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Application extends Model
{
    use HasFactory;


    protected $fillable = [

        'user_id',

        'application_type',

        'application_data',

        'documents',

        'status',

        'reviewed_by',

        'reviewed_at',

        'rejection_reason',

    ];



    protected $casts = [

        'reviewed_at' => 'datetime',

        'application_data' => 'array',

        'documents' => 'array',

    ];



    /*
    |--------------------------------------------------------------------------
    | Applicant
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class);
    }



    /*
    |--------------------------------------------------------------------------
    | Reviewer (Admin)
    |--------------------------------------------------------------------------
    */

    public function reviewer()
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by'
        );
    }

    public function sellerDocuments(): HasMany
    {
        return $this->hasMany(SellerDocument::class);
    }

}
