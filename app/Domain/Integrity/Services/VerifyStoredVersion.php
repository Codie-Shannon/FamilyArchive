<?php

namespace App\Domain\Integrity\Services;

use App\Domain\Media\Models\MediaFileVersion;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class VerifyStoredVersion
{
    public function check(MediaFileVersion $version, Filesystem $disk): string
    {
        $result = 'verified';
        $observed = [];

        try {
            if (! $disk->exists($version->storage_path)) {
                $result = 'missing';
            } else {
                $bytes = $disk->get($version->storage_path);
                $observed = [
                    'bytes' => strlen($bytes),
                    'sha256' => hash('sha256', $bytes),
                ];

                if (strlen($bytes) !== $version->file_size_bytes) {
                    $result = 'size_mismatch';
                } elseif (! hash_equals(strtolower($version->sha256), $observed['sha256'])) {
                    $result = 'hash_mismatch';
                }
            }
        } catch (\Throwable $exception) {
            $result = 'provider_error';
            $observed = ['exception' => $exception::class];
        }

        DB::table('integrity_checks')->insert([
            'check_id' => (string) Str::uuid(),
            'media_file_version_id' => $version->id,
            'result' => $result,
            'observed' => json_encode($observed, JSON_THROW_ON_ERROR),
            'checked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $result;
    }
}
