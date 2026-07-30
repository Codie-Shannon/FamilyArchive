<?php

namespace App\Domain\Knowledge\Actions;

use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Knowledge\Models\FamilyBranch;
use App\Domain\Knowledge\Models\FamilyBranchRevision;
use App\Domain\Media\Enums\StructuredDateConfidence;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ReviewFamilyBranch
{
    /** @param array<string, mixed> $input */
    public function create(array $input, User $actor): FamilyBranch
    {
        $values = $this->normalize($input);

        return DB::transaction(function () use ($values, $actor): FamilyBranch {
            $branch = FamilyBranch::query()->create([
                ...$values,
                'branch_id' => 'BRN-'.Str::upper((string) Str::ulid()),
                'created_by' => $actor->id,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'metadata_revision' => 1,
            ]);

            FamilyBranchRevision::createImmutable([
                'family_branch_id' => $branch->id,
                'revision_number' => 1,
                'actor_user_id' => $actor->id,
                'from_revision' => 0,
                'to_revision' => 1,
                'changed_fields' => array_keys($values),
                'before_values' => [],
                'after_values' => $this->snapshot($branch),
                'change_reason' => $values['review_reason'],
                'created_at' => now(),
            ]);

            return $branch->fresh();
        });
    }

    /** @param array<string, mixed> $input */
    public function update(
        FamilyBranch $branch,
        array $input,
        int $expectedRevision,
        User $actor
    ): FamilyBranch {
        $values = $this->normalize($input);

        return DB::transaction(function () use ($branch, $values, $expectedRevision, $actor): FamilyBranch {
            $locked = FamilyBranch::query()->lockForUpdate()->findOrFail($branch->id);

            if ($locked->metadata_revision !== $expectedRevision) {
                throw ValidationException::withMessages([
                    'expected_metadata_revision' => 'The family branch changed after this form was opened.',
                ]);
            }

            $before = $this->snapshot($locked);
            $changed = [];

            foreach ($values as $field => $value) {
                if ($before[$field] !== $value) {
                    $changed[$field] = $value;
                }
            }

            if ($changed === []) {
                throw ValidationException::withMessages([
                    'review_reason' => 'No reviewed family branch values changed.',
                ]);
            }

            $toRevision = $locked->metadata_revision + 1;
            $locked->forceFill([
                ...$changed,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'metadata_revision' => $toRevision,
            ])->save();

            FamilyBranchRevision::createImmutable([
                'family_branch_id' => $locked->id,
                'revision_number' => $toRevision,
                'actor_user_id' => $actor->id,
                'from_revision' => $expectedRevision,
                'to_revision' => $toRevision,
                'changed_fields' => array_keys($changed),
                'before_values' => $before,
                'after_values' => $this->snapshot($locked),
                'change_reason' => $values['review_reason'],
                'created_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalize(array $input): array
    {
        return [
            'name' => trim((string) $input['name']),
            'description' => filled($input['description']) ? trim((string) $input['description']) : null,
            'is_sensitive' => (bool) $input['is_sensitive'],
            'review_state' => KnowledgeReviewState::from((string) $input['review_state'])->value,
            'confidence' => StructuredDateConfidence::from((string) $input['confidence'])->value,
            'source_note' => filled($input['source_note']) ? trim((string) $input['source_note']) : null,
            'review_reason' => trim((string) $input['review_reason']),
        ];
    }

    /** @return array<string, mixed> */
    private function snapshot(FamilyBranch $branch): array
    {
        return [
            'name' => $branch->name,
            'description' => $branch->description,
            'is_sensitive' => $branch->is_sensitive,
            'review_state' => $branch->review_state->value,
            'confidence' => $branch->confidence->value,
            'source_note' => $branch->source_note,
            'review_reason' => $branch->review_reason ?? '',
        ];
    }
}
