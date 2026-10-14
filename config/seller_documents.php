<?php

return [
    'disk' => env('SELLER_DOCUMENTS_DISK', 'seller_documents'),
    'max_kilobytes' => 5120,
    'extensions' => ['jpg', 'jpeg', 'png', 'pdf'],
    'mime_types' => [
        'image/jpeg',
        'image/png',
        'application/pdf',
    ],
];
