<?php

namespace App\Domain\Derivatives\Services;

use App\Domain\Media\Enums\MediaFileVersionType;
use InvalidArgumentException;

final class PhotoDerivativeRecipe
{
    public const VERSION = 'photo-v1';

    /** @return array{version_type: MediaFileVersionType, max_long_side: int, quality: int} */
    public function target(MediaFileVersionType $type): array
    {
        $config = config("archive.photo_derivatives.targets.{$type->value}");

        if (
            ! in_array($type, [MediaFileVersionType::WebDisplay, MediaFileVersionType::Thumbnail], true)
            || ! is_array($config)
            || ! isset($config['max_long_side'], $config['quality'])
            || ! is_int($config['max_long_side'])
            || ! is_int($config['quality'])
            || $config['max_long_side'] < 1
            || $config['quality'] < 1
            || $config['quality'] > 100
        ) {
            throw new InvalidArgumentException("Invalid derivative recipe for {$type->value}.");
        }

        return [
            'version_type' => $type,
            'max_long_side' => $config['max_long_side'],
            'quality' => $config['quality'],
        ];
    }

    /** @return list<MediaFileVersionType> */
    public function types(): array
    {
        return [MediaFileVersionType::WebDisplay, MediaFileVersionType::Thumbnail];
    }

    /** @return array<string, mixed> */
    public function metadata(
        MediaFileVersionType $type,
        string $sourceSha256,
        int $quality,
        int $maxLongSide,
        string $encoder,
        int $sourceOrientation,
        bool $orientationApplied,
    ): array {
        return [
            'recipe_version' => self::VERSION,
            'source_sha256' => strtolower($sourceSha256),
            'target' => $type->value,
            'format' => 'webp',
            'quality' => $quality,
            'max_long_side' => $maxLongSide,
            'no_upscale' => true,
            'encoder' => $encoder,
            'source_orientation' => $sourceOrientation,
            'orientation_applied' => $orientationApplied,
            'metadata_policy' => 'pixels_only_exif_gps_xmp_and_client_filename_removed',
        ];
    }
}
