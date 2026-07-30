<?php

namespace App\Domain\Processing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['processing_job_id', 'actor_id', 'event', 'safe_context', 'occurred_at'])]
final class ProcessingJobEvent extends Model
{
    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('Processing history is immutable.');
        });

        self::deleting(static function (): never {
            throw new LogicException('Processing history cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'safe_context' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<ProcessingJob, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(ProcessingJob::class, 'processing_job_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
