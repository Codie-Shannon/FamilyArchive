<?php

return [
    'providers' => [
        'google_photos' => [
            'mode' => 'picker',
            'client_id' => env('GOOGLE_PHOTOS_CLIENT_ID'),
            'client_secret' => env('GOOGLE_PHOTOS_CLIENT_SECRET'),
            'redirect_uri' => env('GOOGLE_PHOTOS_REDIRECT_URI'),
        ],
        'apple_photos' => [
            'mode' => env('APPLE_PHOTOS_IMPORT_MODE', 'manual_export'),
            'native_connector_status' => env('APPLE_PHOTOS_NATIVE_STATUS', 'unvalidated'),
        ],
    ],
    'supported_media' => ['photo', 'video', 'audio', 'document'],
    'document_ocr' => false,
];
