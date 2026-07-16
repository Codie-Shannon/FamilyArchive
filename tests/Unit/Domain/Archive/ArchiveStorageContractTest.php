<?php

use App\Domain\Archive\Enums\ArchiveStorageDisk;
use App\Domain\Archive\Services\ArchiveIdGenerator;
use App\Domain\Archive\Services\ArchiveStoragePath;
use App\Domain\Archive\Services\ArchiveStorageRegistry;
use App\Domain\Archive\Services\StoragePathValidator;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('maps every media type to the exact stable archive prefix', function (MediaType $type, string $expected): void {
    expect(app(ArchiveIdGenerator::class)->format($type, 1))->toBe($expected);
})->with([
    [MediaType::Photo, 'PH_000001'],
    [MediaType::Video, 'VD_000001'],
    [MediaType::Document, 'DC_000001'],
    [MediaType::Audio, 'AU_000001'],
    [MediaType::Other, 'OT_000001'],
]);

it('keeps six digits minimum and grows beyond six digits', function (): void {
    $generator = app(ArchiveIdGenerator::class);

    expect($generator->format(MediaType::Photo, 999999))->toBe('PH_999999')
        ->and($generator->format(MediaType::Photo, 1000000))->toBe('PH_1000000');
});

it('calculates exact bucket boundaries', function (): void {
    $paths = app(ArchiveStoragePath::class);

    expect($paths->bucketForArchiveId('PH_000001'))->toBe('000')
        ->and($paths->bucketForArchiveId('PH_000999'))->toBe('000')
        ->and($paths->bucketForArchiveId('PH_001000'))->toBe('001')
        ->and($paths->bucketForArchiveId('PH_030000'))->toBe('030');
});

it('generates separate original and derivative paths', function (): void {
    $paths = app(ArchiveStoragePath::class);
    $original = $paths->original(MediaType::Photo, 'PH_000001', 'JPG');
    $web = $paths->derivative(MediaFileVersionType::WebDisplay, MediaType::Photo, 'PH_000001', 'WEBP');

    expect($original)->toBe([
        'disk' => ArchiveStorageDisk::Originals,
        'path' => 'photos/000/PH_000001.jpg',
    ])->and($web)->toBe([
        'disk' => ArchiveStorageDisk::Derivatives,
        'path' => 'web-display/photos/000/PH_000001.webp',
    ])->and($web['path'])->not->toBe($original['path']);
});

it('generates every supported derivative path contract', function (MediaFileVersionType $type, MediaType $mediaType, string $archiveId, string $extension, string $expected): void {
    $result = app(ArchiveStoragePath::class)->derivative($type, $mediaType, $archiveId, $extension);

    expect($result['disk'])->toBe(ArchiveStorageDisk::Derivatives)
        ->and($result['path'])->toBe($expected);
})->with([
    [MediaFileVersionType::EditedFull, MediaType::Photo, 'PH_000001', 'jpg', 'edited-full/photos/000/PH_000001.jpg'],
    [MediaFileVersionType::WebDisplay, MediaType::Photo, 'PH_000001', 'webp', 'web-display/photos/000/PH_000001.webp'],
    [MediaFileVersionType::Thumbnail, MediaType::Photo, 'PH_000001', 'webp', 'thumbnails/photos/000/PH_000001.webp'],
    [MediaFileVersionType::VideoStream, MediaType::Video, 'VD_000001', 'mp4', 'video-stream/videos/000/VD_000001.mp4'],
    [MediaFileVersionType::VideoPreview, MediaType::Video, 'VD_000001', 'webp', 'video-preview/videos/000/VD_000001.webp'],
    [MediaFileVersionType::DocumentPreview, MediaType::Document, 'DC_000001', 'webp', 'document-preview/documents/000/DC_000001.webp'],
]);

it('plans quarantine and manifest paths without storage mutation', function (): void {
    $paths = app(ArchiveStoragePath::class);

    expect($paths->quarantine('incoming', 'UP-DEMO-000001', 'fictional file.JPG'))->toBe([
        'disk' => ArchiveStorageDisk::Quarantine,
        'path' => 'incoming/UP-DEMO-000001/fictional-file.jpg',
    ])->and($paths->manifest(MediaType::Photo, 'PH_000001'))->toBe([
        'disk' => ArchiveStorageDisk::Manifests,
        'path' => 'media/photos/000/PH_000001.json',
    ]);
});

it('rejects unsafe relative paths', function (string $path): void {
    expect(fn () => app(StoragePathValidator::class)->validateRelativePath($path))
        ->toThrow(InvalidArgumentException::class);
})->with([
    '../escape.jpg',
    '/absolute/photo.jpg',
    'C:/private/photo.jpg',
    'photos\\000\\PH_000001.jpg',
    'photos//PH_000001.jpg',
    'photos/000/PH_000001.bad-ext',
    "photos/000/PH_000001.jpg\0hidden",
]);

it('registers exactly four healthy private logical disks', function (): void {
    $contracts = app(ArchiveStorageRegistry::class)->contracts();

    expect(array_column($contracts, 'name'))->toBe([
        'archive_originals',
        'archive_derivatives',
        'archive_quarantine',
        'archive_manifests',
    ])->and(collect($contracts)->every(fn (array $contract): bool => $contract['healthy']))->toBeTrue();
});
