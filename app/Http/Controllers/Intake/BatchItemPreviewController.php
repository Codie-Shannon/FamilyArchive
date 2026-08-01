<?php

namespace App\Http\Controllers\Intake;

use App\Domain\CloudImport\Services\TrustedBatchReview;
use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Processing\Models\RestorationCandidate;
use App\Http\Controllers\Controller;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class BatchItemPreviewController extends Controller
{
    public function __invoke(
        Request $request,
        string $sessionId,
        int $itemId,
        string $side,
        TrustedBatchReview $reviews,
    ): Response {
        $session = $reviews->session($sessionId, $request->user());
        abort_unless(in_array($side, ['original', 'suggested'], true), 404);
        $item = DB::table('cloud_import_items')
            ->where('id', $itemId)
            ->where('cloud_import_session_id', data_get($session, 'id'))
            ->first();
        abort_unless($item !== null, 404);

        if ($side === 'original') {
            $upload = IncomingUpload::query()->whereKey(data_get($item, 'incoming_upload_id'))->first();
            abort_unless($upload instanceof IncomingUpload, 404);
            abort_unless($upload->source_file_retained && is_string($upload->incoming_path), 404);
            $diskName = 'archive_quarantine';
            $path = $upload->incoming_path;
            $mime = $upload->mime_type;
            $sha256 = (string) $upload->sha256;
            $expectedBytes = $upload->file_size_bytes;
        } else {
            $candidate = RestorationCandidate::query()->whereKey(data_get($item, 'restoration_candidate_id'))->first();
            abort_unless($candidate instanceof RestorationCandidate, 404);
            $version = $candidate->candidateVersion;
            abort_unless($version instanceof MediaFileVersion, 404);
            $diskName = $version->storage_disk;
            $path = $version->storage_path;
            $mime = $version->mime_type;
            $sha256 = $version->sha256;
            $expectedBytes = $version->file_size_bytes;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($diskName);
        abort_unless($disk->exists($path), 404);
        $bytes = $disk->get($path);
        abort_unless(
            strlen($bytes) === $expectedBytes && hash_equals(strtolower($sha256), hash('sha256', $bytes)),
            409,
            'The preview failed integrity verification.',
        );

        return response($bytes, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
