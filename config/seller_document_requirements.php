<?php

return [
    'requirements' => [
        'Individual' => [
            'government_id',
        ],
        'Sole Proprietorship' => [
            'government_id',
            'dti_certificate',
            'bir_form_2303',
        ],
        'Corporation' => [
            'government_id',
            'sec_certificate',
            'bir_form_2303',
        ],
    ],

    'labels' => [
        'government_id' => 'Government-issued ID',
        'dti_certificate' => 'DTI Certificate',
        'bir_form_2303' => 'BIR Form 2303',
        'sec_certificate' => 'SEC Certificate',
        'business_permit' => 'Business Permit (Optional)',
    ],
];
