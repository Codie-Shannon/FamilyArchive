<?php

namespace App\Domain\CloudImport\Services;

use App\Domain\Access\Models\ContributorSubmission;
use App\Domain\Intake\Services\CreateAndRetainIncomingPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class HighVolumePhotoBatch
{
    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'tif', 'tiff'];

    public function __construct(private CreateAndRetainIncomingPhoto $retention) {}

    /** @return array{session_id:string,selected_count:int,total_bytes:int,inventory_sha256:string} */
    public function plan(User $owner, string $directory, int $chunkSize = 500): array
    {
        if (! $owner->canManageTrustedIntake()) {
            throw new RuntimeException('A trusted intake account is required to plan a high-volume batch.');
        }
        if ($chunkSize < 25 || $chunkSize > 1000) {
            throw new RuntimeException('The checkpoint chunk size must be between 25 and 1000.');
        }
        $inventory = $this->inventory($directory);
        if ($inventory['files'] === [] || count($inventory['files']) > 100000) {
            throw new RuntimeException('The batch must contain between 1 and 100,000 supported photos.');
        }

        return DB::transaction(function () use ($owner, $directory, $chunkSize, $inventory): array {
            $sessionId = (string) Str::uuid();
            $sessionKey = DB::table('cloud_import_sessions')->insertGetId([
                'session_id' => $sessionId, 'user_id' => $owner->id, 'provider' => 'manual_export', 'state' => 'preflight',
                'selected_count' => count($inventory['files']), 'imported_count' => 0, 'failed_count' => 0,
                'total_bytes' => $inventory['total_bytes'], 'processed_count' => 0, 'checkpoint_position' => 0,
                'chunk_size' => $chunkSize, 'inventory_sha256' => $inventory['sha256'],
                'source_manifest' => json_encode([
                    'source_label' => basename(rtrim(str_replace('\\', '/', $directory), '/')),
                    'inventory_sha256' => $inventory['sha256'], 'selected_count' => count($inventory['files']),
                    'total_bytes' => $inventory['total_bytes'], 'paths_persisted' => false,
                    'approval_mode' => 'exception_first_batch_review', 'trust_level' => 'trusted_intake',
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach (array_chunk($inventory['files'], 500) as $chunk) {
                DB::table('cloud_import_items')->insert(array_map(fn (array $file): array => [
                    'cloud_import_session_id' => $sessionKey, 'position' => $file['position'],
                    'external_id' => 'local:'.$file['relative_path_hash'], 'relative_path_hash' => $file['relative_path_hash'],
                    'media_type' => 'photo', 'original_name' => $file['name'], 'source_bytes' => $file['bytes'],
                    'source_metadata' => json_encode(['extension' => $file['extension']], JSON_THROW_ON_ERROR),
                    'state' => 'selected', 'attempt_count' => 0, 'created_at' => now(), 'updated_at' => now(),
                ], $chunk));
            }

            return ['session_id' => $sessionId, 'selected_count' => count($inventory['files']), 'total_bytes' => $inventory['total_bytes'], 'inventory_sha256' => $inventory['sha256']];
        });
    }

    /** @return array{state:string,processed_count:int,retained_count:int,failed_count:int,remaining_count:int} */
    public function process(string $sessionId, string $directory, ?int $limit = null): array
    {
        $session = DB::table('cloud_import_sessions')->where('session_id', $sessionId)->where('provider', 'manual_export')->first();
        if ($session === null) {
            throw new RuntimeException('The high-volume batch could not be found.');
        }
        $inventory = $this->inventory($directory);
        if (! hash_equals((string) $session->inventory_sha256, $inventory['sha256'])) {
            throw new RuntimeException('The source inventory changed after planning; processing stopped before retention.');
        }
        $paths = [];
        foreach ($inventory['files'] as $file) {
            $paths[$file['relative_path_hash']] = $file['path'];
        }
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
                $upload = $this->retention->create($owner, new UploadedFile($path, (string) $item->original_name, mime_content_type($path) ?: null, UPLOAD_ERR_OK, true));
                ContributorSubmission::query()->create([
                    'submission_id' => 'SUB-'.Str::upper(Str::random(12)), 'user_id' => $owner->id,
                    'incoming_upload_id' => $upload->id, 'status' => 'retained', 'original_name' => $upload->original_filename,
                    'source_context' => 'High-volume batch '.$sessionId, 'proposed_metadata' => ['batch_session_id' => $sessionId],
                    'automation_preferences' => [
                        'automation_mode' => 'candidates',
                        'crop_target' => 'photo_edge',
                        'auto_rotate' => true,
                        'deskew' => true,
                    ],
                ]);
                DB::table('cloud_import_items')->where('id', $item->id)->update([
                    'incoming_upload_id' => $upload->id, 'state' => 'retained', 'attempt_count' => (int) $item->attempt_count + 1,
                    'failure_code' => null, 'updated_at' => now(),
                ]);
            } catch (Throwable $exception) {
                report($exception);
                DB::table('cloud_import_items')->where('id', $item->id)->update([
                    'state' => 'failed', 'attempt_count' => (int) $item->attempt_count + 1,
                    'failure_code' => 'retention_failed', 'updated_at' => now(),
                ]);
            }
        }

        return $this->checkpoint((int) $session->id);
    }

    /** @return array{files:list<array{position:int,path:string,relative_path_hash:string,name:string,extension:string,bytes:int}>,total_bytes:int,sha256:string} */
    private function inventory(string $directory): array
    {
        $root = realpath($directory);
        if (! is_string($root) || ! is_dir($root)) {
            throw new RuntimeException('The batch source directory does not exist.');
        }
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->isLink() || ! in_array(strtolower($file->getExtension()), self::EXTENSIONS, true)) {
                continue;
            }
            $path = str_replace('\\', '/', $file->getRealPath());
            if (! str_starts_with($path, $root.'/')) {
                throw new RuntimeException('A batch file escaped the selected directory boundary.');
            }
            $relative = substr($path, strlen($root) + 1);
            $files[] = ['path' => $path, 'relative' => $relative, 'relative_path_hash' => hash('sha256', $relative),
                'name' => basename($relative), 'extension' => strtolower($file->getExtension()), 'bytes' => $file->getSize()];
        }
        usort($files, fn (array $left, array $right): int => strcmp($left['relative'], $right['relative']));
        $manifest = hash_init('sha256');
        $totalBytes = 0;
        foreach ($files as $position => &$file) {
            $file['position'] = $position + 1;
            $totalBytes += $file['bytes'];
            hash_update($manifest, $file['relative_path_hash'].':'.$file['bytes']."\n");
            unset($file['relative']);
        }
        unset($file);

        return ['files' => $files, 'total_bytes' => $totalBytes, 'sha256' => hash_final($manifest)];
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
            'state' => $state, 'imported_count' => $retained, 'failed_count' => $failed,
            'processed_count' => $retained + $failed, 'checkpoint_position' => $retained + $failed,
            'last_checkpoint_at' => now(), 'updated_at' => now(),
        ]);

        return ['state' => $state, 'processed_count' => $retained + $failed, 'retained_count' => $retained, 'failed_count' => $failed, 'remaining_count' => $remaining];
    }
}
