<?php

namespace App\Domain\Archive\Services;

use App\Domain\Archive\Models\PhotoVisibilityEvent;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Models\MediaItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PhotoVisibilityManager
{
    public function canManage(User $user, MediaItem $item): bool
    {
        return $user->role === 'owner' || $item->created_by === $user->id;
    }

    public function hide(
        MediaItem $item,
        User $actor,
        string $category,
        ?string $note,
        bool $batch,
        ?int $expectedRevision = null,
    ): bool {
        return DB::transaction(function () use ($item, $actor, $category, $note, $batch, $expectedRevision): bool {
            $locked = MediaItem::query()->lockForUpdate()->findOrFail($item->id);
            abort_unless($this->canManage($actor, $locked), 403);
            abort_unless($locked->media_type === MediaType::Photo
                && $locked->review_status === MediaReviewStatus::Approved
                && $locked->approved_at !== null, 404);
            if ($locked->hidden_at !== null) {
                return false;
            }
            if ($expectedRevision !== null && $locked->metadata_revision !== $expectedRevision) {
                throw ValidationException::withMessages(['photo' => 'This photo changed while the hide form was open.']);
            }

            $previous = $locked->visibility->value;
            $fromRevision = $locked->metadata_revision;
            $locked->forceFill([
                'visibility' => MediaVisibility::PrivateArchive,
                'hidden_at' => now(),
                'hidden_by' => $actor->id,
                'hidden_previous_visibility' => $previous,
                'hide_reason_category' => $category,
                'hide_reason_note' => filled($note) ? trim((string) $note) : null,
                'metadata_revision' => $fromRevision + 1,
            ])->save();
            $this->record($locked, $actor, 'hide', $previous, MediaVisibility::PrivateArchive->value, $category, $note, $batch, $fromRevision);

            return true;
        }, 5);
    }

    public function restore(MediaItem $item, User $actor, bool $batch = true): bool
    {
        return DB::transaction(function () use ($item, $actor, $batch): bool {
            $locked = MediaItem::query()->lockForUpdate()->findOrFail($item->id);
            abort_unless($this->canManage($actor, $locked), 403);
            if ($locked->hidden_at === null) {
                return false;
            }

            $previous = $locked->visibility->value;
            $restored = MediaVisibility::tryFrom((string) $locked->hidden_previous_visibility)
                ?? MediaVisibility::FamilyVisible;
            $fromRevision = $locked->metadata_revision;
            $locked->forceFill([
                'visibility' => $restored,
                'hidden_at' => null,
                'hidden_by' => null,
                'hidden_previous_visibility' => null,
                'hide_reason_category' => null,
                'hide_reason_note' => null,
                'metadata_revision' => $fromRevision + 1,
            ])->save();
            $this->record($locked, $actor, 'restore', $previous, $restored->value, 'restored', null, $batch, $fromRevision);

            return true;
        }, 5);
    }

    private function record(MediaItem $item, User $actor, string $action, string $previous, string $new, string $category, ?string $note, bool $batch, int $fromRevision): void
    {
        PhotoVisibilityEvent::query()->create([
            'media_item_id' => $item->id,
            'actor_user_id' => $actor->id,
            'action' => $action,
            'previous_visibility' => $previous,
            'new_visibility' => $new,
            'reason_category' => $category,
            'reason_note' => filled($note) ? trim((string) $note) : null,
            'batch_action' => $batch,
            'from_metadata_revision' => $fromRevision,
            'to_metadata_revision' => $fromRevision + 1,
            'occurred_at' => now(),
        ]);
    }
}
