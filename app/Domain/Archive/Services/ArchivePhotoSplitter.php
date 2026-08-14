<?php

namespace App\Domain\Archive\Services;

use App\Domain\Archive\Models\ArchivePhotoSplitGroup;
use App\Domain\Derivatives\Actions\GeneratePhotoViewingDerivatives;
use App\Domain\Derivatives\Contracts\NoOverwriteDerivativeWriter;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Processing\Services\PhotoSplitCandidateRenderer;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ArchivePhotoSplitter
{
    public function __construct(
        private ArchivePhotoEditingSource $sources,
        private PhotoSplitCandidateRenderer $renderer,
        private ArchiveIdGenerator $archiveIds,
        private ArchiveStoragePath $paths,
        private NoOverwriteDerivativeWriter $writer,
        private GeneratePhotoViewingDerivatives $derivatives,
        private PhotoVisibilityManager $visibility,
    ) {}

    /**
     * @param  array<array-key, mixed>  $regions
     * @return list<MediaItem>
     */
    public function split(MediaItem $item, User $actor, array $regions, int $expectedRevision, string $sourceBasis): array
    {
        $this->authorize($item, $actor);
        if (! in_array($sourceBasis, ['current', 'original'], true)) {
            throw ValidationException::withMessages(['source_basis' => 'Choose the current correction or the preserved original.']);
        }
        $source = $this->sources->resolve($item, $sourceBasis);
        $normalized = $this->regions($regions);
        $bytes = $this->verifiedBytes($source);
        $dimensions = @getimagesizefromstring($bytes);
        if (! is_array($dimensions)) {
            throw ValidationException::withMessages(['regions_json' => 'The preserved source could not be decoded.']);
        }
        $rendered = $this->renderer->renderBatch($bytes, array_map(
            fn (array $region): array => $this->pixels($region, (int) $dimensions[0], (int) $dimensions[1]),
            $normalized,
        ), false);
        $written = [];
        try {
            $children = DB::transaction(function () use ($item, $actor, $source, $sourceBasis, $normalized, $rendered, $expectedRevision, &$written): array {
                $parent = MediaItem::query()->lockForUpdate()->findOrFail($item->id);
                $this->authorize($parent, $actor);
                if ($parent->metadata_revision !== $expectedRevision || $parent->review_status !== MediaReviewStatus::Approved) {
                    throw ValidationException::withMessages(['regions_json' => 'This photo changed while the split editor was open. Reload it before publishing.']);
                }
                $galleryApprovedAt = $parent->approved_at;

                $group = ArchivePhotoSplitGroup::query()->create([
                    'source_media_item_id' => $parent->id,
                    'source_version_id' => $source->id,
                    'created_by' => $actor->id,
                    'source_basis' => $sourceBasis,
                    'gallery_approved_at' => $galleryApprovedAt,
                    'gallery_archive_id' => $parent->archive_id,
                    'published_at' => now(),
                ]);
                $children = [];
                foreach ($rendered as $index => $candidate) {
                    $archiveId = $this->archiveIds->allocate(MediaType::Photo);
                    $target = $this->paths->derivative(MediaFileVersionType::EditedFull, MediaType::Photo, $archiveId, 'webp', 'archive-split');
                    $object = $this->writer->write($target['path'], $candidate->bytes);
                    $written[] = $object;
                    $child = MediaItem::query()->create([
                        'archive_id' => $archiveId,
                        'media_type' => MediaType::Photo,
                        'title' => $parent->title,
                        'description' => $parent->description,
                        'story' => $parent->story,
                        'canonical_date' => $parent->canonical_date,
                        'date_precision' => $parent->date_precision,
                        'date_year' => $parent->date_year,
                        'estimated_decade' => $parent->estimated_decade,
                        'date_confidence' => $parent->date_confidence,
                        'structured_date_confidence' => $parent->structured_date_confidence,
                        'date_review_state' => $parent->date_review_state,
                        'date_source_note' => $parent->date_source_note,
                        'date_reason' => $parent->date_reason,
                        'visibility' => $parent->visibility,
                        'review_status' => MediaReviewStatus::Approved,
                        'sensitivity_status' => $parent->sensitivity_status,
                        'family_branch_id' => $parent->family_branch_id,
                        'created_by' => $parent->created_by,
                        'approved_by' => $actor->id,
                        'approved_at' => $galleryApprovedAt,
                    ]);
                    $child->forceFill([
                        'contains_living_person' => $parent->getAttribute('contains_living_person'),
                        'contains_child' => $parent->getAttribute('contains_child'),
                    ])->save();
                    MediaFileVersion::query()->create([
                        'media_item_id' => $child->id,
                        'parent_version_id' => $source->id,
                        'version_type' => MediaFileVersionType::EditedFull,
                        'storage_disk' => $target['disk']->value,
                        'storage_path' => $object->relativePath,
                        'mime_type' => 'image/webp',
                        'extension' => 'webp',
                        'file_size_bytes' => $object->bytes,
                        'width' => $candidate->width,
                        'height' => $candidate->height,
                        'sha256' => $object->sha256,
                        'generation_status' => GenerationStatus::Ready,
                        'generation_recipe' => [
                            'operation' => 'archive_photo_split',
                            'source_media_item_id' => $parent->id,
                            'source_version_id' => $source->id,
                            'source_sha256' => $source->sha256,
                            'split_group_id' => $group->id,
                            'source_basis' => $sourceBasis,
                            'region_position' => $index + 1,
                            'bounds_basis_points' => $normalized[$index],
                            'candidate_pipeline' => $candidate->recipe,
                            'original_retained' => true,
                        ],
                        'is_preferred' => true,
                    ]);
                    $group->members()->create([
                        'media_item_id' => $child->id,
                        'position' => $index + 1,
                        'bounds' => $normalized[$index],
                    ]);
                    $this->copyLinks($parent, $child, $actor);
                    $children[] = $child;
                }
                $parent->forceFill([
                    'review_status' => MediaReviewStatus::Hidden,
                    'approved_by' => null,
                    'approved_at' => null,
                    'metadata_revision' => $parent->metadata_revision + 1,
                ])->save();

                return $children;
            }, 5);
        } catch (Throwable $exception) {
            foreach ($written as $object) {
                $this->writer->removeCreated($object);
            }
            throw $exception;
        }

        foreach ($children as $child) {
            $this->derivatives->handle($child->fresh(), $actor, true);
        }

        return $children;
    }

    private function authorize(MediaItem $item, User $actor): void
    {
        abort_unless($this->visibility->canManage($actor, $item)
            && $item->media_type === MediaType::Photo
            && $item->review_status === MediaReviewStatus::Approved
            && $item->approved_at !== null, 403);
    }

    /**
     * @param  array<array-key, mixed>  $regions
     * @return list<array{x:int,y:int,width:int,height:int,rotation_degrees:int}>
     */
    private function regions(array $regions): array
    {
        $normalized = [];
        foreach ($regions as $region) {
            if (! is_array($region)) {
                throw ValidationException::withMessages(['regions_json' => 'Every split region needs valid bounds.']);
            }
            foreach (['x', 'y', 'width', 'height'] as $key) {
                if (! isset($region[$key]) || ! is_int($region[$key])) {
                    throw ValidationException::withMessages(['regions_json' => 'Every split region needs whole-number bounds.']);
                }
            }
            $rotation = $region['rotation_degrees'] ?? 0;
            if (! is_int($rotation) || $rotation < -359 || $rotation > 359
                || $region['x'] < 0 || $region['y'] < 0 || $region['width'] < 250 || $region['height'] < 250
                || $region['x'] + $region['width'] > 10000 || $region['y'] + $region['height'] > 10000) {
                throw ValidationException::withMessages(['regions_json' => 'Split regions must stay inside the photo and retain a usable size.']);
            }
            $normalized[] = [
                'x' => $region['x'], 'y' => $region['y'], 'width' => $region['width'], 'height' => $region['height'],
                'rotation_degrees' => (($rotation % 360) + 360) % 360,
            ];
        }
        if (count($normalized) < 2 || count($normalized) > 20) {
            throw ValidationException::withMessages(['regions_json' => 'Add between 2 and 20 photo regions before publishing.']);
        }

        return $normalized;
    }

    /** @param array{x:int,y:int,width:int,height:int,rotation_degrees:int} $region
     * @return array{x:int,y:int,width:int,height:int,rotation_degrees:int}
     */
    private function pixels(array $region, int $width, int $height): array
    {
        $x = (int) floor($width * $region['x'] / 10000);
        $y = (int) floor($height * $region['y'] / 10000);

        return [
            'x' => $x,
            'y' => $y,
            'width' => min(max(1, (int) ceil($width * $region['width'] / 10000)), $width - $x),
            'height' => min(max(1, (int) ceil($height * $region['height'] / 10000)), $height - $y),
            'rotation_degrees' => $region['rotation_degrees'],
        ];
    }

    private function verifiedBytes(MediaFileVersion $source): string
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($source->storage_disk);
        abort_unless($disk->exists($source->storage_path), 404);
        $bytes = $disk->get($source->storage_path);
        abort_unless(strlen($bytes) === $source->file_size_bytes && hash_equals(strtolower($source->sha256), hash('sha256', $bytes)), 409);

        return $bytes;
    }

    private function copyLinks(MediaItem $parent, MediaItem $child, User $actor): void
    {
        foreach ($parent->provenanceLinks()->get() as $link) {
            $child->provenanceLinks()->create([
                'source_collection_id' => $link->source_collection_id,
                'scan_batch_id' => $link->scan_batch_id,
                'note' => $link->note,
                'attached_by' => $actor->id,
            ]);
        }
        $now = now();
        foreach (DB::table('curated_collection_media')->where('media_item_id', $parent->id)->get() as $membership) {
            DB::table('curated_collection_media')->insertOrIgnore([
                'curated_collection_id' => $membership->curated_collection_id,
                'media_item_id' => $child->id,
                'added_by' => $actor->id,
                'position' => $membership->position,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        foreach (DB::table('archive_person_media')->where('media_item_id', $parent->id)->get() as $membership) {
            DB::table('archive_person_media')->insertOrIgnore([
                'archive_person_id' => $membership->archive_person_id,
                'media_item_id' => $child->id,
                'context' => $membership->context,
                'confidence' => $membership->confidence,
                'reviewed_by' => $actor->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        foreach (DB::table('archive_event_media')->where('media_item_id', $parent->id)->get() as $membership) {
            DB::table('archive_event_media')->insertOrIgnore([
                'archive_event_id' => $membership->archive_event_id,
                'media_item_id' => $child->id,
                'confidence' => $membership->confidence,
                'source_note' => $membership->source_note,
                'reviewed_by' => $actor->id,
                'reviewed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
