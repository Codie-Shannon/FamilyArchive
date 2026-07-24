<?php

namespace Database\Seeders;

use App\Domain\Media\Enums\DateConfidence;
use App\Domain\Media\Enums\DatePrecision;
use App\Domain\Media\Enums\DateReviewState;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Enums\SensitivityStatus;
use App\Domain\Media\Enums\StructuredDateConfidence;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Metadata\Actions\UpdatePhotoMetadata;
use App\Domain\Provenance\Actions\UpdatePhotoProvenance;
use App\Domain\Provenance\Enums\SourceCollectionType;
use App\Domain\Provenance\Models\ScanBatch;
use App\Domain\Provenance\Models\SourceCollection;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class Group12DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (MediaItem::query()->whereNotLike('archive_id', 'G12-DEMO-%')->exists()) {
            throw new RuntimeException('Group 12 demo seeding refuses to mix with non-demo archive records.');
        }

        $owner = User::query()->firstOrCreate(
            ['email' => 'group12-owner@example.test'],
            [
                'name' => 'Fictional Archive Owner',
                'password' => bcrypt('Group12Demo!2026'),
                'role' => 'owner',
                'email_verified_at' => now(),
            ]
        );

        $photo = MediaItem::query()->firstOrCreate(
            ['archive_id' => 'G12-DEMO-001'],
            [
                'media_type' => MediaType::Photo,
                'title' => 'Fictional Wairarapa Railway Picnic',
                'description' => 'A fully synthetic New Zealand archive scene.',
                'story' => 'A fictional family picnic near a fictional rural railway stop.',
                'canonical_date' => null,
                'date_precision' => DatePrecision::Unknown,
                'date_year' => null,
                'estimated_decade' => null,
                'date_confidence' => DateConfidence::Unknown,
                'structured_date_confidence' => StructuredDateConfidence::Unknown,
                'date_review_state' => DateReviewState::Suggestion,
                'date_source_note' => null,
                'date_reason' => null,
                'visibility' => MediaVisibility::PrivateArchive,
                'review_status' => MediaReviewStatus::Approved,
                'sensitivity_status' => SensitivityStatus::NotFlagged,
                'created_by' => $owner->id,
                'approved_by' => $owner->id,
                'approved_at' => now(),
                'metadata_revision' => 0,
            ]
        );

        $this->createViewingFiles($photo);

        if ($photo->metadata_revision === 0) {
            app(UpdatePhotoMetadata::class)->handle($photo, $owner, [
                'title' => $photo->title,
                'description' => $photo->description,
                'story' => $photo->story,
                'date_precision' => DatePrecision::YearOnly->value,
                'canonical_date' => null,
                'date_year' => 1964,
                'estimated_decade' => null,
                'structured_date_confidence' => StructuredDateConfidence::High->value,
                'date_review_state' => DateReviewState::Accepted->value,
                'date_source_note' => 'A fictional album caption reads "Railway picnic, 1964".',
                'date_reason' => 'The synthetic caption identifies a year but no day or month.',
                'change_reason' => 'Review and accept the fictional album date evidence.',
                'expected_metadata_revision' => 0,
            ]);
            $photo->refresh();
        }

        $source = SourceCollection::query()->firstOrCreate(
            ['source_id' => 'SRC-G12-FICTIONAL-ALBUM'],
            [
                'type' => SourceCollectionType::PhysicalAlbum,
                'name' => 'Fictional Kauri Cover Album',
                'description' => 'Synthetic album representing a preserved family source collection.',
                'physical_reference' => 'Demo shelf A, album 1960s',
                'created_by' => $owner->id,
            ]
        );
        $batch = ScanBatch::query()->firstOrCreate(
            ['scan_batch_id' => 'SCAN-G12-FICTIONAL-001'],
            [
                'source_collection_id' => $source->id,
                'label' => 'Fictional album pages 1-8',
                'scanned_on' => '2026-07-24',
                'notes' => 'Synthetic scan session with no real family media.',
                'created_by' => $owner->id,
            ]
        );

        if ($photo->provenanceLinks()->doesntExist()) {
            app(UpdatePhotoProvenance::class)->attach(
                $photo,
                $source,
                $batch,
                'Synthetic scan from fictional album page four.',
                'Attach reviewed fictional source and scan-batch provenance.',
                $photo->metadata_revision,
                $owner
            );
        }
    }

    private function createViewingFiles(MediaItem $photo): void
    {
        $original = MediaFileVersion::query()->firstOrCreate(
            [
                'media_item_id' => $photo->id,
                'version_type' => MediaFileVersionType::Original,
            ],
            [
                'parent_version_id' => null,
                'storage_disk' => 'archive_originals',
                'storage_path' => 'private/group12-demo/G12-DEMO-001/original.jpg',
                'mime_type' => 'image/jpeg',
                'extension' => 'jpg',
                'file_size_bytes' => 1000,
                'width' => 1600,
                'height' => 1000,
                'sha256' => hash('sha256', 'fictional-group12-original'),
                'generation_status' => GenerationStatus::Ready,
                'is_preferred' => true,
            ]
        );

        foreach ([
            MediaFileVersionType::WebDisplay->value => [1600, 1000],
            MediaFileVersionType::Thumbnail->value => [480, 300],
        ] as $type => [$width, $height]) {
            $enum = MediaFileVersionType::from($type);
            $path = "group12-demo/G12-DEMO-001/{$type}.webp";
            $bytes = $this->imageBytes($width, $height);
            Storage::disk('archive_derivatives')->put($path, $bytes);

            MediaFileVersion::query()->updateOrCreate(
                ['media_item_id' => $photo->id, 'version_type' => $enum],
                [
                    'parent_version_id' => $original->id,
                    'storage_disk' => 'archive_derivatives',
                    'storage_path' => $path,
                    'mime_type' => 'image/webp',
                    'extension' => 'webp',
                    'file_size_bytes' => strlen($bytes),
                    'width' => $width,
                    'height' => $height,
                    'sha256' => hash('sha256', $bytes),
                    'generation_status' => GenerationStatus::Ready,
                    'generation_recipe' => ['recipe_version' => 'photo-v1', 'demo' => true],
                    'is_preferred' => true,
                ]
            );
        }
    }

    private function imageBytes(int $width, int $height): string
    {
        $safeWidth = max(1, $width);
        $safeHeight = max(1, $height);
        $image = imagecreatetruecolor($safeWidth, $safeHeight);

        if ($image === false) {
            throw new RuntimeException('Could not create Group 12 demo image.');
        }

        $sky = imagecolorallocate($image, 87, 132, 151);
        $hills = imagecolorallocate($image, 62, 91, 63);
        $grass = imagecolorallocate($image, 103, 119, 67);
        $sun = imagecolorallocate($image, 238, 196, 112);

        if ($sky === false || $hills === false || $grass === false || $sun === false) {
            imagedestroy($image);
            throw new RuntimeException('Could not allocate Group 12 demo colours.');
        }

        imagefilledrectangle($image, 0, 0, $safeWidth, (int) ($safeHeight * .58), $sky);
        imagefilledellipse($image, (int) ($safeWidth * .76), (int) ($safeHeight * .2), (int) ($safeHeight * .15), (int) ($safeHeight * .15), $sun);
        imagefilledpolygon($image, [0, (int) ($safeHeight * .65), (int) ($safeWidth * .28), (int) ($safeHeight * .3), (int) ($safeWidth * .6), (int) ($safeHeight * .65)], 3, $hills);
        imagefilledrectangle($image, 0, (int) ($safeHeight * .58), $safeWidth, $safeHeight, $grass);

        ob_start();
        $encoded = imagewebp($image, null, 82);
        $bytes = ob_get_clean();
        imagedestroy($image);

        if (! $encoded || $bytes === '') {
            throw new RuntimeException('Could not encode Group 12 demo image.');
        }

        return $bytes;
    }
}
