<?php

namespace App\Domain\Processing\Services;

use App\Domain\Access\Models\ContributorSubmission;
use App\Domain\Archive\Models\ArchivePromotion;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Processing\Models\ProcessingJob;
use App\Domain\Processing\Models\ProcessingJobEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RestorationWorkflow
{
    /** @param array<string, mixed> $operations */
    public function createRecipe(
        string $name,
        int $version,
        array $operations,
        bool $batch = false,
        ?User $actor = null,
        string $automationSource = 'owner',
    ): int {
        $name = trim($name);
        $allowed = [
            'orient',
            'deskew',
            'crop',
            'exposure',
            'colour',
            'tone',
            'denoise',
            'grain',
            'sharpen',
            'surface_cleanup',
            'damage_repair',
            'upscale',
        ];
        $unknown = array_diff(array_keys($operations), $allowed);

        if ($name === '' || mb_strlen($name) > 255) {
            throw ValidationException::withMessages(['name' => 'A recipe name is required.']);
        }

        if ($version < 1) {
            throw ValidationException::withMessages(['version' => 'Recipe versions start at 1.']);
        }

        if ($operations === []) {
            throw ValidationException::withMessages(['operations' => 'At least one approved restoration operation is required.']);
        }

        if ($unknown !== []) {
            throw ValidationException::withMessages(['operations' => 'Unsupported restoration operation: '.implode(', ', $unknown)]);
        }

        return DB::table('processing_recipes')->insertGetId([
            'created_by' => $actor?->id,
            'recipe_id' => 'RCP-'.strtoupper(Str::random(12)),
            'name' => $name,
            'version' => $version,
            'operations' => json_encode($operations, JSON_THROW_ON_ERROR),
            'automation_source' => $automationSource,
            'is_batch_profile' => $batch,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $preferences
     */
    public function queue(
        MediaFileVersion $source,
        int $recipeId,
        ?User $actor = null,
        ?array $preferences = null,
    ): string {
        if ($source->version_type !== MediaFileVersionType::Original || ! $source->is_preferred) {
            throw ValidationException::withMessages(['source' => 'A verified preferred original is required as the immutable source.']);
        }

        if (! DB::table('processing_recipes')->where('id', $recipeId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['recipe' => 'A versioned restoration recipe is required.']);
        }

        $jobId = (string) Str::uuid();
        $normalized = $this->normalizePreferences($preferences ?? $this->preferencesForSource($source));
        $job = ProcessingJob::query()->create([
            'job_id' => $jobId,
            'media_item_id' => $source->media_item_id,
            'source_version_id' => $source->id,
            'processing_recipe_id' => $recipeId,
            'requested_by' => $actor?->id,
            'automation_preferences' => $normalized,
            'state' => 'queued',
            'attempts' => 0,
        ]);
        ProcessingJobEvent::query()->create([
            'processing_job_id' => $job->id,
            'actor_id' => $actor?->id,
            'event' => 'queued',
            'safe_context' => [
                'automation_mode' => $normalized['automation_mode'],
                'uploader_preferences_inherited' => $preferences === null,
            ],
            'occurred_at' => now(),
        ]);

        return $jobId;
    }

    /**
     * @param  array<string, mixed>  $preferences
     */
    public function createFromPreferences(string $name, array $preferences, User $actor): int
    {
        $preferences = $this->normalizePreferences($preferences);
        $operations = [];

        if ($preferences['auto_rotate']) {
            $operations['orient'] = ['mode' => 'exif'];
        }
        if ($preferences['deskew']) {
            $operations['deskew'] = ['max_degrees' => 8];
        }
        if ($preferences['crop_target'] !== 'none') {
            $operations['crop'] = ['target' => $preferences['crop_target']];
        }
        foreach (['exposure', 'denoise', 'sharpen'] as $operation) {
            if ($preferences[$operation]) {
                $operations[$operation] = ['strength' => 'gentle'];
            }
        }
        if ($preferences['color']) {
            $operations['colour'] = ['mode' => 'neutral'];
        }
        if ($preferences['cleanup']) {
            $operations['surface_cleanup'] = ['strength' => 'gentle'];
        }

        if ($operations === []) {
            throw ValidationException::withMessages([
                'automation_preferences' => 'Enable at least one review-candidate operation.',
            ]);
        }

        $nextVersion = (int) DB::table('processing_recipes')->where('name', trim($name))->max('version') + 1;

        return $this->createRecipe(
            $name,
            max(1, $nextVersion),
            $operations,
            false,
            $actor,
            'uploader_preferences',
        );
    }

    /**
     * @param  array<string, mixed>  $preferences
     * @return array<string, bool|string>
     */
    public function normalizePreferences(array $preferences): array
    {
        $mode = $preferences['automation_mode'] ?? 'suggestions';
        if (! in_array($mode, ['off', 'suggestions', 'candidates'], true)) {
            $mode = 'suggestions';
        }

        $crop = $preferences['crop_target'] ?? 'none';
        if (! in_array($crop, ['none', 'photo_edge', 'content'], true)) {
            $crop = 'none';
        }

        $normalized = [
            'automation_mode' => $mode,
            'crop_target' => $crop,
        ];
        foreach ([
            'auto_rotate',
            'deskew',
            'perspective',
            'exposure',
            'color',
            'denoise',
            'sharpen',
            'cleanup',
            'damage_repair',
            'upscale',
            'quality_warnings',
        ] as $preference) {
            $normalized[$preference] = filter_var($preferences[$preference] ?? false, FILTER_VALIDATE_BOOL);
        }

        return $normalized;
    }

    /** @return array<string, mixed> */
    private function preferencesForSource(MediaFileVersion $source): array
    {
        $promotion = ArchivePromotion::query()
            ->where('original_media_file_version_id', $source->id)
            ->first();
        if (! $promotion instanceof ArchivePromotion) {
            return [];
        }

        $submission = ContributorSubmission::query()
            ->where('incoming_upload_id', $promotion->incoming_upload_id)
            ->first();

        if (! $submission instanceof ContributorSubmission) {
            return [];
        }

        $preferences = $submission->automation_preferences;

        return is_array($preferences) ? $preferences : [];
    }
}
