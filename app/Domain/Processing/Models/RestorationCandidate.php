<?php

namespace App\Domain\Processing\Models;

use App\Domain\Media\Models\MediaFileVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property array<string, mixed>|null $analysis
 * @property string $review_state
 */
#[Fillable([
    'candidate_id',
    'processing_job_id',
    'source_version_id',
    'candidate_version_id',
    'quality_checks',
    'analysis',
    'operations_applied',
    'review_state',
    'reviewed_by',
    'review_note',
    'reviewed_at',
])]
final class RestorationCandidate extends Model
{
    protected function casts(): array
    {
        return [
            'quality_checks' => 'array',
            'analysis' => 'array',
            'operations_applied' => 'array',
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<ProcessingJob, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(ProcessingJob::class, 'processing_job_id');
    }

    /** @return BelongsTo<MediaFileVersion, $this> */
    public function sourceVersion(): BelongsTo
    {
        return $this->belongsTo(MediaFileVersion::class, 'source_version_id');
    }

    /** @return BelongsTo<MediaFileVersion, $this> */
    public function candidateVersion(): BelongsTo
    {
        return $this->belongsTo(MediaFileVersion::class, 'candidate_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
