<?php

namespace App\Domain\Processing\Services;

use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Models\MediaFileVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RestorationWorkflow
{
    /** @param array<string, mixed> $operations */
    public function createRecipe(string $name, int $version, array $operations, bool $batch = false): int
    {
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
            'recipe_id' => 'RCP-'.strtoupper(Str::random(12)),
            'name' => $name,
            'version' => $version,
            'operations' => json_encode($operations, JSON_THROW_ON_ERROR),
            'is_batch_profile' => $batch,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function queue(MediaFileVersion $source, int $recipeId): string
    {
        if ($source->version_type !== MediaFileVersionType::Original || ! $source->is_preferred) {
            throw ValidationException::withMessages(['source' => 'A verified preferred original is required as the immutable source.']);
        }

        if (! DB::table('processing_recipes')->where('id', $recipeId)->exists()) {
            throw ValidationException::withMessages(['recipe' => 'A versioned restoration recipe is required.']);
        }

        $jobId = (string) Str::uuid();
        DB::table('processing_jobs')->insert([
            'job_id' => $jobId,
            'media_item_id' => $source->media_item_id,
            'processing_recipe_id' => $recipeId,
            'state' => 'queued',
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $jobId;
    }
}
