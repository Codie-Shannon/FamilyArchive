<?php

namespace App\Domain\Duplicates\Models;

use App\Domain\Duplicates\Enums\DuplicateCandidateReviewState;
use App\Domain\Duplicates\Enums\DuplicateMatchMethod;
use App\Domain\Duplicates\Enums\DuplicateReviewDecision;
use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Media\Models\MediaFileVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property DuplicateMatchMethod $match_method
 * @property DuplicateCandidateReviewState $review_state
 * @property DuplicateReviewDecision|null $resolution
 */
#[Fillable([
    'incoming_upload_id', 'matched_incoming_upload_id', 'matched_media_file_version_id',
    'match_method', 'matched_sha256', 'confidence', 'review_state', 'detected_at',
    'reviewed_by', 'reviewed_at', 'resolution',
])]
class DuplicateCandidate extends Model
{
    protected function casts(): array
    {
        return [
            'match_method' => DuplicateMatchMethod::class,
            'confidence' => 'decimal:4',
            'review_state' => DuplicateCandidateReviewState::class,
            'resolution' => DuplicateReviewDecision::class,
            'detected_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<IncomingUpload, $this> */
    public function incomingUpload(): BelongsTo { return $this->belongsTo(IncomingUpload::class); }
    /** @return BelongsTo<IncomingUpload, $this> */
    public function matchedIncomingUpload(): BelongsTo { return $this->belongsTo(IncomingUpload::class, 'matched_incoming_upload_id'); }
    /** @return BelongsTo<MediaFileVersion, $this> */
    public function matchedMediaFileVersion(): BelongsTo { return $this->belongsTo(MediaFileVersion::class, 'matched_media_file_version_id'); }
    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    /** @return HasMany<DuplicateReviewEvent, $this> */
    public function reviewEvents(): HasMany { return $this->hasMany(DuplicateReviewEvent::class)->orderBy('id'); }

    public function targetType(): string
    {
        return $this->matched_incoming_upload_id !== null ? 'retained_incoming_upload' : 'original_media_file_version';
    }
}
