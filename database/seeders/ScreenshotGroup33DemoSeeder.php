<?php

namespace Database\Seeders;

use App\Domain\CloudImport\Models\MigrationQualificationRun;
use App\Domain\CloudImport\Services\ArchiveMigrationQualification;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class ScreenshotGroup33DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('Migration qualification evidence seeding is restricted to the local environment.');
        }

        $owner = User::query()->updateOrCreate(
            ['email' => 'sg33-owner@example.test'],
            [
                'name' => 'Morgan Family Archive Owner',
                'password' => Hash::make('SG33Demo!2026'),
                'email_verified_at' => now(),
                'role' => 'owner',
                'account_state' => 'approved',
            ],
        );

        MigrationQualificationRun::query()->delete();

        app(ArchiveMigrationQualification::class)->qualify($owner, 30000, 500, 12000);
    }
}
