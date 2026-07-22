<?php

namespace Database\Seeders;

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
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class Group10DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (MediaItem::query()->whereNotLike('archive_id', 'G10-DEMO-%')->exists()) {
            throw new RuntimeException('Group 10 demo seeding refuses to mix with non-demo archive records.');
        }

        $owner = User::query()->firstOrCreate(
            ['email' => 'group10-owner@example.test'],
            ['name' => 'Group 10 Archive Owner', 'password' => bcrypt('group10-demo-only'), 'role' => 'owner', 'email_verified_at' => now()],
        );

        $titles = [
            'Summer Garden Gathering', 'Fictional Lakeside Picnic', 'Old Homestead Veranda', 'Sunday Drive Through Hills',
            'Family Baking Afternoon', 'Coastal Walk at Dusk', 'Winter Cabin Weekend', 'Spring Orchard Visit',
            'Museum Day Out', 'Backyard Lantern Evening', 'Country Road Adventure', 'Rainy Day Story Time',
        ];

        foreach ($titles as $index => $title) {
            $number = $index + 1;
            $item = MediaItem::query()->updateOrCreate(
                ['archive_id' => sprintf('G10-DEMO-%03d', $number)],
                [
                    'media_type' => MediaType::Photo,
                    'title' => $title,
                    'description' => 'Sanitized fictional archive description for private browsing proof.',
                    'story' => 'A generated demonstration record with no real people, places or family media.',
                    'canonical_date' => now()->subYears(20 + $number)->startOfYear(),
                    'estimated_decade' => null,
                    'date_confidence' => DateConfidence::Estimated,
                    'visibility' => MediaVisibility::PrivateArchive,
                    'review_status' => MediaReviewStatus::Approved,
                    'sensitivity_status' => SensitivityStatus::NotFlagged,
                    'created_by' => $owner->id,
                    'approved_by' => $owner->id,
                    'approved_at' => now()->subMinutes($number),
                ],
            );

            $original = MediaFileVersion::query()->updateOrCreate(
                ['media_item_id' => $item->id, 'version_type' => MediaFileVersionType::Original],
                [
                    'parent_version_id' => null,
                    'storage_disk' => 'archive_originals',
                    'storage_path' => 'private/demo/'.$item->archive_id.'/original.jpg',
                    'mime_type' => 'image/jpeg', 'extension' => 'jpg', 'file_size_bytes' => 1000,
                    'width' => 1600, 'height' => 1000, 'duration_ms' => null,
                    'sha256' => hash('sha256', 'group10-original-'.$item->archive_id),
                    'perceptual_hash' => null, 'generation_status' => GenerationStatus::Ready,
                    'generation_recipe' => null, 'is_preferred' => true,
                ],
            );

            if ($number === 11) {
                $this->failedDerivative($item, $original, MediaFileVersionType::Thumbnail);

                continue;
            }
            if ($number === 12) {
                $this->readyDerivative($item, $original, MediaFileVersionType::Thumbnail, $number, 480, 300);
                $this->failedDerivative($item, $original, MediaFileVersionType::WebDisplay);

                continue;
            }

            $this->readyDerivative($item, $original, MediaFileVersionType::Thumbnail, $number, 480, 300);
            $this->readyDerivative($item, $original, MediaFileVersionType::WebDisplay, $number, 1600, 1000);
        }
    }

    private function readyDerivative(MediaItem $item, MediaFileVersion $original, MediaFileVersionType $type, int $seed, int $width, int $height): void
    {
        $path = 'group10-demo/'.$item->archive_id.'/'.$type->value.'.webp';
        $bytes = $this->imageBytes($width, $height, $seed, $type->value);
        Storage::disk('archive_derivatives')->put($path, $bytes);

        MediaFileVersion::query()->updateOrCreate(
            ['media_item_id' => $item->id, 'version_type' => $type],
            [
                'parent_version_id' => $original->id, 'storage_disk' => 'archive_derivatives', 'storage_path' => $path,
                'mime_type' => 'image/webp', 'extension' => 'webp', 'file_size_bytes' => strlen($bytes),
                'width' => $width, 'height' => $height, 'duration_ms' => null, 'sha256' => hash('sha256', $bytes),
                'perceptual_hash' => null, 'generation_status' => GenerationStatus::Ready,
                'generation_recipe' => ['recipe_version' => 'photo-v1', 'demo' => true], 'is_preferred' => true,
            ],
        );
    }

    private function failedDerivative(MediaItem $item, MediaFileVersion $original, MediaFileVersionType $type): void
    {
        MediaFileVersion::query()->updateOrCreate(
            ['media_item_id' => $item->id, 'version_type' => $type],
            [
                'parent_version_id' => $original->id, 'storage_disk' => 'archive_derivatives',
                'storage_path' => 'group10-demo/'.$item->archive_id.'/'.$type->value.'-failed.webp',
                'mime_type' => 'image/webp', 'extension' => 'webp', 'file_size_bytes' => 0,
                'width' => null, 'height' => null, 'duration_ms' => null, 'sha256' => hash('sha256', ''),
                'perceptual_hash' => null, 'generation_status' => GenerationStatus::Failed,
                'generation_recipe' => ['recipe_version' => 'photo-v1', 'demo' => true], 'is_preferred' => false,
            ],
        );
    }

    private function imageBytes(int $width, int $height, int $seed, string $label): string
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagewebp')) {
            throw new RuntimeException('GD WebP support is required for Group 10 demo images.');
        }

        $safeWidth = max(1, $width);
        $safeHeight = max(1, $height);
        $image = imagecreatetruecolor($safeWidth, $safeHeight);

        if ($image === false) {
            throw new RuntimeException('Could not create Group 10 demo image canvas.');
        }

        $sky = $this->allocateColor($image, 25 + (($seed * 17) % 80), 70 + (($seed * 13) % 70), 105 + (($seed * 11) % 70));
        $ground = $this->allocateColor($image, 70 + (($seed * 9) % 80), 80 + (($seed * 7) % 60), 45 + (($seed * 5) % 60));
        $sun = $this->allocateColor($image, 245, 205, 105);
        imagefilledrectangle($image, 0, 0, $safeWidth, (int) ($safeHeight * .62), $sky);
        imagefilledrectangle($image, 0, (int) ($safeHeight * .62), $safeWidth, $safeHeight, $ground);
        imagefilledellipse($image, (int) ($safeWidth * .78), (int) ($safeHeight * .22), (int) ($safeHeight * .18), (int) ($safeHeight * .18), $sun);

        for ($i = 0; $i < 6; $i++) {
            $shade = $this->allocateColor($image, 35 + $i * 8, 45 + $i * 7, 35 + $i * 4);
            imagefilledpolygon($image, [0, (int) ($safeHeight * (.62 - $i * .035)), (int) ($safeWidth * .25), (int) ($safeHeight * (.32 + $i * .02)), (int) ($safeWidth * .52), (int) ($safeHeight * (.62 - $i * .02))], 3, $shade);
        }

        ob_start();
        $encoded = imagewebp($image, null, $label === 'thumbnail' ? 72 : 82);
        $bytes = ob_get_clean();
        imagedestroy($image);

        if (! $encoded) {
            throw new RuntimeException('Could not encode Group 10 demo image.');
        }

        return $bytes;
    }

    private function allocateColor(\GdImage $image, int $red, int $green, int $blue): int
    {
        $color = imagecolorallocate(
            $image,
            max(0, min(255, $red)),
            max(0, min(255, $green)),
            max(0, min(255, $blue)),
        );

        if ($color === false) {
            throw new RuntimeException('Could not allocate Group 10 demo image color.');
        }

        return $color;
    }
}
