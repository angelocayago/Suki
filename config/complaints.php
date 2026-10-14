<?php

return [
    'evidence_disk' => env('COMPLAINT_EVIDENCE_DISK', 'local'),
    'evidence_max_kilobytes' => 10240,
    'evidence_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
    'evidence_mime_types' => [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
    ],
];
