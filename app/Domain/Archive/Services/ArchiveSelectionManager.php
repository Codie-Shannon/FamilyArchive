<?php

namespace App\Domain\Archive\Services;

use App\Domain\Archive\Models\ArchiveSelectionDraft;
use App\Domain\Media\Models\MediaItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ArchiveSelectionManager
{
    public function draft(User $user, string $context): ArchiveSelectionDraft
    {
        return ArchiveSelectionDraft::query()->firstOrCreate([
            'user_id' => $user->id,
            'context' => $this->normalizeContext($context),
        ]);
    }

    /** @return Collection<int, int> */
    public function ids(User $user, string $context): Collection
    {
        return $this->draft($user, $context)->mediaItems()
            ->orderByPivot('selected_at')
            ->pluck('media_items.id')
            ->map(static fn ($id): int => (int) $id);
    }

    public function count(User $user, string $context): int
    {
        return $this->draft($user, $context)->mediaItems()->count();
    }

    /** @return array{count: int, page_count: int, selected_ids: list<int>} */
    public function set(User $user, string $context, MediaItem $item, bool $selected, int $sourcePage = 1): array
    {
        $draft = $this->draft($user, $context);
        if ($selected) {
            DB::table('archive_selection_items')->upsert([[
                'archive_selection_draft_id' => $draft->id,
                'media_item_id' => $item->id,
                'selected_at' => now(),
                'source_page' => max(1, $sourcePage),
            ]], ['archive_selection_draft_id', 'media_item_id'], ['selected_at', 'source_page']);
        } else {
            DB::table('archive_selection_items')
                ->where('archive_selection_draft_id', $draft->id)
                ->where('media_item_id', $item->id)
                ->delete();
        }

        return $this->summary($user, $context);
    }

    /** @return array{count: int, page_count: int, selected_ids: list<int>} */
    public function summary(User $user, string $context): array
    {
        $draft = $this->draft($user, $context);
        $selectedIds = [];
        foreach ($this->ids($user, $context) as $id) {
            $selectedIds[] = $id;
        }

        return [
            'count' => $draft->mediaItems()->count(),
            'page_count' => DB::table('archive_selection_items')
                ->where('archive_selection_draft_id', $draft->id)
                ->distinct()->count('source_page'),
            'selected_ids' => $selectedIds,
        ];
    }

    public function clear(User $user, string $context): void
    {
        $draft = $this->draft($user, $context);
        DB::table('archive_selection_items')
            ->where('archive_selection_draft_id', $draft->id)
            ->delete();
    }

    public function normalizeContext(string $context): string
    {
        $context = trim($context);
        abort_unless(preg_match('/^(?:photos:(?:visible|hidden|edit)|album:\d+)$/', $context) === 1, 422);

        return $context;
    }
}
