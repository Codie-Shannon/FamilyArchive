<?php

namespace Database\Seeders;

use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

final class Group09DemoSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::query()->firstOrCreate(
            ['email' => 'archive-owner@example.test'],
            ['name' => 'Archive Owner', 'password' => bcrypt('group09-demo-only'), 'role' => 'owner', 'email_verified_at' => now()],
        );

        $item = MediaItem::query()->firstOrCreate(
            ['archive_id' => 'PH_000901'],
            [
                'media_type' => MediaType::Photo,
                'review_status' => MediaReviewStatus::Approved,
                'created_by' => $owner->id,
                'approved_by' => $owner->id,
                'approved_at' => now(),
            ],
        );

        if ($item->fileVersions()->where('version_type', MediaFileVersionType::Original)->exists()) {
            return;
        }

        $image = imagecreatetruecolor(1600, 900);
        if ($image === false) {
            throw new \RuntimeException('Unable to create fictional Group 09 fixture canvas.');
        }

        $background = imagecolorallocate($image, 24, 82, 120);
        if ($background === false || ! imagefill($image, 0, 0, $background)) {
            imagedestroy($image);
            throw new \RuntimeException('Unable to initialize fictional Group 09 fixture canvas.');
        }

        ob_start();
        $encoded = imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        if (! $encoded || $bytes === '') {
            throw new \RuntimeException('Unable to create fictional Group 09 fixture.');
        }
        $path = 'photos/000/PH_000901.png';
        Storage::disk('archive_originals')->put($path, $bytes);

        MediaFileVersion::query()->create([
            'media_item_id' => $item->id,
            'parent_version_id' => null,
            'version_type' => MediaFileVersionType::Original,
            'storage_disk' => 'archive_originals',
            'storage_path' => $path,
            'mime_type' => 'image/png',
            'extension' => 'png',
            'file_size_bytes' => strlen($bytes),
            'width' => 1600,
            'height' => 900,
            'sha256' => hash('sha256', $bytes),
            'generation_status' => GenerationStatus::Ready,
            'generation_recipe' => ['fixture' => 'fictional-group-09'],
            'is_preferred' => true,
        ]);
    }
}
