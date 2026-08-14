<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Archive\Services\ArchivePhotoEditingSource;
use App\Domain\Archive\Services\PhotoVisibilityManager;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Http\Controllers\Controller;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

final class ArchivePhotoEditorThumbnailController extends Controller
{
    public function __invoke(
        Request $request,
        MediaItem $mediaItem,
        ArchivePhotoEditingSource $sources,
        PhotoVisibilityManager $visibility,
    ): Response {
        abort_unless($visibility->canManage($request->user(), $mediaItem), 403);
        $source = $sources->resolve($mediaItem, $request->query('basis') === 'original' ? 'original' : 'current');
        $thumbnail = MediaFileVersion::query()
            ->where('media_item_id', $mediaItem->id)
            ->where('parent_version_id', $source->id)
            ->where('version_type', MediaFileVersionType::Thumbnail)
            ->where('generation_status', GenerationStatus::Ready)
            ->where('storage_disk', 'archive_derivatives')
            ->where('mime_type', 'image/webp')
            ->latest('id')
            ->first();
        $version = $thumbnail ?? $source;

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($version->storage_disk);
        abort_unless($disk->exists($version->storage_path), 404);
        $bytes = $disk->get($version->storage_path);
        abort_unless(strlen($bytes) === $version->file_size_bytes
            && hash_equals(strtolower($version->sha256), hash('sha256', $bytes)), 409);

        return response($bytes, 200, [
            'Content-Type' => $version->mime_type,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
