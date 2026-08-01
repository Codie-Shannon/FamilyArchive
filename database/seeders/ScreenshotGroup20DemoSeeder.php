<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class ScreenshotGroup20DemoSeeder extends Seeder
{
    private const SESSION_ID = '20000000-0000-4000-8000-000000000001';

    public function run(): void
    {
        abort_unless(app()->environment('local'), 403, 'The SG20 dataset is local-only.');

        $owner = User::query()->firstOrNew(['email' => 'sg20-owner@example.test']);
        $owner->forceFill([
            'name' => 'Morgan Rimu',
            'password' => Hash::make('SG20Demo!2026'),
            'email_verified_at' => now(),
            'role' => 'owner',
            'account_state' => 'approved',
            'family_connection' => 'Fictional SG20 evidence identity',
        ])->save();

        DB::transaction(function () use ($owner): void {
            DB::table('cloud_import_sessions')->updateOrInsert(
                ['session_id' => self::SESSION_ID],
                [
                    'cloud_import_connection_id' => null,
                    'user_id' => $owner->id,
                    'provider' => 'manual_export',
                    'state' => 'paused',
                    'selected_count' => 30_000,
                    'imported_count' => 12_496,
                    'failed_count' => 4,
                    'total_bytes' => 187_648_000_000,
                    'processed_count' => 12_500,
                    'checkpoint_position' => 12_500,
                    'chunk_size' => 500,
                    'inventory_sha256' => hash('sha256', 'fictional-sg20-thirty-thousand-photo-inventory'),
                    'last_checkpoint_at' => now()->subMinutes(3),
                    'source_manifest' => json_encode([
                        'source_label' => 'Fictional 30,000-photo archive rehearsal',
                        'privacy_scope' => 'aggregate-and-fictional-only',
                        'inventory_version' => 1,
                    ], JSON_THROW_ON_ERROR),
                    'created_at' => now()->subHours(2),
                    'updated_at' => now()->subMinutes(3),
                ],
            );

            $sessionId = DB::table('cloud_import_sessions')->where('session_id', self::SESSION_ID)->value('id');
            DB::table('cloud_import_items')->where('cloud_import_session_id', $sessionId)->delete();

            $items = [
                [12_493, 'fictional-box-025-photo-493.jpg', 'retained', 1, null],
                [12_494, 'fictional-box-025-photo-494.jpg', 'retained', 1, null],
                [12_495, 'fictional-box-025-photo-495.jpg', 'retained', 1, null],
                [12_496, 'fictional-box-025-photo-496.jpg', 'retained', 1, null],
                [12_497, 'fictional-box-025-photo-497.jpg', 'failed', 1, 'unreadable_source'],
                [12_498, 'fictional-box-025-photo-498.jpg', 'failed', 2, 'checksum_mismatch'],
                [12_499, 'fictional-box-025-photo-499.tif', 'failed', 1, 'unsupported_encoding'],
                [12_500, 'fictional-box-025-photo-500.jpg', 'failed', 1, 'retention_write_failed'],
                [12_501, 'fictional-box-026-photo-001.jpg', 'selected', 0, null],
                [12_502, 'fictional-box-026-photo-002.jpg', 'selected', 0, null],
            ];

            foreach ($items as [$position, $name, $state, $attempts, $failure]) {
                DB::table('cloud_import_items')->insert([
                    'cloud_import_session_id' => $sessionId,
                    'position' => $position,
                    'external_id' => 'sg20-fictional-'.$position,
                    'relative_path_hash' => hash('sha256', 'fictional-relative-'.$position),
                    'media_type' => 'photo',
                    'original_name' => $name,
                    'source_checksum' => hash('sha256', 'fictional-photo-'.$position),
                    'source_bytes' => 6_254_933 + $position,
                    'source_metadata' => json_encode(['evidence_scope' => 'fictional-only'], JSON_THROW_ON_ERROR),
                    'state' => $state,
                    'attempt_count' => $attempts,
                    'failure_code' => $failure,
                    'incoming_upload_id' => null,
                    'created_at' => now()->subMinutes(15),
                    'updated_at' => now()->subMinutes(max(1, 12_503 - $position)),
                ]);
            }
        });
    }
}
