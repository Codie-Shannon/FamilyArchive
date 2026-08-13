<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Access\Services\ArchiveAccess;
use App\Domain\Derivatives\Exceptions\DerivativeGenerationException;
use App\Domain\Derivatives\Services\ApprovedPhotoViewingSource;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Models\MediaFileVersion;
use App\Http\Controllers\Controller;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

final class PrivateDerivativeController extends Controller
{
    public function __invoke(
        Request $request,
        MediaFileVersion $mediaFileVersion,
        ArchiveAccess $access,
        ApprovedPhotoViewingSource $sources,
    ): Response {
        $mediaFileVersion->load(['mediaItem', 'parentVersion']);
        $parent = $mediaFileVersion->parentVersion;
        $item = $mediaFileVersion->mediaItem;
        $viewingSource = $sources->resolve($item);

        if (
            ! in_array($mediaFileVersion->version_type, [MediaFileVersionType::WebDisplay, MediaFileVersionType::Thumbnail], true)
            || $mediaFileVersion->generation_status !== GenerationStatus::Ready
            || ! $mediaFileVersion->is_preferred
            || $mediaFileVersion->storage_disk !== 'archive_derivatives'
            || $mediaFileVersion->mime_type !== 'image/webp'
            || ! $parent instanceof MediaFileVersion
            || $parent->generation_status !== GenerationStatus::Ready
            || ! $parent->is_preferred
            || ! $viewingSource instanceof MediaFileVersion
            || $viewingSource->id !== $parent->id
            || $item->media_type !== MediaType::Photo
            || $item->review_status !== MediaReviewStatus::Approved
            || (! $access->canView($request->user(), $item)
                && ! ($item->hidden_at !== null
                    && ($request->user()->role === 'owner' || $item->created_by === $request->user()->id)))
        ) {
            abort(404);
        }

        $bytes = $this->verifiedDerivativeBytes($mediaFileVersion);

        return response($bytes, 200, [
            'Content-Type' => 'image/webp',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function verifiedDerivativeBytes(MediaFileVersion $mediaFileVersion): string
    {
        $diskNames = [$mediaFileVersion->storage_disk];

        // Local proof imports can deliberately pin writes to the stable local
        // archive while the long-running web process remains configured for
        // production storage. Keep that fallback local-only and integrity
        // verify the bytes before serving them.
        if (app()->environment('local', 'testing')) {
            array_unshift($diskNames, 'archive_local_derivatives');
        }

        $foundCandidate = false;

        foreach (array_unique($diskNames) as $diskName) {
            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk($diskName);

            if (! $disk->exists($mediaFileVersion->storage_path)) {
                continue;
            }

            $foundCandidate = true;
            $bytes = $disk->get($mediaFileVersion->storage_path);

            if (
                strlen($bytes) === $mediaFileVersion->file_size_bytes
                && hash_equals(strtolower($mediaFileVersion->sha256), hash('sha256', $bytes))
            ) {
                return $bytes;
            }
        }

        if ($foundCandidate) {
            throw new DerivativeGenerationException('The private derivative preview failed integrity verification.');
        }

        abort(404);
    }
}
