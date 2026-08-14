<?php

namespace App\Domain\Archive\Services;

use App\Domain\Archive\Models\ArchivePhotoEditBatch;
use App\Domain\Archive\Models\ArchivePhotoEditBatchItem;
use App\Domain\Archive\Models\ArchivePhotoEditDraft;
use App\Domain\Derivatives\Exceptions\DerivativeGenerationException;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Jobs\PublishArchivePhotoEdit;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ArchivePhotoEditBatchPublisher
{
    public function __construct(private ArchivePhotoEditor $editor) {}

    /** @param EloquentCollection<int, ArchivePhotoEditDraft> $drafts */
    public function start(User $actor, EloquentCollection $drafts): ArchivePhotoEditBatch
    {
        if ($drafts->isEmpty()) {
            throw ValidationException::withMessages(['editor' => 'No changed photos are ready to save.']);
        }

        /** @var array{batch: ArchivePhotoEditBatch, item_ids: list<int>, existing: bool} $created */
        $created = DB::transaction(function () use ($actor, $drafts): array {
            $existing = ArchivePhotoEditBatch::query()
                ->where('active_user_id', $actor->id)
                ->whereIn('state', ['queued', 'running'])
                ->lockForUpdate()
                ->first();
            if ($existing instanceof ArchivePhotoEditBatch) {
                return ['batch' => $existing, 'item_ids' => [], 'existing' => true];
            }

            $batch = ArchivePhotoEditBatch::query()->create([
                'batch_id' => (string) Str::uuid(),
                'user_id' => $actor->id,
                'active_user_id' => $actor->id,
                'state' => 'queued',
                'total_count' => $drafts->count(),
            ]);
            $itemIds = [];
            foreach ($drafts->values() as $position => $draft) {
                $item = $batch->items()->create([
                    'media_item_id' => $draft->media_item_id,
                    'source_version_id' => $draft->source_version_id,
                    'draft_id' => $draft->id,
                    'draft_fingerprint' => $this->draftFingerprint($draft),
                    'settings' => $draft->editorSettings(),
                    'expected_metadata_revision' => $draft->expected_metadata_revision,
                    'from_source_scan' => $draft->from_source_scan,
                    'position' => $position + 1,
                    'state' => 'queued',
                ]);
                $itemIds[] = $item->id;
            }

            return ['batch' => $batch, 'item_ids' => $itemIds, 'existing' => false];
        }, 5);

        if (! $created['existing']) {
            $this->dispatch($created['item_ids']);
        }

        return $created['batch'];
    }

    public function publish(int $batchItemId, int $attempt): void
    {
        $item = DB::transaction(function () use ($batchItemId, $attempt): ?ArchivePhotoEditBatchItem {
            $locked = ArchivePhotoEditBatchItem::query()->with('batch')->lockForUpdate()->find($batchItemId);
            if (! $locked instanceof ArchivePhotoEditBatchItem || $locked->state === 'completed') {
                return null;
            }
            $locked->forceFill([
                'state' => 'processing',
                'attempts' => max($locked->attempts, $attempt),
                'failure_code' => null,
                'failure_message' => null,
                'started_at' => $locked->started_at ?? now(),
            ])->save();
            $locked->batch->forceFill([
                'state' => 'running',
                'started_at' => $locked->batch->started_at ?? now(),
            ])->save();

            return $locked;
        }, 5);
        if (! $item instanceof ArchivePhotoEditBatchItem) {
            return;
        }

        $item->load(['batch.user', 'mediaItem', 'sourceVersion']);
        $actor = $item->batch->user;
        $mediaItem = $item->mediaItem;
        $source = $item->sourceVersion;
        abort_unless($actor instanceof User && $mediaItem instanceof MediaItem && $source instanceof MediaFileVersion, 409);

        $this->editor->publishSnapshot(
            $mediaItem,
            $source,
            $item->settings,
            $item->expected_metadata_revision,
            $item->from_source_scan,
            $actor,
            $item->id,
        );
        $this->deleteUnchangedDraft($item, $actor);
        $this->finishItem($item->id, 'completed');
    }

    public function requeue(int $batchItemId, Throwable $exception): void
    {
        $this->recordFailure($batchItemId, 'queued', $exception);
    }

    public function fail(int $batchItemId, Throwable $exception): void
    {
        $this->recordFailure($batchItemId, 'failed', $exception);
    }

    public function retry(ArchivePhotoEditBatch $batch, User $actor): ArchivePhotoEditBatch
    {
        abort_unless($batch->user_id === $actor->id, 403);
        $itemIds = DB::transaction(function () use ($batch, $actor): array {
            $locked = ArchivePhotoEditBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($locked->failed_count < 1 || $locked->isActive()) {
                throw ValidationException::withMessages(['editor' => 'This batch has no failed photos ready to retry.']);
            }
            $otherActive = ArchivePhotoEditBatch::query()
                ->where('active_user_id', $actor->id)
                ->whereKeyNot($locked->id)
                ->exists();
            if ($otherActive) {
                throw ValidationException::withMessages(['editor' => 'Finish the active photo-save batch before retrying this one.']);
            }
            $ids = $locked->items()->where('state', 'failed')->pluck('id')->map(static fn ($id): int => (int) $id)->values()->all();
            $locked->items()->whereIn('id', $ids)->update([
                'state' => 'queued', 'failure_code' => null, 'failure_message' => null, 'completed_at' => null,
            ]);
            $locked->forceFill([
                'active_user_id' => $actor->id,
                'state' => 'queued',
                'failed_count' => 0,
                'completed_at' => null,
            ])->save();

            return $ids;
        }, 5);
        $this->dispatch($itemIds);

        return $batch->fresh();
    }

    public function hasActiveItem(User $actor, MediaItem $item): bool
    {
        return ArchivePhotoEditBatchItem::query()
            ->where('media_item_id', $item->id)
            ->whereIn('state', ['queued', 'processing'])
            ->whereHas('batch', fn ($query) => $query->where('user_id', $actor->id)->whereIn('state', ['queued', 'running']))
            ->exists();
    }

    public function draftFingerprint(ArchivePhotoEditDraft $draft): string
    {
        $settings = $draft->editorSettings();
        ksort($settings);

        return hash('sha256', json_encode([
            'source_version_id' => $draft->source_version_id,
            'expected_metadata_revision' => $draft->expected_metadata_revision,
            'from_source_scan' => $draft->from_source_scan,
            'settings' => $settings,
        ], JSON_THROW_ON_ERROR));
    }

    /** @param array<int, int> $itemIds */
    private function dispatch(array $itemIds): void
    {
        foreach ($itemIds as $itemId) {
            PublishArchivePhotoEdit::dispatch($itemId);
        }
    }

    private function deleteUnchangedDraft(ArchivePhotoEditBatchItem $item, User $actor): void
    {
        $draft = ArchivePhotoEditDraft::query()
            ->whereKey($item->draft_id)
            ->where('user_id', $actor->id)
            ->where('media_item_id', $item->media_item_id)
            ->first();
        if ($draft instanceof ArchivePhotoEditDraft && hash_equals($item->draft_fingerprint, $this->draftFingerprint($draft))) {
            $draft->delete();
        }
    }

    private function finishItem(int $batchItemId, string $state): void
    {
        DB::transaction(function () use ($batchItemId, $state): void {
            $item = ArchivePhotoEditBatchItem::query()->lockForUpdate()->findOrFail($batchItemId);
            $item->forceFill(['state' => $state, 'completed_at' => now()])->save();
            $this->refreshBatch($item->archive_photo_edit_batch_id);
        }, 5);
    }

    private function recordFailure(int $batchItemId, string $state, Throwable $exception): void
    {
        if ($state === 'failed' && ! $exception instanceof ValidationException && ! $exception instanceof DerivativeGenerationException) {
            report($exception);
        }
        DB::transaction(function () use ($batchItemId, $state, $exception): void {
            $item = ArchivePhotoEditBatchItem::query()->lockForUpdate()->find($batchItemId);
            if (! $item instanceof ArchivePhotoEditBatchItem || $item->state === 'completed') {
                return;
            }
            $item->forceFill([
                'state' => $state,
                'failure_code' => class_basename($exception),
                'failure_message' => $this->safeMessage($exception),
                'completed_at' => $state === 'failed' ? now() : null,
            ])->save();
            $this->refreshBatch($item->archive_photo_edit_batch_id);
        }, 5);
    }

    private function refreshBatch(int $batchId): void
    {
        $batch = ArchivePhotoEditBatch::query()->lockForUpdate()->findOrFail($batchId);
        $completed = $batch->items()->where('state', 'completed')->count();
        $failed = $batch->items()->where('state', 'failed')->count();
        $finished = $completed + $failed >= $batch->total_count;
        $batch->forceFill([
            'completed_count' => $completed,
            'failed_count' => $failed,
            'state' => $finished ? ($failed > 0 ? 'completed_with_failures' : 'completed') : ($batch->started_at === null ? 'queued' : 'running'),
            'active_user_id' => $finished ? null : $batch->user_id,
            'completed_at' => $finished ? now() : null,
        ])->save();
    }

    private function safeMessage(Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            $message = collect($exception->errors())->flatten()->first();

            return is_string($message) ? Str::limit($message, 500) : 'This photo changed and needs review.';
        }
        if ($exception instanceof DerivativeGenerationException) {
            return Str::limit($exception->getMessage(), 500);
        }

        return 'Photo processing failed and can be retried safely.';
    }
}
