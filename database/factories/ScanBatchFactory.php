<?php

namespace Database\Factories;

use App\Domain\Provenance\Models\ScanBatch;
use App\Domain\Provenance\Models\SourceCollection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ScanBatch> */
final class ScanBatchFactory extends Factory
{
    protected $model = ScanBatch::class;

    public function definition(): array
    {
        return [
            'scan_batch_id' => 'SCAN-'.Str::upper((string) Str::ulid()),
            'source_collection_id' => SourceCollection::factory(),
            'label' => 'Fictional album pages 1-8',
            'scanned_on' => '2026-07-24',
            'notes' => 'Synthetic scan batch for automated tests.',
            'created_by' => User::factory()->state([
                'role' => 'owner',
                'email_verified_at' => now(),
            ]),
        ];
    }
}
