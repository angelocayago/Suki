<?php

return [
    // OFF by default. PostgreSQL/staging/production also require an explicit
    // administrator attestation of isolated DB tests, durable storage and limits.
    'attachments_enabled' => env('MESSAGE_ATTACHMENTS_ENABLED', false),
    'deployment_verified' => env('MESSAGE_ATTACHMENTS_DEPLOYMENT_VERIFIED', false),
];
