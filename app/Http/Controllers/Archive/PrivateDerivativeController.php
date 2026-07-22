<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Derivatives\Exceptions\DerivativeGenerationException;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Models\MediaFileVersion;
use App\Http\Controllers\Controller;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

final class PrivateDerivativeController extends Controller
{
    public function __invoke(MediaFileVersion $mediaFileVersion): Response
    {
        $mediaFileVersion->load(['mediaItem', 'parentVersion']);
        $parent = $mediaFileVersion->parentVersion;
        $item = $mediaFileVersion->mediaItem;

        if (
            ! in_array($mediaFileVersion->version_type, [MediaFileVersionType::WebDisplay, MediaFileVersionType::Thumbnail], true)
            || $mediaFileVersion->generation_status !== GenerationStatus::Ready
            || ! $mediaFileVersion->is_preferred
            || $mediaFileVersion->storage_disk !== 'archive_derivatives'
            || $mediaFileVersion->mime_type !== 'image/webp'
            || ! $parent instanceof MediaFileVersion
            || $parent->version_type !== MediaFileVersionType::Original
            || $parent->generation_status !== GenerationStatus::Ready
            || ! $parent->is_preferred
            || $item === null
            || $item->media_type !== MediaType::Photo
            || $item->review_status !== MediaReviewStatus::Approved
        ) {
            abort(404);
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('archive_derivatives');
        if (! $disk->exists($mediaFileVersion->storage_path)) {
            abort(404);
        }
        $bytes = $disk->get($mediaFileVersion->storage_path);
        if (strlen($bytes) !== $mediaFileVersion->file_size_bytes || ! hash_equals(strtolower($mediaFileVersion->sha256), hash('sha256', $bytes))) {
            throw new DerivativeGenerationException('The private derivative preview failed integrity verification.');
        }

        return response($bytes, 200, [
            'Content-Type' => 'image/webp',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
