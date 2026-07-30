<?php

namespace App\Domain\Processing\Models;

use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $job_id
 * @property int $media_item_id
 * @property int|null $source_version_id
 * @property int $processing_recipe_id
 * @property int|null $requested_by
 * @property array<string, mixed>|null $automation_preferences
 * @property string $state
 * @property int $attempts
 * @property string|null $failure_reason
 */
#[Fillable([
    'job_id',
    'media_item_id',
    'source_version_id',
    'processing_recipe_id',
    'requested_by',
    'automation_preferences',
    'state',
    'attempts',
    'failure_reason',
    'started_at',
    'completed_at',
])]
final class ProcessingJob extends Model
{
    protected function casts(): array
    {
        return [
            'automation_preferences' => 'array',
            'attempts' => 'integer',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<MediaItem, $this> */
    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class);
    }

    /** @return BelongsTo<MediaFileVersion, $this> */
    public function sourceVersion(): BelongsTo
    {
        return $this->belongsTo(MediaFileVersion::class, 'source_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<ProcessingRecipe, $this> */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(ProcessingRecipe::class, 'processing_recipe_id');
    }

    /** @return HasOne<RestorationCandidate, $this> */
    public function candidate(): HasOne
    {
        return $this->hasOne(RestorationCandidate::class);
    }

    /** @return HasMany<ProcessingJobEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ProcessingJobEvent::class);
    }
}
