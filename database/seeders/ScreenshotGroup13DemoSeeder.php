<?php

namespace Database\Seeders;

use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Processing\Models\ProcessingJob;
use App\Domain\Processing\Models\ProcessingJobEvent;
use App\Domain\Processing\Models\ProcessingRecipe;
use App\Domain\Processing\Models\RestorationCandidate;
use App\Models\User;
use GdImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

final class ScreenshotGroup13DemoSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('local'), 403, 'The SG13 dataset is local-only.');

        $owner = User::query()->updateOrCreate(
            ['email' => 'sg13-owner@example.test'],
            [
                'name' => 'Mereana Raukawa',
                'password' => Hash::make('SG13Demo!2026'),
                'role' => 'owner',
                'account_state' => 'approved',
                'email_verified_at' => now(),
            ],
        );

        $records = [
            [
                'archive_id' => 'PH_013101',
                'title' => 'Crooked album portrait',
                'recipe' => 'Photographed print edge recovery',
                'state' => 'candidate_ready',
                'review' => 'pending',
                'theme' => 1,
                'preferences' => $this->preferences(),
                'analysis' => [
                    'source_dimensions' => ['width' => 1200, 'height' => 820],
                    'candidate_dimensions' => ['width' => 930, 'height' => 610],
                    'deskew' => ['degrees' => 3.4, 'confidence' => 0.82],
                    'crop' => ['confidence' => 0.91, 'applied' => true],
                    'uploader_controls_respected' => true,
                    'manual_only_preferences' => [],
                ],
                'operations' => ['deskew', 'auto_crop', 'gentle_exposure', 'neutral_colour'],
                'minutes' => 1,
            ],
            [
                'archive_id' => 'PH_013102',
                'title' => 'Faded harbour picnic print',
                'recipe' => 'Gentle colour and edge cleanup',
                'state' => 'approved',
                'review' => 'approved',
                'theme' => 2,
                'preferences' => $this->preferences(['denoise' => true]),
                'analysis' => [
                    'source_dimensions' => ['width' => 1200, 'height' => 820],
                    'candidate_dimensions' => ['width' => 930, 'height' => 610],
                    'deskew' => ['degrees' => -1.8, 'confidence' => 0.76],
                    'crop' => ['confidence' => 0.88, 'applied' => true],
                    'uploader_controls_respected' => true,
                    'manual_only_preferences' => [],
                ],
                'operations' => ['deskew', 'auto_crop', 'gentle_exposure', 'neutral_colour', 'gentle_denoise'],
                'minutes' => 7,
            ],
            [
                'archive_id' => 'PH_013103',
                'title' => 'Wedding album contact print',
                'recipe' => 'Orientation suggestions only',
                'state' => 'queued',
                'review' => null,
                'theme' => 3,
                'preferences' => $this->preferences([
                    'deskew' => false,
                    'crop_target' => 'none',
                    'exposure' => false,
                    'color' => false,
                ]),
                'analysis' => null,
                'operations' => [],
                'minutes' => 12,
            ],
        ];

        foreach ($records as $record) {
            $this->record($owner, $record);
        }
    }

    /**
     * @param array{
     *   archive_id: string,
     *   title: string,
     *   recipe: string,
     *   state: string,
     *   review: string|null,
     *   theme: int,
     *   preferences: array<string, bool|string>,
     *   analysis: array<string, mixed>|null,
     *   operations: list<string>,
     *   minutes: int
     * } $record
     */
    private function record(User $owner, array $record): void
    {
        $item = MediaItem::query()->updateOrCreate(
            ['archive_id' => $record['archive_id']],
            [
                'media_type' => MediaType::Photo,
                'title' => $record['title'],
                'description' => 'Synthetic restoration demonstration with no real people or family media.',
                'visibility' => MediaVisibility::PrivateArchive,
                'review_status' => MediaReviewStatus::Approved,
                'created_by' => $owner->id,
                'approved_by' => $owner->id,
                'approved_at' => now(),
            ],
        );

        $sourceBytes = $this->sourceImage($record['theme']);
        $sourcePath = 'photo/013/'.$record['archive_id'].'.jpg';
        $this->putDeterministic('archive_originals', $sourcePath, $sourceBytes);
        $sourceFacts = getimagesizefromstring($sourceBytes);
        if (! is_array($sourceFacts)) {
            throw new \RuntimeException('The fictional SG13 source image could not be decoded.');
        }
        $source = MediaFileVersion::query()->updateOrCreate(
            ['storage_disk' => 'archive_originals', 'storage_path' => $sourcePath],
            [
                'media_item_id' => $item->id,
                'parent_version_id' => null,
                'version_type' => MediaFileVersionType::Original,
                'mime_type' => 'image/jpeg',
                'extension' => 'jpg',
                'file_size_bytes' => strlen($sourceBytes),
                'width' => $sourceFacts[0],
                'height' => $sourceFacts[1],
                'sha256' => hash('sha256', $sourceBytes),
                'generation_status' => GenerationStatus::Ready,
                'generation_recipe' => null,
                'is_preferred' => true,
            ],
        );

        $recipe = ProcessingRecipe::query()->updateOrCreate(
            ['name' => $record['recipe'], 'version' => 1],
            [
                'created_by' => $owner->id,
                'recipe_id' => 'RCP-'.$record['archive_id'],
                'operations' => [
                    'orient' => ['mode' => 'exif'],
                    'deskew' => ['max_degrees' => 8],
                    'crop' => ['target' => 'photo_edge'],
                    'exposure' => ['strength' => 'gentle'],
                    'colour' => ['mode' => 'neutral'],
                    'denoise' => ['strength' => 'gentle'],
                ],
                'automation_source' => 'uploader_preferences',
                'is_batch_profile' => false,
                'is_active' => true,
            ],
        );
        $job = ProcessingJob::query()->updateOrCreate(
            ['job_id' => '13000000-0000-4000-8000-'.str_pad((string) $record['theme'], 12, '0', STR_PAD_LEFT)],
            [
                'media_item_id' => $item->id,
                'source_version_id' => $source->id,
                'processing_recipe_id' => $recipe->id,
                'requested_by' => $owner->id,
                'automation_preferences' => $record['preferences'],
                'state' => $record['state'],
                'attempts' => $record['state'] === 'queued' ? 0 : 1,
                'started_at' => $record['state'] === 'queued' ? null : now()->subMinutes($record['minutes']),
                'completed_at' => $record['state'] === 'queued' ? null : now()->subMinutes($record['minutes'])->addSeconds(3),
                'created_at' => now()->subMinutes($record['minutes']),
                'updated_at' => now()->subMinutes($record['minutes']),
            ],
        );

        if ($record['review'] === null) {
            ProcessingJobEvent::query()->firstOrCreate(
                ['processing_job_id' => $job->id, 'event' => 'queued'],
                [
                    'actor_id' => $owner->id,
                    'safe_context' => ['automation_mode' => 'suggestions', 'uploader_preferences_inherited' => true],
                    'occurred_at' => now()->subMinutes($record['minutes']),
                ],
            );

            return;
        }

        $candidateBytes = $this->candidateImage($record['theme']);
        $candidatePath = 'restoration-candidates/'.$item->id.'/sg13-'.$record['theme'].'.webp';
        $this->putDeterministic('archive_derivatives', $candidatePath, $candidateBytes);
        $candidateFacts = getimagesizefromstring($candidateBytes);
        if (! is_array($candidateFacts)) {
            throw new \RuntimeException('The fictional SG13 candidate image could not be decoded.');
        }
        $candidateVersion = MediaFileVersion::query()->updateOrCreate(
            ['storage_disk' => 'archive_derivatives', 'storage_path' => $candidatePath],
            [
                'media_item_id' => $item->id,
                'parent_version_id' => $source->id,
                'version_type' => MediaFileVersionType::EditedFull,
                'mime_type' => 'image/webp',
                'extension' => 'webp',
                'file_size_bytes' => strlen($candidateBytes),
                'width' => $candidateFacts[0],
                'height' => $candidateFacts[1],
                'sha256' => hash('sha256', $candidateBytes),
                'generation_status' => GenerationStatus::Ready,
                'generation_recipe' => [
                    'release' => '1.8.0',
                    'recipe_id' => $recipe->recipe_id,
                    'source_sha256' => hash('sha256', $sourceBytes),
                    'operations_applied' => $record['operations'],
                    'preserves_original' => true,
                ],
                'is_preferred' => $record['review'] === 'approved',
            ],
        );
        $candidate = RestorationCandidate::query()->updateOrCreate(
            ['candidate_id' => '13000000-0000-4000-9000-'.str_pad((string) $record['theme'], 12, '0', STR_PAD_LEFT)],
            [
                'processing_job_id' => $job->id,
                'source_version_id' => $source->id,
                'candidate_version_id' => $candidateVersion->id,
                'quality_checks' => [
                    'source_hash_verified_before' => true,
                    'source_hash_verified_after' => true,
                    'candidate_hash_verified' => true,
                    'separate_derivative_storage' => true,
                    'human_review_required' => true,
                ],
                'analysis' => $record['analysis'],
                'operations_applied' => $record['operations'],
                'review_state' => $record['review'],
                'reviewed_by' => $record['review'] === 'approved' ? $owner->id : null,
                'review_note' => $record['review'] === 'approved' ? 'Synthetic candidate reviewed; source remains unchanged.' : null,
                'reviewed_at' => $record['review'] === 'approved' ? now()->subMinutes(4) : null,
            ],
        );
        foreach (['queued', 'processing_started', 'candidate_ready'] as $offset => $event) {
            ProcessingJobEvent::query()->firstOrCreate(
                ['processing_job_id' => $job->id, 'event' => $event],
                [
                    'actor_id' => $owner->id,
                    'safe_context' => ['candidate_id' => $candidate->candidate_id, 'original_retained' => true],
                    'occurred_at' => now()->subMinutes($record['minutes'])->addSeconds($offset),
                ],
            );
        }
        if ($record['review'] === 'approved') {
            ProcessingJobEvent::query()->firstOrCreate(
                ['processing_job_id' => $job->id, 'event' => 'candidate_approved'],
                [
                    'actor_id' => $owner->id,
                    'safe_context' => ['candidate_id' => $candidate->candidate_id, 'original_retained' => true],
                    'occurred_at' => now()->subMinutes(4),
                ],
            );
        }
    }

    /** @param array<string, bool|string> $overrides
     * @return array<string, bool|string>
     */
    private function preferences(array $overrides = []): array
    {
        return [
            'automation_mode' => 'candidates',
            'auto_rotate' => true,
            'deskew' => true,
            'perspective' => false,
            'crop_target' => 'photo_edge',
            'exposure' => true,
            'color' => true,
            'denoise' => false,
            'sharpen' => false,
            'cleanup' => false,
            'damage_repair' => false,
            'upscale' => false,
            'quality_warnings' => true,
            ...$overrides,
        ];
    }

    private function putDeterministic(string $disk, string $path, string $bytes): void
    {
        $storage = Storage::disk($disk);
        if ($storage->exists($path)) {
            $existing = $storage->get($path);
            if (! hash_equals(hash('sha256', $existing), hash('sha256', $bytes))) {
                throw new \RuntimeException("The existing fictional SG13 object {$path} does not match its deterministic fixture.");
            }

            return;
        }
        $storage->put($path, $bytes);
    }

    private function sourceImage(int $theme): string
    {
        $skyRed = match ($theme) {
            1 => 66,
            2 => 78,
            default => 90,
        };
        $fieldGreen = match ($theme) {
            1 => 112,
            2 => 120,
            default => 128,
        };
        $woodGreen = match ($theme) {
            1 => 75,
            2 => 80,
            default => 85,
        };
        $canvas = imagecreatetruecolor(1200, 820);
        imagefill($canvas, 0, 0, $this->color($canvas, 235, 231, 218));
        imagefilledrectangle($canvas, 100, 70, 1110, 760, $this->color($canvas, 188, 178, 158));
        imagefilledrectangle($canvas, 135, 105, 1075, 725, $this->color($canvas, $skyRed, 72, 92));
        imagefilledrectangle($canvas, 135, 510, 1075, 725, $this->color($canvas, 82, $fieldGreen, 68));
        imagefilledellipse($canvas, 420, 365, 230, 285, $this->color($canvas, 224, 202, 155));
        imagefilledellipse($canvas, 770, 350, 240, 300, $this->color($canvas, 214, 190, 146));
        imagefilledrectangle($canvas, 320, 510, 870, 650, $this->color($canvas, 122, $woodGreen, 54));
        imagestring($canvas, 5, 175, 675, 'FICTIONAL SG13 SOURCE - ORIGINAL RETAINED', $this->color($canvas, 247, 242, 225));

        ob_start();
        imagejpeg($canvas, null, 92);
        $bytes = ob_get_clean();
        imagedestroy($canvas);

        return $bytes;
    }

    private function candidateImage(int $theme): string
    {
        $skyRed = match ($theme) {
            1 => 74,
            2 => 86,
            default => 98,
        };
        $fieldGreen = match ($theme) {
            1 => 124,
            2 => 130,
            default => 136,
        };
        $woodGreen = match ($theme) {
            1 => 80,
            2 => 85,
            default => 90,
        };
        $canvas = imagecreatetruecolor(930, 610);
        imagefill($canvas, 0, 0, $this->color($canvas, $skyRed, 82, 106));
        imagefilledrectangle($canvas, 0, 405, 930, 610, $this->color($canvas, 80, $fieldGreen, 70));
        imagefilledellipse($canvas, 285, 255, 225, 285, $this->color($canvas, 229, 207, 158));
        imagefilledellipse($canvas, 640, 245, 235, 300, $this->color($canvas, 220, 196, 150));
        imagefilledrectangle($canvas, 185, 405, 740, 548, $this->color($canvas, 128, $woodGreen, 56));
        imagestring($canvas, 5, 28, 575, 'FICTIONAL REVIEW CANDIDATE - SEPARATE WEBP', $this->color($canvas, 248, 244, 229));

        ob_start();
        imagewebp($canvas, null, 92);
        $bytes = ob_get_clean();
        imagedestroy($canvas);

        return $bytes;
    }

    /** @param int<0, 255> $red
     * @param  int<0, 255>  $green
     * @param  int<0, 255>  $blue
     */
    private function color(GdImage $image, int $red, int $green, int $blue): int
    {
        $color = imagecolorallocate($image, $red, $green, $blue);
        if ($color === false) {
            throw new \RuntimeException('The fictional SG13 palette could not be allocated.');
        }

        return $color;
    }
}
