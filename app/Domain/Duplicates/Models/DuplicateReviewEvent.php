<?php

namespace App\Domain\Duplicates\Models;

use App\Domain\Duplicates\Enums\DuplicateReviewDecision;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['duplicate_candidate_id', 'previous_decision', 'new_decision', 'actor_id', 'reason', 'request_context', 'decided_at'])]
final class DuplicateReviewEvent extends Model
{
    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Duplicate review events are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Duplicate review events are immutable.'));
    }

    protected function casts(): array
    {
        return [
            'previous_decision' => DuplicateReviewDecision::class,
            'new_decision' => DuplicateReviewDecision::class,
            'request_context' => 'array',
            'decided_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<DuplicateCandidate, $this> */
    public function candidate(): BelongsTo { return $this->belongsTo(DuplicateCandidate::class, 'duplicate_candidate_id'); }
    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
