<?php

namespace App\Domain\Archive\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'batch_id', 'user_id', 'active_user_id', 'state', 'total_count', 'completed_count', 'failed_count',
    'started_at', 'completed_at',
])]
final class ArchivePhotoEditBatch extends Model
{
    protected function casts(): array
    {
        return [
            'total_count' => 'integer',
            'completed_count' => 'integer',
            'failed_count' => 'integer',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'batch_id';
    }

    public function isActive(): bool
    {
        return in_array($this->state, ['queued', 'running'], true);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ArchivePhotoEditBatchItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ArchivePhotoEditBatchItem::class)->orderBy('position');
    }
}
