<?php

namespace Database\Factories;

use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MediaFileVersion>
 */
class MediaFileVersionFactory extends Factory
{
    protected $model = MediaFileVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = Str::lower(Str::random(12));

        return [
            'media_item_id' => MediaItem::factory(),
            'parent_version_id' => null,
            'version_type' => MediaFileVersionType::Original,
            'storage_disk' => 'local',
            'storage_path' => 'demo/archive/'.$token.'/original/fictional-source.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'file_size_bytes' => 8192,
            'width' => 1600,
            'height' => 900,
            'duration_ms' => null,
            'sha256' => hash('sha256', 'fictional-version-'.$token),
            'perceptual_hash' => 'demo-'.$token,
            'generation_status' => GenerationStatus::NotRequired,
            'generation_recipe' => null,
            'is_preferred' => true,
        ];
    }
}
