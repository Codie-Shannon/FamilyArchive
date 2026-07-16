<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Intake\Enums\DuplicateStatus;
use App\Domain\Intake\Enums\IncomingProcessingStatus;
use App\Domain\Intake\Enums\IncomingReviewStatus;
use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Media\Enums\DateConfidence;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Enums\SensitivityStatus;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ArchiveSchemaController extends Controller
{
    public function __invoke(Request $request): View
    {
        $allowedViews = ['overview', 'media-item', 'incoming-upload', 'file-versions', 'contracts', 'access-boundary'];
        $activeView = $request->string('view')->toString();

        if (! in_array($activeView, $allowedViews, true)) {
            $activeView = 'overview';
        }

        $tableDefinitions = [
            'media_items' => [
                'label' => 'Media items',
                'model' => MediaItem::class,
                'required_columns' => [
                    'archive_id',
                    'media_type',
                    'date_confidence',
                    'visibility',
                    'review_status',
                    'sensitivity_status',
                    'created_by',
                ],
            ],
            'incoming_uploads' => [
                'label' => 'Incoming uploads',
                'model' => IncomingUpload::class,
                'required_columns' => [
                    'upload_id',
                    'uploader_id',
                    'media_item_id',
                    'sha256',
                    'processing_status',
                    'review_status',
                    'duplicate_status',
                ],
            ],
            'media_file_versions' => [
                'label' => 'File versions',
                'model' => MediaFileVersion::class,
                'required_columns' => [
                    'media_item_id',
                    'parent_version_id',
                    'version_type',
                    'storage_disk',
                    'storage_path',
                    'sha256',
                    'generation_status',
                ],
            ],
        ];

        $tables = [];
        foreach ($tableDefinitions as $table => $definition) {
            $exists = Schema::hasTable($table);
            $hasColumns = $exists && Schema::hasColumns($table, $definition['required_columns']);

            /** @var class-string<MediaItem|IncomingUpload|MediaFileVersion> $model */
            $model = $definition['model'];

            $tables[] = [
                'name' => $table,
                'label' => $definition['label'],
                'healthy' => $exists && $hasColumns,
                'count' => $exists ? $model::query()->count() : 0,
            ];
        }

        $schemaReady = collect($tables)->every(
            fn (array $table): bool => $table['healthy'],
        );

        $migrationNames = [
            '2026_07_16_235000_create_media_items_table',
            '2026_07_16_235100_create_incoming_uploads_table',
            '2026_07_16_235200_create_media_file_versions_table',
        ];

        $appliedMigrationCount = Schema::hasTable('migrations')
            ? DB::table('migrations')->whereIn('migration', $migrationNames)->count()
            : 0;

        $mediaItem = $schemaReady
            ? MediaItem::query()
                ->with(['fileVersions.parentVersion', 'incomingUploads'])
                ->where('archive_id', 'FA-DEMO-00000001')
                ->first()
            : null;

        $incomingUpload = $schemaReady
            ? IncomingUpload::query()
                ->with('mediaItem')
                ->where('upload_id', 'UP-DEMO-00000001')
                ->first()
            : null;

        $versions = $mediaItem?->fileVersions
            ->sortBy(fn (MediaFileVersion $version): int => match ($version->version_type) {
                MediaFileVersionType::Original => 1,
                MediaFileVersionType::EditedFull => 2,
                MediaFileVersionType::WebDisplay => 3,
                MediaFileVersionType::Thumbnail => 4,
                default => 5,
            })
            ->values() ?? collect();

        $enumContracts = [
            'Media type' => array_column(MediaType::cases(), 'value'),
            'Media review' => array_column(MediaReviewStatus::cases(), 'value'),
            'Visibility' => array_column(MediaVisibility::cases(), 'value'),
            'Sensitivity' => array_column(SensitivityStatus::cases(), 'value'),
            'Date confidence' => array_column(DateConfidence::cases(), 'value'),
            'Incoming processing' => array_column(IncomingProcessingStatus::cases(), 'value'),
            'Incoming review' => array_column(IncomingReviewStatus::cases(), 'value'),
            'Duplicate status' => array_column(DuplicateStatus::cases(), 'value'),
            'File version type' => array_column(MediaFileVersionType::cases(), 'value'),
            'Generation status' => array_column(GenerationStatus::cases(), 'value'),
        ];

        $archiveSchemaRoute = app('router')->getRoutes()->getByName('admin.archive-schema');
        $routeMiddleware = $archiveSchemaRoute?->gatherMiddleware() ?? [];

        $accessBoundary = [
            [
                'actor' => 'Verified Owner',
                'result' => 'HTTP 200',
                'reason' => 'Passes auth, verified and owner middleware.',
                'allowed' => true,
            ],
            [
                'actor' => 'Authenticated non-owner',
                'result' => 'HTTP 403',
                'reason' => 'Rejected by the owner middleware.',
                'allowed' => false,
            ],
            [
                'actor' => 'Guest',
                'result' => 'Redirect to login',
                'reason' => 'Rejected by authentication before schema access.',
                'allowed' => false,
            ],
        ];

        return view('admin.archive-schema', [
            'activeView' => $activeView,
            'tables' => $tables,
            'appliedMigrationCount' => $appliedMigrationCount,
            'expectedMigrationCount' => count($migrationNames),
            'schemaReady' => $schemaReady,
            'mediaItem' => $mediaItem,
            'incomingUpload' => $incomingUpload,
            'versions' => $versions,
            'enumContracts' => $enumContracts,
            'routeMiddleware' => $routeMiddleware,
            'accessBoundary' => $accessBoundary,
        ]);
    }
}
