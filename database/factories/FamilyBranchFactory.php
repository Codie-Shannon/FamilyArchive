<?php

namespace Database\Factories;

use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Knowledge\Models\FamilyBranch;
use App\Domain\Media\Enums\StructuredDateConfidence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<FamilyBranch> */
final class FamilyBranchFactory extends Factory
{
    protected $model = FamilyBranch::class;

    public function definition(): array
    {
        return [
            'branch_id' => 'BRN-'.Str::upper((string) Str::ulid()),
            'name' => 'Fictional Harbour Branch',
            'description' => 'Synthetic Group 14 family branch.',
            'is_sensitive' => false,
            'review_state' => KnowledgeReviewState::Accepted,
            'confidence' => StructuredDateConfidence::High,
            'source_note' => 'Supported by a fictional family register.',
            'review_reason' => 'Accept fictional branch for automated testing.',
            'created_by' => User::factory()->state([
                'role' => 'owner',
                'email_verified_at' => now(),
            ]),
            'reviewed_by' => fn (array $attributes) => $attributes['created_by'],
            'reviewed_at' => now(),
            'metadata_revision' => 1,
        ];
    }
}
