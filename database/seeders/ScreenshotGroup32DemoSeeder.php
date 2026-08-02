<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class ScreenshotGroup32DemoSeeder extends Seeder
{
    public const SESSION_ID = '32000000-0000-4000-8000-000000000001';

    public function run(): void
    {
        abort_unless(app()->environment('local'), 403, 'The SG32 dataset is local-only.');

        $owner = User::query()->firstOrNew(['email' => 'sg32-owner@example.test']);
        $owner->forceFill([
            'name' => 'Morgan Kauri',
            'username' => 'sg32.morgan.kauri',
            'password' => Hash::make('SG32Demo!2026'),
            'email_verified_at' => now(),
            'role' => 'owner',
            'account_state' => 'approved',
            'family_connection' => 'Fictional migration operator.',
        ])->save();

        $summary = [
            'supported_count' => 30000,
            'valid_count' => 29986,
            'invalid_count' => 14,
            'supported_bytes' => 187648000000,
            'ignored_count' => 82,
            'ignored_extensions' => ['mov' => 41, 'pdf' => 26, 'tmp' => 15],
            'extension_counts' => ['jpeg' => 4620, 'jpg' => 24418, 'png' => 814, 'tif' => 148],
            'duplicate_groups' => 413,
            'duplicate_files' => 467,
            'orientation_tagged_count' => 7842,
            'captured_at_count' => 22614,
            'estimated_derivative_bytes' => 84441600000,
            'estimated_working_bytes' => 28147200000,
            'estimated_total_bytes' => 300236800000,
            'estimate_formula' => 'originals + derivative reserve + working reserve',
            'paths_persisted' => false,
            'deep_scan' => true,
        ];

        DB::transaction(function () use ($owner, $summary): void {
            $existing = DB::table('cloud_import_sessions')->where('session_id', self::SESSION_ID)->first();
            if ($existing !== null) {
                DB::table('cloud_import_items')->where('cloud_import_session_id', $existing->id)->delete();
                DB::table('cloud_import_sessions')->where('id', $existing->id)->delete();
            }

            $sessionKey = DB::table('cloud_import_sessions')->insertGetId([
                'session_id' => self::SESSION_ID,
                'user_id' => $owner->id,
                'provider' => 'manual_export',
                'state' => 'preflight',
                'selected_count' => 30000,
                'imported_count' => 0,
                'failed_count' => 14,
                'total_bytes' => 187648000000,
                'processed_count' => 14,
                'checkpoint_position' => 0,
                'chunk_size' => 500,
                'inventory_sha256' => hash('sha256', 'fictional-sg32-30000-photo-inventory'),
                'last_checkpoint_at' => now()->subMinutes(8),
                'source_manifest' => json_encode([
                    'source_label' => 'Fictional 30,000-photo migration preflight',
                    'inventory_sha256' => hash('sha256', 'fictional-sg32-30000-photo-inventory'),
                    'selected_count' => 30000,
                    'total_bytes' => 187648000000,
                    'paths_persisted' => false,
                    'approval_mode' => 'exception_first_batch_review',
                    'trust_level' => 'trusted_intake',
                    'evidence_scope' => 'synthetic-only',
                    'preflight_summary' => $summary,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now()->subMinutes(12),
                'updated_at' => now()->subMinutes(8),
            ]);

            $exceptions = [
                ['family-album-scan-00418.jpg', 'unreadable_image'],
                ['shoebox-negative-01107.tif', 'unreadable_image'],
                ['reunion-envelope-08312.jpeg', 'unreadable_image'],
                ['anniversary-print-12644.jpg', 'unreadable_image'],
                ['album-page-17402.jpg', 'unreadable_image'],
                ['postcard-back-22106.png', 'unreadable_image'],
                ['portrait-scan-28754.jpg', 'unreadable_image'],
                ['camera-export-29991.jpg', 'unreadable_image'],
            ];

            foreach ($exceptions as $offset => [$name, $failure]) {
                DB::table('cloud_import_items')->insert([
                    'cloud_import_session_id' => $sessionKey,
                    'position' => (($offset + 1) * 3173) % 30000,
                    'external_id' => 'local:'.hash('sha256', 'sg32-'.$name),
                    'relative_path_hash' => hash('sha256', 'fictional/'.$name),
                    'media_type' => 'photo',
                    'original_name' => $name,
                    'source_checksum' => hash('sha256', 'synthetic-content-'.$name),
                    'source_bytes' => 4200000 + ($offset * 230000),
                    'source_metadata' => json_encode(['extension' => pathinfo($name, PATHINFO_EXTENSION)], JSON_THROW_ON_ERROR),
                    'state' => 'failed',
                    'attempt_count' => 0,
                    'failure_code' => $failure,
                    'created_at' => now()->subMinutes(10 - $offset),
                    'updated_at' => now()->subMinutes(10 - $offset),
                ]);
            }
        });
    }
}
