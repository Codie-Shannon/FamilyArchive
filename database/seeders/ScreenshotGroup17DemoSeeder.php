<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

final class ScreenshotGroup17DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('Screenshot Group 17 demo seeding is restricted to the local environment.');
        }

        $this->call(ScreenshotGroup16DemoSeeder::class);
    }
}
