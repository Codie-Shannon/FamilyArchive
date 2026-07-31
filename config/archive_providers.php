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
            'use_path_style_endpoint' => env('WASABI_USE_PATH_STYLE_ENDPOINT', true),
            'visibility' => 'private',
            'prefixes' => [
                'archive_originals' => 'archive/originals',
                'archive_derivatives' => 'archive/derivatives',
                'archive_quarantine' => 'archive/quarantine',
                'archive_manifests' => 'archive/manifests',
                'health' => 'archive/health',
            ],
        ],
    ],
];
