<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Access\Services\ArchiveAccess;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Models\MediaFileVersion;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

final class OriginalMediaController extends Controller
{
    public function __invoke(Request $request, MediaFileVersion $mediaFileVersion, ArchiveAccess $access): Response
    {
        $mediaFileVersion->load('mediaItem');
        abort_unless(
            $mediaFileVersion->version_type === MediaFileVersionType::Original
            && $mediaFileVersion->generation_status === GenerationStatus::Ready
            && $mediaFileVersion->is_preferred
            && $mediaFileVersion->storage_disk === 'archive_originals'
            && $mediaFileVersion->mediaItem !== null
            && $access->canViewOriginal($request->user(), $mediaFileVersion->mediaItem),
            404
        );

        $disk = Storage::disk('archive_originals');
        abort_unless($disk->exists($mediaFileVersion->storage_path), 404);
        $bytes = $disk->get($mediaFileVersion->storage_path);
        abort_unless(
            strlen($bytes) === $mediaFileVersion->file_size_bytes
            && hash_equals(strtolower($mediaFileVersion->sha256), hash('sha256', $bytes)),
            409,
            'The original failed integrity verification.'
        );

        return response($bytes, 200, [
            'Content-Type' => $mediaFileVersion->mime_type,
            'Content-Disposition' => 'inline; filename="'.$mediaFileVersion->mediaItem->archive_id.'.'.($mediaFileVersion->extension ?? 'bin').'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
