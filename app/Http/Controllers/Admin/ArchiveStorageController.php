<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Archive\Services\ArchiveIdGenerator;
use App\Domain\Archive\Services\ArchiveStoragePath;
use App\Domain\Archive\Services\ArchiveStorageRegistry;
use App\Domain\Archive\Services\StoragePathValidator;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Storage\Models\StorageProviderVerification;
use App\Domain\Storage\Services\ArchiveProviderReadiness;
use App\Domain\Storage\Services\WasabiArchiveMigrator;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class ArchiveStorageController extends Controller
{
    public function __invoke(
        ArchiveIdGenerator $idGenerator,
        ArchiveStoragePath $paths,
        ArchiveStorageRegistry $registry,
        StoragePathValidator $validator,
        ArchiveProviderReadiness $providerReadiness,
        WasabiArchiveMigrator $wasabiMigrator,
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
        $provider = $providerReadiness->report();
        $latestProviderVerification = Schema::hasTable('storage_provider_verifications')
            ? StorageProviderVerification::query()->latest('checked_at')->first()
            : null;

        return view('admin.archive-storage', [
            'disks' => $registry->contracts(),
            'idExamples' => $idExamples,
            'pathExamples' => $pathExamples,
            'bucketExamples' => $bucketExamples,
            'plannedPaths' => $plannedPaths,
            'rejections' => $rejections,
            'routeMiddleware' => $route?->gatherMiddleware() ?? [],
            'provider' => $provider,
            'latestProviderVerification' => $latestProviderVerification,
            'providerBoundaries' => [
                ['label' => 'Originals', 'prefix' => 'archive/originals', 'rule' => 'Create + read; application deletion denied'],
                ['label' => 'Derivatives', 'prefix' => 'archive/derivatives', 'rule' => 'Create + read; exact-version cleanup'],
                ['label' => 'Quarantine', 'prefix' => 'archive/quarantine', 'rule' => 'Create + read; exact-version cleanup'],
                ['label' => 'Manifests', 'prefix' => 'archive/manifests', 'rule' => 'Create + read; application deletion denied'],
                ['label' => 'Health checks', 'prefix' => 'archive/health', 'rule' => 'Synthetic verification + exact-version cleanup'],
            ],
            'migrationPlan' => $wasabiMigrator->migrate(),
        ]);
    }
}
