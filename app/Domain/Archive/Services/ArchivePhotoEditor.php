<?php

namespace App\Domain\Archive\Services;

use App\Domain\Archive\Models\ArchivePhotoEditDraft;
use App\Domain\Archive\Models\ArchivePhotoSplitMember;
use App\Domain\Derivatives\Actions\GeneratePhotoViewingDerivatives;
use App\Domain\Derivatives\Contracts\NoOverwriteDerivativeWriter;
use App\Domain\Derivatives\Services\ApprovedPhotoViewingSource;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Processing\Models\PhotoSplitRegion;
use App\Domain\Processing\Models\ProcessingJob;
use App\Domain\Processing\Models\ProcessingJobEvent;
use App\Domain\Processing\Models\ProcessingRecipe;
use App\Domain\Processing\Models\RestorationCandidate;
use App\Domain\Processing\Services\ManualRestorationEditor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ArchivePhotoEditor
{
    public function __construct(
        private ApprovedPhotoViewingSource $sources,
        private ManualRestorationEditor $renderer,
        private NoOverwriteDerivativeWriter $writer,
        private StoragePathValidator $paths,
        private GeneratePhotoViewingDerivatives $derivatives,
        private PhotoVisibilityManager $visibility,
    ) {}

    public function source(MediaItem $item, bool $fromSourceScan = false): MediaFileVersion
    {
        if ($fromSourceScan) {
            $member = ArchivePhotoSplitMember::query()->with('group.sourceVersion')
                ->where('media_item_id', $item->id)->first();
            $archiveSource = $member?->group?->sourceVersion;
            if ($archiveSource instanceof MediaFileVersion) {
                return $archiveSource;
            }
            $region = PhotoSplitRegion::query()->with('proposal.sourceVersion')
                ->where('output_media_item_id', $item->id)->first();
            $source = $region?->proposal?->sourceVersion;
            if ($source instanceof MediaFileVersion) {
                return $source;
            }
            throw ValidationException::withMessages(['editor' => 'This photo is not linked to a preserved source scan.']);
        }

        $source = $this->sources->resolve($item);
        if (! $source instanceof MediaFileVersion) {
            throw ValidationException::withMessages(['editor' => 'No verified full-resolution editing source is available.']);
        }

        return $source;
    }

    public function isSplit(MediaItem $item): bool
    {
        return ArchivePhotoSplitMember::query()->where('media_item_id', $item->id)->exists()
            || PhotoSplitRegion::query()->where('output_media_item_id', $item->id)->exists();
    }

    /** @param array<string, bool|float|int> $settings */
    public function saveDraft(MediaItem $item, User $actor, array $settings, bool $fromSourceScan): ArchivePhotoEditDraft
    {
        $this->authorize($item, $actor);
        $source = $this->source($item, $fromSourceScan);

        return ArchivePhotoEditDraft::query()->updateOrCreate(
            ['user_id' => $actor->id, 'media_item_id' => $item->id],
            ['source_version_id' => $source->id, 'settings' => $settings, 'expected_metadata_revision' => (int) ($item->metadata_revision ?? 0), 'from_source_scan' => $fromSourceScan],
        );
    }

    public function publish(ArchivePhotoEditDraft $draft, User $actor): MediaFileVersion
    {
        $draft->load(['mediaItem', 'sourceVersion']);
        $item = $draft->mediaItem;
        $source = $draft->sourceVersion;
        if ($draft->user_id !== $actor->id) {
            abort(403);
        }

        $version = $this->publishSnapshot(
            $item,
            $source,
            $draft->editorSettings(),
            $draft->expected_metadata_revision,
            $draft->from_source_scan,
            $actor,
        );
        $draft->delete();

        return $version;
    }

    /** @param array<string, bool|float|int> $settings */
    public function publishSnapshot(
        MediaItem $item,
        MediaFileVersion $source,
        array $settings,
        int $expectedMetadataRevision,
        bool $fromSourceScan,
        User $actor,
        ?int $batchEditItemId = null,
    ): MediaFileVersion {
        $this->authorize($item, $actor);
        if ($batchEditItemId !== null) {
            $publishedVersionId = DB::table('archive_photo_edit_batch_items as batch_items')
                ->join('archive_photo_edit_batches as batches', 'batch_items.archive_photo_edit_batch_id', '=', 'batches.id')
                ->where('batch_items.id', $batchEditItemId)
                ->where('batch_items.media_item_id', $item->id)
                ->where('batches.user_id', $actor->id)
                ->value('batch_items.published_version_id');
            if ($publishedVersionId !== null) {
                $version = MediaFileVersion::query()->where('id', $publishedVersionId)->sole();
                $this->derivatives->handle($item->fresh(), $actor, true);

                return $version;
            }
        }
        if ($item->metadata_revision !== $expectedMetadataRevision) {
            throw ValidationException::withMessages(['editor' => 'This photo changed after the draft was created. Reload it before saving.']);
        }

        $rendered = $this->renderer->renderApprovedSource($source, $settings);
        $candidateId = (string) Str::uuid();
        $path = $this->paths->validateRelativePath('restoration-candidates/'.$item->id.'/'.$candidateId.'.webp');
        $written = $this->writer->write($path, $rendered['bytes']);
        $committed = false;

        try {
            $version = DB::transaction(function () use ($actor, $item, $source, $rendered, $candidateId, $written, $expectedMetadataRevision, $fromSourceScan, $batchEditItemId): MediaFileVersion {
                $lockedItem = MediaItem::query()->lockForUpdate()->findOrFail($item->id);
                $this->authorize($lockedItem, $actor);
                if ($lockedItem->metadata_revision !== $expectedMetadataRevision) {
                    throw ValidationException::withMessages(['editor' => 'This photo changed while the edit was processing.']);
                }
                $lockedSource = MediaFileVersion::query()->lockForUpdate()->findOrFail($source->id);
                $recipe = ProcessingRecipe::query()->create([
                    'created_by' => $actor->id, 'recipe_id' => 'RCP-'.strtoupper(Str::random(12)),
                    'name' => 'Archive non-destructive edit '.$candidateId, 'version' => 1,
                    'operations' => $rendered['operations'], 'automation_source' => 'archive_editor',
                    'is_batch_profile' => false, 'is_active' => true,
                ]);
                $job = ProcessingJob::query()->create([
                    'job_id' => (string) Str::uuid(), 'media_item_id' => $lockedItem->id,
                    'source_version_id' => $lockedSource->id, 'processing_recipe_id' => $recipe->id,
                    'requested_by' => $actor->id, 'automation_preferences' => ['mode' => 'archive_editor', 'settings' => $rendered['normalized']],
                    'state' => 'approved', 'attempts' => 1, 'started_at' => now(), 'completed_at' => now(),
                ]);
                MediaFileVersion::query()->where('media_item_id', $lockedItem->id)
                    ->where('version_type', MediaFileVersionType::EditedFull)->update(['is_preferred' => false]);
                $version = MediaFileVersion::query()->create([
                    'media_item_id' => $lockedItem->id, 'parent_version_id' => $lockedSource->id,
                    'version_type' => MediaFileVersionType::EditedFull, 'storage_disk' => 'archive_derivatives',
                    'storage_path' => $written->relativePath, 'mime_type' => 'image/webp', 'extension' => 'webp',
                    'file_size_bytes' => $written->bytes, 'width' => $rendered['width'], 'height' => $rendered['height'],
                    'duration_ms' => null, 'sha256' => $written->sha256, 'perceptual_hash' => null,
                    'generation_status' => GenerationStatus::Ready,
                    'generation_recipe' => ['editor' => 'archive', 'source_sha256' => $rendered['source_sha256'], 'operations' => $rendered['operations'], 'preserves_original' => true, 'from_source_scan' => $fromSourceScan, 'batch_edit_item_id' => $batchEditItemId],
                    'is_preferred' => true,
                ]);
                RestorationCandidate::query()->create([
                    'candidate_id' => $candidateId, 'processing_job_id' => $job->id,
                    'source_version_id' => $lockedSource->id, 'candidate_version_id' => $version->id,
                    'quality_checks' => ['source_hash_verified_before' => true, 'source_hash_verified_after' => true, 'candidate_hash_verified' => true, 'original_retained' => true],
                    'analysis' => ['editor' => 'archive', 'settings' => $rendered['normalized'], 'from_source_scan' => $fromSourceScan],
                    'operations_applied' => array_keys($rendered['operations']), 'review_state' => 'approved',
                    'reviewed_by' => $actor->id, 'review_note' => 'Saved by the photo owner in the non-destructive archive editor.', 'reviewed_at' => now(),
                ]);
                ProcessingJobEvent::query()->create([
                    'processing_job_id' => $job->id, 'actor_id' => $actor->id,
                    'event' => 'archive_edit_approved', 'safe_context' => ['original_retained' => true, 'from_source_scan' => $fromSourceScan, 'batch_edit' => $batchEditItemId !== null], 'occurred_at' => now(),
                ]);
                if ($batchEditItemId !== null) {
                    $checkpointed = DB::table('archive_photo_edit_batch_items')
                        ->where('id', $batchEditItemId)
                        ->where('media_item_id', $lockedItem->id)
                        ->whereNull('published_version_id')
                        ->update(['published_version_id' => $version->id, 'updated_at' => now()]);
                    if ($checkpointed !== 1) {
                        throw ValidationException::withMessages(['editor' => 'The batch checkpoint changed while this photo was processing.']);
                    }
                }

                return $version;
            }, 5);
            $committed = true;

            $this->derivatives->handle($item->fresh(), $actor, true);

            return $version;
        } catch (Throwable $exception) {
            if (! $committed) {
                $this->writer->removeCreated($written);
            }
            throw $exception;
        }
    }

    private function authorize(MediaItem $item, User $actor): void
    {
        abort_unless($this->visibility->canManage($actor, $item)
            && $item->media_type === MediaType::Photo
            && $item->review_status === MediaReviewStatus::Approved
            && $item->approved_at !== null, 403);
    }
}
