<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Application extends Model
{
    use HasFactory;


    protected $fillable = [

        'user_id',

        'application_type',

        'status',

        'reviewed_by',

        'reviewed_at',

        'rejection_reason',

    ];



    protected $casts = [

        'reviewed_at' => 'datetime',

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
    | Reviewer (Super Admin)
    |--------------------------------------------------------------------------
    */

    public function reviewer()
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by'
        );
    }

}