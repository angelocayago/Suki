<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerRegistrationDetail extends Model
{
    protected $table = 'seller_registration_details';

    protected $fillable = [
        'user_id',
        'seller_type',
        'tin',
        'dti_registration_number',
        'sec_registration_number',
        'dti_certificate',
        'sec_certificate',
        'bir_form_2303',
        'pickup_region',
        'pickup_province',
        'pickup_city',
        'pickup_barangay',
        'pickup_street',
        'pickup_postal_code',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
