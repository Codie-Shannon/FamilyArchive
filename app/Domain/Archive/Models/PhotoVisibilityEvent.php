<?php

namespace App\Domain\Archive\Models;

use App\Domain\Media\Models\MediaItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'media_item_id', 'actor_user_id', 'action', 'previous_visibility',
    'new_visibility', 'reason_category', 'reason_note', 'batch_action',
    'from_metadata_revision', 'to_metadata_revision', 'occurred_at',
])]
final class PhotoVisibilityEvent extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['batch_action' => 'boolean', 'occurred_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<MediaItem, $this> */
    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
