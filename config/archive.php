<?php

use App\Domain\Archive\Enums\ArchiveStorageDisk;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaType;

return [
    'bucket_size' => 1000,
    'prefixes' => [MediaType::Photo->value => 'PH', MediaType::Video->value => 'VD', MediaType::Document->value => 'DC', MediaType::Audio->value => 'AU', MediaType::Other->value => 'OT'],
    'media_segments' => [MediaType::Photo->value => 'photos', MediaType::Video->value => 'videos', MediaType::Document->value => 'documents', MediaType::Audio->value => 'audio', MediaType::Other->value => 'other'],
    'disks' => [
        ArchiveStorageDisk::Originals->value => ['purpose' => 'Approved original media', 'private' => true, 'public_url' => false],
        ArchiveStorageDisk::Derivatives->value => ['purpose' => 'Rebuildable edited and viewing files', 'private' => true, 'public_url' => false],
        ArchiveStorageDisk::Quarantine->value => ['purpose' => 'Incoming, failed and possible-duplicate files', 'private' => true, 'public_url' => false],
        ArchiveStorageDisk::Manifests->value => ['purpose' => 'Future integrity and recovery manifests', 'private' => true, 'public_url' => false],
    ],
    'version_segments' => [
        MediaFileVersionType::EditedFull->value => 'edited-full', MediaFileVersionType::WebDisplay->value => 'web-display', MediaFileVersionType::Thumbnail->value => 'thumbnails',
        MediaFileVersionType::VideoStream->value => 'video-stream', MediaFileVersionType::VideoPreview->value => 'video-preview', MediaFileVersionType::DocumentPreview->value => 'document-preview',
    ],
    'photo_intake' => [
        'max_bytes' => 104857600,
        'max_width' => 40000,
        'max_height' => 40000,
        'max_pixels' => 250000000,
        'mime_extensions' => [
            'image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'], 'image/webp' => ['webp'], 'image/tiff' => ['tif', 'tiff'],
        ],
    ],
];
