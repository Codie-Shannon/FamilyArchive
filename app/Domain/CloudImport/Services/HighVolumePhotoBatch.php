<?php

namespace App\Domain\CloudImport\Services;

use App\Domain\Access\Models\ContributorSubmission;
use App\Domain\CloudImport\ValueObjects\BatchSafetyPolicy;
use App\Domain\CloudImport\ValueObjects\SourceExclusionBoundary;
use App\Domain\Intake\Services\CreateAndRetainIncomingPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class HighVolumePhotoBatch
{
    public function __construct(
        private CreateAndRetainIncomingPhoto $retention,
        private PhotoBatchPreflight $preflight,
    ) {}

    /**
     * @param  list<string>  $excludedDirectories
     * @return array{session_id:string,selected_count:int,total_bytes:int,inventory_sha256:string}
     */
    public function plan(User $owner, string $directory, int $chunkSize = 500, array $excludedDirectories = []): array
    {
        if (! $owner->canManageTrustedIntake()) {
            throw new RuntimeException('A trusted intake account is required to plan a high-volume batch.');
        }
        if ($chunkSize < 25 || $chunkSize > 1000) {
            throw new RuntimeException('The checkpoint chunk size must be between 25 and 1000.');
        }

        $inventory = $this->preflight->scan($directory, true, $excludedDirectories);
        if ($inventory['files'] === [] || count($inventory['files']) > 100000) {
            throw new RuntimeException('The batch must contain between 1 and 100,000 supported photos.');
        }

        return DB::transaction(function () use ($owner, $directory, $chunkSize, $inventory): array {
            $sessionId = (string) Str::uuid();
            $invalidCount = (int) $inventory['summary']['invalid_count'];
            $sessionKey = DB::table('cloud_import_sessions')->insertGetId([
                'session_id' => $sessionId,
                'user_id' => $owner->id,
                'provider' => 'manual_export',
                'state' => 'preflight',
                'selected_count' => count($inventory['files']),
                'imported_count' => 0,
                'failed_count' => $invalidCount,
                'total_bytes' => $inventory['summary']['supported_bytes'],
                'processed_count' => $invalidCount,
                'checkpoint_position' => $invalidCount,
                'chunk_size' => $chunkSize,
                'inventory_sha256' => $inventory['inventory_sha256'],
                'source_manifest' => json_encode([
                    'source_label' => basename(rtrim(str_replace('\\', '/', $directory), '/')),
                    'inventory_sha256' => $inventory['inventory_sha256'],
                    'selected_count' => count($inventory['files']),
                    'total_bytes' => $inventory['summary']['supported_bytes'],
                    'paths_persisted' => false,
                    'approval_mode' => 'exception_first_batch_review',
                    'trust_level' => 'trusted_intake',
                    'preflight_summary' => $inventory['summary'],
                    'content_safety' => BatchSafetyPolicy::defaults()->toArray(),
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (array_chunk($inventory['files'], 500) as $chunk) {
                DB::table('cloud_import_items')->insert(array_map(fn (array $file): array => [
                    'cloud_import_session_id' => $sessionKey,
                    'position' => $file['position'],
                    'external_id' => 'local:'.$file['relative_path_hash'],
                    'relative_path_hash' => $file['relative_path_hash'],
                    'media_type' => 'photo',
                    'original_name' => $file['name'],
                    'source_checksum' => $file['content_sha256'],
                    'source_bytes' => $file['bytes'],
                    'source_metadata' => json_encode([
                        'extension' => $file['extension'],
                        'mime' => $file['mime'],
                        'width' => $file['width'],
                        'height' => $file['height'],
                        'orientation' => $file['orientation'],
                        'captured_at' => $file['captured_at'],
                    ], JSON_THROW_ON_ERROR),
                    'state' => $file['valid'] ? 'selected' : 'failed',
                    'attempt_count' => 0,
                    'failure_code' => $file['failure_code'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $chunk));
            }

            return [
                'session_id' => $sessionId,
                'selected_count' => count($inventory['files']),
                'total_bytes' => (int) $inventory['summary']['supported_bytes'],
                'inventory_sha256' => $inventory['inventory_sha256'],
            ];
        });
    }

    /**
     * @param  list<string>  $excludedDirectories
     * @return array{state:string,processed_count:int,retained_count:int,failed_count:int,remaining_count:int}
     */
    public function process(string $sessionId, string $directory, ?int $limit = null, array $excludedDirectories = []): array
    {
        $session = DB::table('cloud_import_sessions')->where('session_id', $sessionId)->where('provider', 'manual_export')->first();
        if ($session === null) {
            throw new RuntimeException('The high-volume batch could not be found.');
        }

        $manifest = json_decode((string) $session->source_manifest, true) ?: [];
        $plannedPolicy = $manifest['preflight_summary']['exclusion_policy_fingerprint'] ?? null;
        $currentPolicy = SourceExclusionBoundary::forRoot($directory, $excludedDirectories)->fingerprint();
        if (! is_string($plannedPolicy) || ! hash_equals($plannedPolicy, $currentPolicy)) {
            throw new RuntimeException('The source exclusion policy changed after planning; processing stopped before retention.');
        }
        $inventory = $this->preflight->scan($directory, false, $excludedDirectories);
        if (! hash_equals((string) $session->inventory_sha256, $inventory['inventory_sha256'])) {
            throw new RuntimeException('The source inventory changed after planning; processing stopped before retention.');
        }

        $paths = [];
        foreach ($inventory['files'] as $file) {
            $paths[$file['relative_path_hash']] = $file['path'];
        }

        $this->checkpoint((int) $session->id);
        $items = DB::table('cloud_import_items')->where('cloud_import_session_id', $session->id)->where('state', 'selected')
            ->orderBy('position')->limit(max(1, min($limit ?? (int) $session->chunk_size, 1000)))->get();
        $owner = User::query()->whereKey($session->user_id)->firstOrFail();
        DB::table('cloud_import_sessions')->where('id', $session->id)->update(['state' => 'importing', 'updated_at' => now()]);

        foreach ($items as $item) {
            try {
                $path = $paths[$item->relative_path_hash] ?? null;
                if (! is_string($path) || ! is_file($path)) {
                    throw new RuntimeException('The inventoried source file is unavailable.');
                }
                $checksum = hash_file('sha256', $path);
                if (! is_string($checksum) || ! is_string($item->source_checksum) || ! hash_equals($item->source_checksum, $checksum)) {
                    throw new RuntimeException('The source checksum no longer matches the approved preflight inventory.');
                }

                $upload = $this->retention->create($owner, new UploadedFile($path, (string) $item->original_name, mime_content_type($path) ?: null, UPLOAD_ERR_OK, true));
                ContributorSubmission::query()->create([
                    'submission_id' => 'SUB-'.Str::upper(Str::random(12)),
                    'user_id' => $owner->id,
                    'incoming_upload_id' => $upload->id,
                    'status' => 'retained',
                    'original_name' => $upload->original_filename,
                    'source_context' => 'High-volume batch '.$sessionId,
                    'proposed_metadata' => ['batch_session_id' => $sessionId],
                    'automation_preferences' => [
                        'automation_mode' => 'candidates',
                        'crop_target' => 'photo_edge',
                        'auto_rotate' => true,
                        'deskew' => true,
                    ],
                ]);
                DB::table('cloud_import_items')->where('id', $item->id)->update([
                    'incoming_upload_id' => $upload->id,
                    'state' => 'retained',
                    'attempt_count' => (int) $item->attempt_count + 1,
                    'failure_code' => null,
                    'updated_at' => now(),
                ]);
            } catch (Throwable $exception) {
                report($exception);
                DB::table('cloud_import_items')->where('id', $item->id)->update([
                    'state' => 'failed',
                    'attempt_count' => (int) $item->attempt_count + 1,
                    'failure_code' => $this->failureCode($exception),
                    'updated_at' => now(),
                ]);
            }
        }

        return $this->checkpoint((int) $session->id);
    }

    public function retryFailed(string $sessionId, int $maximum = 100): int
    {
        $session = DB::table('cloud_import_sessions')->where('session_id', $sessionId)->where('provider', 'manual_export')->first();
        if ($session === null) {
            throw new RuntimeException('The high-volume batch could not be found.');
        }

        $ids = DB::table('cloud_import_items')->where('cloud_import_session_id', $session->id)
            ->where('state', 'failed')
            ->whereIn('failure_code', ['retention_failed', 'unreadable_source'])
            ->where('attempt_count', '<', 3)
            ->orderBy('position')
            ->limit(max(1, min($maximum, 1000)))
            ->pluck('id');
        if ($ids->isEmpty()) {
            return 0;
        }

        DB::table('cloud_import_items')->whereIn('id', $ids)->update(['state' => 'selected', 'failure_code' => null, 'updated_at' => now()]);
        $this->checkpoint((int) $session->id);

        return $ids->count();
    }

    /**
     * @param  list<string>  $excludedDirectories
     * @return array{state:string,processed_count:int,retained_count:int,failed_count:int,remaining_count:int}
     */
    public function runToCompletion(string $sessionId, string $directory, array $excludedDirectories = []): array
    {
        $maximumChunks = max(1, (int) config('archive.batch_preflight.maximum_unattended_chunks', 1000));
        $result = ['state' => 'paused', 'processed_count' => 0, 'retained_count' => 0, 'failed_count' => 0, 'remaining_count' => 1];
        for ($chunk = 0; $chunk < $maximumChunks && $result['remaining_count'] > 0; $chunk++) {
            $result = $this->process($sessionId, $directory, null, $excludedDirectories);
        }
        if ($result['remaining_count'] > 0) {
            throw new RuntimeException('The unattended chunk safety limit was reached before completion.');
        }

        return $result;
    }

    /** @return array{state:string,processed_count:int,retained_count:int,failed_count:int,remaining_count:int} */
    private function checkpoint(int $sessionKey): array
    {
        $counts = DB::table('cloud_import_items')->where('cloud_import_session_id', $sessionKey)
            ->selectRaw("SUM(CASE WHEN state = 'retained' THEN 1 ELSE 0 END) AS retained_count")
            ->selectRaw("SUM(CASE WHEN state = 'failed' THEN 1 ELSE 0 END) AS failed_count")
            ->selectRaw("SUM(CASE WHEN state = 'selected' THEN 1 ELSE 0 END) AS remaining_count")->first();
        $retained = (int) ($counts->retained_count ?? 0);
        $failed = (int) ($counts->failed_count ?? 0);
        $remaining = (int) ($counts->remaining_count ?? 0);
        $state = $remaining === 0 ? ($failed > 0 ? 'failed' : 'complete') : 'paused';
        DB::table('cloud_import_sessions')->where('id', $sessionKey)->update([
            'state' => $state,
            'imported_count' => $retained,
            'failed_count' => $failed,
            'processed_count' => $retained + $failed,
            'checkpoint_position' => $retained + $failed,
            'last_checkpoint_at' => now(),
            'updated_at' => now(),
        ]);

        return ['state' => $state, 'processed_count' => $retained + $failed, 'retained_count' => $retained, 'failed_count' => $failed, 'remaining_count' => $remaining];
    }

    private function failureCode(Throwable $exception): string
    {
        if (str_contains($exception->getMessage(), 'checksum')) {
            return 'checksum_mismatch';
        }

        return str_contains($exception->getMessage(), 'unavailable') ? 'unreadable_source' : 'retention_failed';
    }
}
