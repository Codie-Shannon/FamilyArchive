<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Archive\Services\ArchiveIdGenerator;
use App\Domain\Archive\Services\ArchiveStoragePath;
use App\Domain\Archive\Services\ArchiveStorageRegistry;
use App\Domain\Archive\Services\StoragePathValidator;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaType;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;

class ArchiveStorageController extends Controller
{
    public function __invoke(
        ArchiveIdGenerator $idGenerator,
        ArchiveStoragePath $paths,
        ArchiveStorageRegistry $registry,
        StoragePathValidator $validator,
    ): View {
        $idExamples = collect(MediaType::cases())->map(fn (MediaType $type): array => [
            'type' => $type->value,
            'prefix' => config("archive.prefixes.{$type->value}"),
            'example' => $idGenerator->format($type, 1),
            'sequence' => 'Fictional next value: 1',
        ])->all();

        $pathExamples = [
            ['label' => 'Original', ...$paths->original(MediaType::Photo, 'PH_000001', 'jpg')],
            ['label' => 'Edited full', ...$paths->derivative(MediaFileVersionType::EditedFull, MediaType::Photo, 'PH_000001', 'jpg')],
            ['label' => 'Web display', ...$paths->derivative(MediaFileVersionType::WebDisplay, MediaType::Photo, 'PH_000001', 'webp')],
            ['label' => 'Thumbnail', ...$paths->derivative(MediaFileVersionType::Thumbnail, MediaType::Photo, 'PH_000001', 'webp')],
            ['label' => 'Video stream', ...$paths->derivative(MediaFileVersionType::VideoStream, MediaType::Video, 'VD_000001', 'mp4')],
            ['label' => 'Document preview', ...$paths->derivative(MediaFileVersionType::DocumentPreview, MediaType::Document, 'DC_000001', 'webp')],
        ];

        $bucketExamples = collect(['PH_000001', 'PH_000999', 'PH_001000', 'PH_030000'])
            ->map(fn (string $archiveId): array => [
                'archive_id' => $archiveId,
                'bucket' => $paths->bucketForArchiveId($archiveId),
            ])->all();

        $plannedPaths = [
            ['label' => 'Incoming quarantine', ...$paths->quarantine('incoming', 'UP-DEMO-000001', 'fictional-photo.jpg')],
            ['label' => 'Possible duplicate', ...$paths->quarantine('duplicates', 'UP-DEMO-000001', 'fictional-photo.jpg')],
            ['label' => 'Failed intake', ...$paths->quarantine('failed', 'UP-DEMO-000001', 'fictional-photo.jpg')],
            ['label' => 'Future manifest', ...$paths->manifest(MediaType::Photo, 'PH_000001')],
        ];

        $rejections = collect([
            '../escape.jpg',
            '/absolute/photo.jpg',
            'C:/private/photo.jpg',
            'photos\\000\\PH_000001.jpg',
            'photos//PH_000001.jpg',
            'photos/000/PH_000001.bad-ext',
            "photos/000/PH_000001.jpg\0hidden",
        ])->map(function (string $candidate) use ($validator): array {
            try {
                $validator->validateRelativePath($candidate);

                return ['candidate' => $candidate, 'result' => 'Unexpectedly accepted'];
            } catch (InvalidArgumentException $exception) {
                return ['candidate' => str_replace("\0", '[NULL]', $candidate), 'result' => 'Rejected: '.$exception->getMessage()];
            }
        })->all();

        $route = app('router')->getRoutes()->getByName('admin.archive-storage');

        return view('admin.archive-storage', [
            'disks' => $registry->contracts(),
            'idExamples' => $idExamples,
            'pathExamples' => $pathExamples,
            'bucketExamples' => $bucketExamples,
            'plannedPaths' => $plannedPaths,
            'rejections' => $rejections,
            'routeMiddleware' => $route?->gatherMiddleware() ?? [],
        ]);
    }
}
