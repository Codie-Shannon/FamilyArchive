<?php

return [
    'default' => env('ARCHIVE_PROVIDER', 'local'),
    'providers' => [
        'local' => [
            'driver' => 'local',
            'configured' => true,
        ],
        'wasabi' => [
            'driver' => 's3',
            'endpoint' => env('WASABI_ENDPOINT'),
            'region' => env('WASABI_REGION'),
            'bucket' => env('WASABI_BUCKET'),
            'key' => env('WASABI_ACCESS_KEY_ID'),
            'secret' => env('WASABI_SECRET_ACCESS_KEY'),
            'visibility' => 'private',
        ],
    ],
];
