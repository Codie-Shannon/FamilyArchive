<?php

namespace App\Domain\Duplicates\Actions;

use App\Domain\Duplicates\Enums\DuplicateCandidateReviewState;
use App\Domain\Duplicates\Enums\DuplicateReviewDecision;
use App\Domain\Duplicates\Models\DuplicateCandidate;
use App\Domain\Duplicates\Models\DuplicateReviewEvent;
use App\Domain\Intake\Enums\DuplicateStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ResolveDuplicateCandidate
{
    /** @param array<string, scalar|null> $requestContext */
    public function handle(DuplicateCandidate $candidate, DuplicateReviewDecision $decision, ?string $reason, User $actor, array $requestContext = [], ?callable $beforeCommit = null): DuplicateCandidate
    {
        $reason = trim((string) $reason);

        return DB::transaction(function () use ($candidate, $decision, $reason, $actor, $requestContext, $beforeCommit): DuplicateCandidate {
            $locked = DuplicateCandidate::query()->lockForUpdate()->findOrFail($candidate->id);
            $upload = $locked->incomingUpload()->lockForUpdate()->firstOrFail();
            $previous = $locked->resolution;

            if ($previous === $decision) {
                return $locked->fresh(['reviewEvents.actor']);
            }

            if (($previous !== null || $decision->requiresInitialNote()) && $reason === '') {
                throw ValidationException::withMessages(['reason' => 'A decision note is required for this decision or correction.']);
            }

            DuplicateReviewEvent::query()->create([
                'duplicate_candidate_id' => $locked->id,
                'previous_decision' => $previous?->value,
                'new_decision' => $decision->value,
                'actor_id' => $actor->id,
                'reason' => $reason !== '' ? $reason : null,
                'request_context' => array_filter([
                    'route' => isset($requestContext['route']) ? substr((string) $requestContext['route'], 0, 120) : null,
                    'method' => isset($requestContext['method']) ? substr((string) $requestContext['method'], 0, 12) : null,
                ], fn ($value) => $value !== null),
                'decided_at' => now(),
            ]);

            $locked->forceFill([
                'review_state' => DuplicateCandidateReviewState::Resolved,
                'resolution' => $decision,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ])->save();

            $upload->forceFill(['duplicate_status' => DuplicateStatus::from($decision->value)])->save();

            if ($beforeCommit !== null) {
                $beforeCommit();
            }

            return $locked->fresh(['reviewEvents.actor']);
        }, 3);
    }
}
