<?php

namespace App\Domain\CloudImport\Services;

use App\Domain\Access\Models\ContributorSubmission;
use App\Domain\Access\Models\UploadSession;
use App\Domain\Intake\Models\IncomingUpload;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class BrowserUploadBatch
{
    public function open(User $user, UploadSession $session): void
    {
        DB::transaction(function () use ($user, $session): void {
            $batchKey = DB::table('cloud_import_sessions')->insertGetId([
                'session_id' => $session->session_id,
                'user_id' => $user->id,
                'provider' => 'manual_export',
                'state' => 'selecting',
                'selected_count' => $session->expected_files,
                'imported_count' => 0,
                'failed_count' => 0,
                'total_bytes' => 0,
                'processed_count' => 0,
                'checkpoint_position' => 0,
                'chunk_size' => 25,
                'review_state' => 'not_ready',
                'source_manifest' => json_encode([
                    'source_label' => $session->title,
                    'source_context' => $session->source_context,
                    'ingest_channel' => 'browser_upload',
                    'paths_persisted' => false,
                    'approval_mode' => 'delegated_batch_review',
                    'trust_level' => $user->canManageTrustedIntake() ? 'trusted_intake' : 'review_required',
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $session->forceFill(['cloud_import_session_id' => $batchKey])->save();
        });
    }

    public function retain(UploadSession $session, ContributorSubmission $submission, IncomingUpload $upload): void
    {
        $batchKey = $session->cloud_import_session_id;
        if (! is_int($batchKey)) {
            throw new RuntimeException('The browser upload session has no review batch.');
        }

        DB::transaction(function () use ($batchKey, $submission, $upload): void {
            $batch = DB::table('cloud_import_sessions')->where('id', $batchKey)->lockForUpdate()->first();
            if ($batch === null) {
                throw new RuntimeException('The browser upload review batch is unavailable.');
            }

            $position = (int) DB::table('cloud_import_items')
                ->where('cloud_import_session_id', $batchKey)
                ->max('position') + 1;
            DB::table('cloud_import_items')->insert([
                'cloud_import_session_id' => $batchKey,
                'position' => $position,
                'external_id' => 'browser:'.$upload->upload_id,
                'relative_path_hash' => hash('sha256', $upload->upload_id),
                'media_type' => 'photo',
                'original_name' => $upload->original_filename,
                'source_checksum' => $upload->sha256,
                'source_bytes' => $upload->file_size_bytes,
                'source_metadata' => json_encode([
                    'submission_id' => $submission->submission_id,
                    'upload_session_id' => $submission->upload_session_id,
                ], JSON_THROW_ON_ERROR),
                'state' => 'retained',
                'incoming_upload_id' => $upload->id,
                'attempt_count' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function checkpoint(UploadSession $session, bool $finish = false): UploadSession
    {
        return DB::transaction(function () use ($session, $finish): UploadSession {
            $locked = UploadSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $received = $locked->submissions()->count();
            if ($finish && $received === 0) {
                throw new RuntimeException('Retain at least one photo before finishing the batch.');
            }
            if ($finish) {
                $locked->expected_files = $received;
            }
            $complete = $received >= $locked->expected_files;
            $locked->received_files = $received;
            $locked->status = $complete ? 'complete' : 'paused';
            $locked->save();

            $batchKey = $locked->cloud_import_session_id;
            if (! is_int($batchKey)) {
                throw new RuntimeException('The browser upload session has no review batch.');
            }
            $totalBytes = (int) DB::table('cloud_import_items')
                ->where('cloud_import_session_id', $batchKey)
                ->sum('source_bytes');
            DB::table('cloud_import_sessions')->where('id', $batchKey)->update([
                'state' => $complete ? 'complete' : 'paused',
                'selected_count' => $locked->expected_files,
                'imported_count' => $received,
                'processed_count' => $received,
                'checkpoint_position' => $received,
                'total_bytes' => $totalBytes,
                'review_state' => $complete ? 'ready' : 'not_ready',
                'last_checkpoint_at' => now(),
                'updated_at' => now(),
            ]);

            return $locked->fresh();
        });
    }
}
