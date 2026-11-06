<?php

$chatPhotoDriver=env('CHAT_PHOTOS_STORAGE_DRIVER','local');
$chatPhotoDisk=$chatPhotoDriver==='s3' ? [
    'driver'=>'chat_private_s3',
    'endpoint'=>env('CHAT_PHOTOS_S3_ENDPOINT'),
    'region'=>env('CHAT_PHOTOS_S3_REGION'),
    'bucket'=>env('CHAT_PHOTOS_S3_BUCKET'),
    'key'=>env('CHAT_PHOTOS_S3_ACCESS_KEY_ID'),
    'secret'=>env('CHAT_PHOTOS_S3_SECRET_ACCESS_KEY'),
    'private_bucket_verified'=>env('CHAT_PHOTOS_S3_PRIVATE_BUCKET_VERIFIED',false),
    'use_path_style_endpoint'=>true,'visibility'=>'private','serve'=>false,'throw'=>true,
] : [
    'driver'=>$chatPhotoDriver,
    'root'=>env('MESSAGE_ATTACHMENTS_ROOT',storage_path('app/private/message-attachments')),
    'visibility'=>'private','directory_visibility'=>'private','serve'=>false,'throw'=>true,
    'permissions'=>['file'=>['private'=>0600],'dir'=>['private'=>0700]],
];

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'message_attachments' => $chatPhotoDisk,

        'delivery_proofs' => [
            'driver' => 'local',
            'root' => storage_path('app/private/delivery-proofs'),
            'serve' => false,
            'throw' => true,
        ],

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'seller_documents' => [
            'driver' => 'local',
            'root' => storage_path('app/private/seller-documents'),
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
