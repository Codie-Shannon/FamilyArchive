<?php

$archiveProvider = env('ARCHIVE_PROVIDER', 'local');
$wasabiKey = (string) env('WASABI_ACCESS_KEY_ID', '');
$wasabiSecret = (string) env('WASABI_SECRET_ACCESS_KEY', '');
$wasabiBase = [
    'driver' => 's3',
    'key' => $wasabiKey !== '' ? $wasabiKey : '__wasabi_not_configured__',
    'secret' => $wasabiSecret !== '' ? $wasabiSecret : '__wasabi_not_configured__',
    'region' => env('WASABI_REGION'),
    'bucket' => env('WASABI_BUCKET'),
    'endpoint' => env('WASABI_ENDPOINT'),
    'use_path_style_endpoint' => env('WASABI_USE_PATH_STYLE_ENDPOINT', true),
    'visibility' => 'private',
    'throw' => true,
    'report' => true,
    'stream_reads' => true,
];

$archiveDisk = static function (string $localRoot, string $wasabiRoot) use ($archiveProvider, $wasabiBase): array {
    if ($archiveProvider === 'wasabi') {
        return [...$wasabiBase, 'root' => $wasabiRoot];
    }

    return [
        'driver' => 'local',
        'root' => $localRoot,
        'visibility' => 'private',
        'serve' => false,
        'throw' => true,
        'report' => true,
    ];
};

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

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'archive_originals' => $archiveDisk(storage_path('app/archive/originals'), 'archive/originals'),

        'archive_derivatives' => $archiveDisk(storage_path('app/archive/derivatives'), 'archive/derivatives'),

        'archive_quarantine' => $archiveDisk(storage_path('app/archive/quarantine'), 'archive/quarantine'),

        'archive_manifests' => $archiveDisk(storage_path('app/archive/manifests'), 'archive/manifests'),

        'archive_local_originals' => [
            'driver' => 'local',
            'root' => storage_path('app/archive/originals'),
            'visibility' => 'private',
            'serve' => false,
            'throw' => true,
            'report' => true,
        ],

        'archive_local_derivatives' => [
            'driver' => 'local',
            'root' => storage_path('app/archive/derivatives'),
            'visibility' => 'private',
            'serve' => false,
            'throw' => true,
            'report' => true,
        ],

        'archive_local_quarantine' => [
            'driver' => 'local',
            'root' => storage_path('app/archive/quarantine'),
            'visibility' => 'private',
            'serve' => false,
            'throw' => true,
            'report' => true,
        ],

        'archive_local_manifests' => [
            'driver' => 'local',
            'root' => storage_path('app/archive/manifests'),
            'visibility' => 'private',
            'serve' => false,
            'throw' => true,
            'report' => true,
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
