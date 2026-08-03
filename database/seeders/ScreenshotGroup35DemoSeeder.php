<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class ScreenshotGroup35DemoSeeder extends Seeder
{
    public const SESSION_ID = '35000000-0000-4000-8000-000000000001';

    public function run(): void
    {
        abort_unless(app()->environment('local'), 403, 'The SG35 dataset is local-only.');

        $owner = User::query()->firstOrNew(['email' => 'sg35-owner@example.test']);
        $owner->forceFill([
            'name' => 'Avery Source Review',
            'username' => 'sg35.avery.source',
            'password' => Hash::make('SG35Demo!2026'),
            'email_verified_at' => now(),
            'role' => 'owner',
            'account_state' => 'approved',
            'family_connection' => 'Fictional source-boundary operator.',
        ])->save();

        $summary = [
            'supported_count' => 30000,
            'valid_count' => 29986,
            'invalid_count' => 14,
            'supported_bytes' => 139586437120,
            'ignored_count' => 82,
            'ignored_extensions' => ['mov' => 41, 'pdf' => 26, 'tmp' => 15],
            'extension_counts' => ['jpeg' => 4620, 'jpg' => 24418, 'png' => 814, 'tif' => 148],
            'duplicate_groups' => 413,
            'duplicate_files' => 467,
            'orientation_tagged_count' => 7842,
            'captured_at_count' => 22614,
            'estimated_derivative_bytes' => 62813896704,
            'estimated_working_bytes' => 20937965568,
            'estimated_total_bytes' => 223338299392,
            'estimate_formula' => 'originals + derivative reserve + working reserve',
            'paths_persisted' => false,
            'excluded_paths_persisted' => false,
            'excluded_subtree_count' => 1,
            'exclusion_policy_fingerprint' => hash('sha256', 'fictional-keyed-source-exclusion-policy'),
            'exclusion_enforcement' => 'pruned_before_discovery',
            'deep_scan' => true,
        ];

        DB::transaction(function () use ($owner, $summary): void {
            $existing = DB::table('cloud_import_sessions')->where('session_id', self::SESSION_ID)->first();
            if ($existing !== null) {
                DB::table('cloud_import_items')->where('cloud_import_session_id', $existing->id)->delete();
                DB::table('cloud_import_sessions')->where('id', $existing->id)->delete();
            }

            DB::table('cloud_import_sessions')->insert([
                'session_id' => self::SESSION_ID,
                'user_id' => $owner->id,
                'provider' => 'manual_export',
                'state' => 'preflight',
                'selected_count' => 30000,
                'imported_count' => 0,
                'failed_count' => 14,
                'total_bytes' => 139586437120,
                'processed_count' => 14,
                'checkpoint_position' => 14,
                'chunk_size' => 500,
                'inventory_sha256' => hash('sha256', 'fictional-sg35-source-safe-inventory'),
                'last_checkpoint_at' => now()->subMinutes(4),
                'source_manifest' => json_encode([
                    'source_label' => 'Fictional source-safe migration rehearsal',
                    'inventory_sha256' => hash('sha256', 'fictional-sg35-source-safe-inventory'),
                    'selected_count' => 30000,
                    'total_bytes' => 139586437120,
                    'paths_persisted' => false,
                    'approval_mode' => 'exception_first_batch_review',
                    'trust_level' => 'trusted_intake',
                    'evidence_scope' => 'synthetic-only',
                    'preflight_summary' => $summary,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now()->subMinutes(9),
                'updated_at' => now()->subMinutes(4),
            ]);
        });
    }
}
