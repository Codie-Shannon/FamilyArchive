<?php

namespace App\Http\Controllers\Intake;

use App\Domain\CloudImport\Services\TrustedBatchReview;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Processing\Models\PhotoSplitRegion;
use App\Http\Controllers\Controller;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

final class PhotoSplitPreviewController extends Controller
{
    public function __invoke(
        Request $request,
        string $sessionId,
        int $itemId,
        string $regionId,
        TrustedBatchReview $reviews,
    ): Response {
        $session = $reviews->session($sessionId, $request->user());
        abort_unless(DB::table('cloud_import_items')->where('id', $itemId)->where('cloud_import_session_id', data_get($session, 'id'))->exists(), 404);
        $region = PhotoSplitRegion::query()
            ->with('candidateVersion')
            ->where('region_id', $regionId)
            ->whereHas('proposal', fn ($query) => $query->where('cloud_import_item_id', $itemId))
            ->firstOrFail();
        $version = $region->candidateVersion;
        abort_unless($version instanceof MediaFileVersion, 404);
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($version->storage_disk);
        abort_unless($disk->exists($version->storage_path), 404);
        $bytes = $disk->get($version->storage_path);
        abort_unless(strlen($bytes) === $version->file_size_bytes && hash_equals($version->sha256, hash('sha256', $bytes)), 409, 'The split preview failed integrity verification.');

        return response($bytes, 200, [
            'Content-Type' => $version->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
