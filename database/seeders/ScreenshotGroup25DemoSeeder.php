<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class ScreenshotGroup25DemoSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('local'), 403, 'The SG25 dataset is local-only.');

        $this->call(ScreenshotGroup24DemoSeeder::class);

        $owner = $this->user('sg25-owner@example.test', 'Amelia Hart', 'owner');
        $admin = $this->user('sg25-admin@example.test', 'Lucas Ngata', 'admin');
        $trusted = $this->user('sg25-trusted@example.test', 'Mereana Cole', 'trusted_contributor');
        $this->user('sg25-member@example.test', 'Oliver Reed', 'viewer');

        $this->batch('25000000-0000-4000-8000-000000000001', $trusted, 18, 2);
        $this->batch('25000000-0000-4000-8000-000000000002', $admin, 34, 4);
        $this->batch('25000000-0000-4000-8000-000000000003', $owner, 9, 1);
    }

    private function user(string $email, string $name, string $role): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'password' => Hash::make('SG25Demo!2026'),
            'email_verified_at' => now(),
            'role' => $role,
            'account_state' => 'approved',
            'family_connection' => 'Fictional SG25 evidence identity.',
        ])->save();

        return $user;
    }

    private function batch(string $sessionId, User $user, int $count, int $attention): void
    {
        DB::table('cloud_import_sessions')->updateOrInsert(
            ['session_id' => $sessionId],
            [
                'cloud_import_connection_id' => null,
                'user_id' => $user->id,
                'provider' => 'manual_export',
                'state' => 'paused',
                'selected_count' => $count,
                'imported_count' => $count,
                'failed_count' => 0,
                'total_bytes' => $count * 2500000,
                'processed_count' => $count,
                'checkpoint_position' => $count,
                'chunk_size' => 500,
                'inventory_sha256' => hash('sha256', $sessionId),
                'last_checkpoint_at' => now(),
                'review_state' => 'ready',
                'reviewed_count' => $count - $attention,
                'attention_count' => $attention,
                'source_manifest' => json_encode(['kind' => 'fictional-sg25-batch']),
                'created_at' => now()->subMinutes($count),
                'updated_at' => now(),
            ],
        );
    }
}
