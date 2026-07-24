<?php

namespace Database\Factories;

use App\Domain\Provenance\Enums\SourceCollectionType;
use App\Domain\Provenance\Models\SourceCollection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SourceCollection> */
final class SourceCollectionFactory extends Factory
{
    protected $model = SourceCollection::class;

    public function definition(): array
    {
        return [
            'source_id' => 'SRC-'.Str::upper((string) Str::ulid()),
            'type' => SourceCollectionType::PhysicalAlbum,
            'name' => 'Fictional Wairarapa Family Album',
            'description' => 'Synthetic source collection for automated tests.',
            'physical_reference' => 'Demo shelf A, album 1',
            'created_by' => User::factory()->state([
                'role' => 'owner',
                'email_verified_at' => now(),
            ]),
        ];
    }
}
