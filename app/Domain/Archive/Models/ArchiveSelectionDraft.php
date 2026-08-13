<?php

namespace App\Domain\Archive\Models;

use App\Domain\Media\Models\MediaItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['user_id', 'context'])]
final class ArchiveSelectionDraft extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<MediaItem, $this> */
    public function mediaItems(): BelongsToMany
    {
        return $this->belongsToMany(MediaItem::class, 'archive_selection_items')
            ->withPivot(['selected_at', 'source_page']);
    }
}
