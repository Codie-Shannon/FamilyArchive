<?php

namespace Database\Seeders;

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
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class Group02DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('Group02DemoSeeder may run only in the local environment.');
        }

        $containsNonDemoData = MediaItem::query()
            ->where('archive_id', 'not like', 'FA-DEMO-%')
            ->exists()
            || IncomingUpload::query()
                ->where('upload_id', 'not like', 'UP-DEMO-%')
                ->exists()
            || MediaFileVersion::query()
                ->where('storage_path', 'not like', 'demo/%')
                ->exists();

        if ($containsNonDemoData) {
            throw new RuntimeException('Group 02 demo data was not seeded because non-demo media records exist.');
        }

        DB::transaction(function (): void {
            $owner = $this->demoUser(
                email: 'archive-owner@example.test',
                name: 'Fictional Archive Owner',
                role: 'owner',
            );

            $contributor = $this->demoUser(
                email: 'archive-contributor@example.test',
                name: 'Fictional Archive Contributor',
                role: 'viewer',
            );

            $mediaItem = MediaItem::query()->updateOrCreate(
                ['archive_id' => 'FA-DEMO-00000001'],
                [
                    'media_type' => MediaType::Photo,
                    'title' => 'Fictional Archive Scene',
                    'description' => 'Sanitized metadata for the Group 02 schema proof.',
                    'story' => 'A fictional archive record used only to demonstrate safe record boundaries.',
                    'canonical_date' => null,
                    'estimated_decade' => 1980,
                    'date_confidence' => DateConfidence::DecadeOnly,
                    'visibility' => MediaVisibility::PrivateArchive,
                    'review_status' => MediaReviewStatus::Approved,
                    'sensitivity_status' => SensitivityStatus::NotFlagged,
                    'created_by' => $owner->id,
                    'approved_by' => $owner->id,
                    'approved_at' => now(),
                ],
            );

            IncomingUpload::query()->updateOrCreate(
                ['upload_id' => 'UP-DEMO-00000001'],
                [
                    'uploader_id' => $contributor->id,
                    'reviewed_by' => $owner->id,
                    'media_item_id' => $mediaItem->id,
                    'original_filename' => 'fictional-archive-scene.jpg',
                    'incoming_path' => 'demo/intake/fictional-archive-scene.jpg',
                    'mime_type' => 'image/jpeg',
                    'extension' => 'jpg',
                    'file_size_bytes' => 1200000,
                    'width' => 2400,
                    'height' => 1600,
                    'duration_ms' => null,
                    'sha256' => hash('sha256', 'group-02-fictional-incoming-upload'),
                    'perceptual_hash' => 'demo-perceptual-hash-0001',
                    'processing_status' => IncomingProcessingStatus::Processed,
                    'review_status' => IncomingReviewStatus::Approved,
                    'duplicate_status' => DuplicateStatus::NoMatch,
                    'source_file_retained' => true,
                    'source_file_removed_at' => null,
                    'submitted_at' => now(),
                    'reviewed_at' => now(),
                ],
            );

            $original = MediaFileVersion::query()->updateOrCreate(
                ['storage_path' => 'demo/archive/FA-DEMO-00000001/original/fictional-archive-scene.jpg'],
                [
                    'media_item_id' => $mediaItem->id,
                    'parent_version_id' => null,
                    'version_type' => MediaFileVersionType::Original,
                    'storage_disk' => 'local',
                    'mime_type' => 'image/jpeg',
                    'extension' => 'jpg',
                    'file_size_bytes' => 1200000,
                    'width' => 2400,
                    'height' => 1600,
                    'duration_ms' => null,
                    'sha256' => hash('sha256', 'group-02-fictional-original'),
                    'perceptual_hash' => 'demo-perceptual-hash-0001',
                    'generation_status' => GenerationStatus::NotRequired,
                    'generation_recipe' => null,
                    'is_preferred' => true,
                ],
            );

            MediaFileVersion::query()->updateOrCreate(
                ['storage_path' => 'demo/archive/FA-DEMO-00000001/web/fictional-archive-scene.webp'],
                [
                    'media_item_id' => $mediaItem->id,
                    'parent_version_id' => $original->id,
                    'version_type' => MediaFileVersionType::WebDisplay,
                    'storage_disk' => 'local',
                    'mime_type' => 'image/webp',
                    'extension' => 'webp',
                    'file_size_bytes' => 180000,
                    'width' => 1600,
                    'height' => 1067,
                    'duration_ms' => null,
                    'sha256' => hash('sha256', 'group-02-fictional-web-display'),
                    'perceptual_hash' => 'demo-perceptual-hash-0001',
                    'generation_status' => GenerationStatus::Ready,
                    'generation_recipe' => [
                        'profile' => 'fictional-web-display',
                        'source_version' => 'original',
                    ],
                    'is_preferred' => true,
                ],
            );

            MediaFileVersion::query()->updateOrCreate(
                ['storage_path' => 'demo/archive/FA-DEMO-00000001/thumbnail/fictional-archive-scene.webp'],
                [
                    'media_item_id' => $mediaItem->id,
                    'parent_version_id' => $original->id,
                    'version_type' => MediaFileVersionType::Thumbnail,
                    'storage_disk' => 'local',
                    'mime_type' => 'image/webp',
                    'extension' => 'webp',
                    'file_size_bytes' => 24000,
                    'width' => 480,
                    'height' => 320,
                    'duration_ms' => null,
                    'sha256' => hash('sha256', 'group-02-fictional-thumbnail'),
                    'perceptual_hash' => 'demo-perceptual-hash-0001',
                    'generation_status' => GenerationStatus::Ready,
                    'generation_recipe' => [
                        'profile' => 'fictional-thumbnail',
                        'source_version' => 'original',
                    ],
                    'is_preferred' => true,
                ],
            );
        });
    }

    private function demoUser(string $email, string $name, string $role): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);

        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('group-02-demo-only'),
            'email_verified_at' => now(),
            'role' => $role,
        ]);
        $user->save();

        return $user;
    }
}
