<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Processing\Models\RestorationCandidate;
use App\Http\Controllers\Controller;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

final class RestorationCandidatePreviewController extends Controller
{
    public function __invoke(Request $request, RestorationCandidate $candidate, string $side): Response
    {
        abort_unless($request->user()?->isArchiveAdministrator(), 403);
        abort_unless(in_array($side, ['source', 'candidate'], true), 404);

        $version = $side === 'source' ? $candidate->sourceVersion : $candidate->candidateVersion;
        abort_unless($version instanceof MediaFileVersion, 404);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($version->storage_disk);
        abort_unless($disk->exists($version->storage_path), 404);
        $bytes = $disk->get($version->storage_path);
        abort_unless(
            strlen($bytes) === $version->file_size_bytes
            && hash_equals(strtolower($version->sha256), hash('sha256', $bytes)),
            409,
            'The preview failed integrity verification.',
        );

        return response($bytes, 200, [
            'Content-Type' => $version->mime_type,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
