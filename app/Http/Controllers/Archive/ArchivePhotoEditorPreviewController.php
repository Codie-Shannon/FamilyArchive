<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Archive\Services\ArchivePhotoEditor;
use App\Domain\Media\Models\MediaItem;
use App\Http\Controllers\Controller;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

final class ArchivePhotoEditorPreviewController extends Controller
{
    public function __invoke(Request $request, MediaItem $mediaItem, ArchivePhotoEditor $editor): Response
    {
        abort_unless($request->user()->role === 'owner' || $mediaItem->created_by === $request->user()->id, 403);
        $source = $editor->source($mediaItem, $request->boolean('source_scan'));
        /** @var FilesystemAdapter $disk */ $disk = Storage::disk($source->storage_disk);
        abort_unless($disk->exists($source->storage_path), 404);
        $bytes = $disk->get($source->storage_path);
        abort_unless(strlen($bytes) === $source->file_size_bytes && hash_equals(strtolower($source->sha256), hash('sha256', $bytes)), 409);

        return response($bytes, 200, ['Content-Type' => $source->mime_type, 'Content-Disposition' => 'inline', 'Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff']);
    }
}
