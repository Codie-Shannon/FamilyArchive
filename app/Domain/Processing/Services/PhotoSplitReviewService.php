<?php

namespace App\Domain\Processing\Services;

use App\Domain\Archive\Services\ArchiveIdGenerator;
use App\Domain\Archive\Services\ArchiveStoragePath;
use App\Domain\Derivatives\Actions\GeneratePhotoViewingDerivatives;
use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Media\Enums\DateConfidence;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Enums\SensitivityStatus;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Processing\Models\PhotoSplitProposal;
use App\Domain\Processing\Models\PhotoSplitRegion;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class PhotoSplitReviewService
{
    public function __construct(
        private MultiPhotoLayoutDetector $detector,
        private PhotoSplitCandidateRenderer $candidateRenderer,
        private ArchiveIdGenerator $archiveIds,
        private ArchiveStoragePath $paths,
        private GeneratePhotoViewingDerivatives $derivatives,
    ) {}

    public function analyzeItem(int $itemId, User $actor, bool $createWhenUndetected = false): ?PhotoSplitProposal
    {
        $existing = PhotoSplitProposal::query()->with(['regions.candidateVersion', 'sourceVersion'])->where('cloud_import_item_id', $itemId)->first();
        if ($existing instanceof PhotoSplitProposal) {
            if ($existing->state === 'dismissed') {
                return null;
            }
            if ($existing->state !== 'suggested' || $this->detector->isHighConfidenceAnalysis($existing->analysis)) {
                return $existing;
            }

            $existing->forceFill([
                'state' => 'dismissed',
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ])->save();

            return null;
        }

        [$item, $source] = $this->itemAndSource($itemId);
        $bytes = $this->verifiedBytes($source);
        $analysis = $this->detector->analyze($bytes);
        if (! $createWhenUndetected && ! $this->detector->isHighConfidenceAnalysis($analysis)) {
            return null;
        }

        $regions = $analysis['regions'];
        if ($regions === []) {
            $regions = [
                ['x' => 0, 'y' => 0, 'width' => 5000, 'height' => 10000, 'confidence' => 0.0],
                ['x' => 5000, 'y' => 0, 'width' => 5000, 'height' => 10000, 'confidence' => 0.0],
            ];
        }

        $proposal = DB::transaction(function () use ($item, $source, $actor, $analysis, $regions): PhotoSplitProposal {
            $proposal = PhotoSplitProposal::query()->create([
                'cloud_import_item_id' => (int) data_get($item, 'id'),
                'source_version_id' => $source->id,
                'created_by' => $actor->id,
                'state' => 'suggested',
                'confidence' => $analysis['confidence'],
                'detection_method' => $analysis['method'],
                'analysis' => $analysis,
            ]);

            foreach ($regions as $index => $region) {
                $proposal->regions()->create([
                    'region_id' => (string) Str::uuid(),
                    'position' => $index + 1,
                    'x_basis_points' => $region['x'],
                    'y_basis_points' => $region['y'],
                    'width_basis_points' => $region['width'],
                    'height_basis_points' => $region['height'],
                    'rotation_degrees' => 0,
                    'confidence' => $region['confidence'],
                    'source' => 'detected',
                    'review_state' => 'included',
                ]);
            }

            return $proposal;
        });

        return $proposal->load(['regions.candidateVersion', 'sourceVersion']);
    }

    /**
     * @param  array<mixed>  $regions
     */
    public function saveRegions(PhotoSplitProposal $proposal, User $actor, array $regions): PhotoSplitProposal
    {
        if ($proposal->state === 'published') {
            throw ValidationException::withMessages(['regions' => 'Published split regions cannot be changed.']);
        }
        $maximumRegions = max(2, (int) config('archive.multi_photo.maximum_regions', 32));
        if (! array_is_list($regions) || count($regions) < 2 || count($regions) > $maximumRegions) {
            throw ValidationException::withMessages([
                'regions' => "Define between 2 and {$maximumRegions} photos.",
            ]);
        }

        $normalized = [];
        foreach ($regions as $region) {
            $normalized[] = $this->normalizeRegion($region);
        }

        $source = $proposal->sourceVersion()->firstOrFail();
        $sourceBytes = $this->verifiedBytes($source);
        $keptIds = [];

        DB::transaction(function () use ($proposal, $normalized, $source, $sourceBytes, $actor, &$keptIds): void {
            // Move existing positions out of the active range before applying a
            // reordered/manual layout, avoiding transient unique-key clashes.
            $proposal->regions()->update([
                'position' => DB::raw('position + 100'),
            ]);

            foreach ($normalized as $index => $input) {
                $regionUuid = isset($input['region_id']) && Str::isUuid($input['region_id'])
                    ? $input['region_id']
                    : (string) Str::uuid();
                $region = PhotoSplitRegion::query()->firstOrNew([
                    'photo_split_proposal_id' => $proposal->id,
                    'region_id' => $regionUuid,
                ]);
                $region->fill([
                    'position' => $index + 1,
                    'x_basis_points' => $input['x'],
                    'y_basis_points' => $input['y'],
                    'width_basis_points' => $input['width'],
                    'height_basis_points' => $input['height'],
                    'rotation_degrees' => $input['rotation_degrees'],
                    'confidence' => 1.0,
                    'source' => 'manual',
                    'review_state' => $input['included'] ? 'included' : 'excluded',
                ])->save();

                if ($input['included']) {
                    $candidate = $this->renderCandidate($proposal, $region, $source, $sourceBytes);
                    $region->forceFill(['candidate_version_id' => $candidate->id])->save();
                }
                $keptIds[] = $region->id;
            }

            $proposal->regions()->whereNotIn('id', $keptIds)->delete();
            $proposal->forceFill([
                'state' => 'ready',
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ])->save();
        });

        DB::table('cloud_import_items')->where('id', $proposal->cloud_import_item_id)->update([
            'attention_code' => 'multi_photo_ready',
            'updated_at' => now(),
        ]);

        return $proposal->fresh(['regions.candidateVersion', 'sourceVersion']);
    }

    /** @return list<MediaItem> */
    public function publish(PhotoSplitProposal $proposal, User $actor): array
    {
        if ($proposal->state !== 'ready') {
            throw ValidationException::withMessages(['items' => 'Review and save the split regions before publishing them.']);
        }

        $proposal->load(['regions.candidateVersion', 'sourceVersion.mediaItem']);
        $included = $proposal->regions->where('review_state', 'included')->filter(fn (PhotoSplitRegion $region): bool => $region->candidateVersion instanceof MediaFileVersion);
        if ($included->count() < 2) {
            throw ValidationException::withMessages(['items' => 'At least two included split photos are required.']);
        }

        $created = [];
        DB::transaction(function () use ($proposal, $included, $actor, &$created): void {
            foreach ($included as $region) {
                if ($region->output_media_item_id !== null) {
                    $created[] = $region->outputMediaItem()->firstOrFail();

                    continue;
                }

                $candidate = $region->candidateVersion;
                $bytes = $this->verifiedBytes($candidate);
                $archiveId = $this->archiveIds->allocate(MediaType::Photo);
                $target = $this->paths->derivative(MediaFileVersionType::EditedFull, MediaType::Photo, $archiveId, 'webp', 'split');
                /** @var FilesystemAdapter $targetDisk */
                $targetDisk = Storage::disk($target['disk']->value);
                if ($targetDisk->exists($target['path'])) {
                    throw new RuntimeException('The split derivative target already exists.');
                }
                $targetDisk->put($target['path'], $bytes);

                $item = MediaItem::query()->create([
                    'archive_id' => $archiveId,
                    'media_type' => MediaType::Photo,
                    'title' => 'Split photo '.$region->position,
                    'description' => 'Individually reviewed photo derived from a preserved multi-photo source.',
                    'date_confidence' => DateConfidence::Unknown,
                    'visibility' => MediaVisibility::PrivateArchive,
                    'review_status' => MediaReviewStatus::Approved,
                    'sensitivity_status' => SensitivityStatus::NotFlagged,
                    'created_by' => $actor->id,
                    'approved_by' => $actor->id,
                    'approved_at' => now(),
                ]);
                MediaFileVersion::query()->create([
                    'media_item_id' => $item->id,
                    'parent_version_id' => $proposal->source_version_id,
                    'version_type' => MediaFileVersionType::EditedFull,
                    'storage_disk' => $target['disk']->value,
                    'storage_path' => $target['path'],
                    'mime_type' => 'image/webp',
                    'extension' => 'webp',
                    'file_size_bytes' => strlen($bytes),
                    'width' => $candidate->width,
                    'height' => $candidate->height,
                    'sha256' => hash('sha256', $bytes),
                    'generation_status' => GenerationStatus::Ready,
                    'generation_recipe' => [
                        'operation' => 'multi_photo_split',
                        'proposal_id' => $proposal->id,
                        'region_id' => $region->region_id,
                        'source_sha256' => $proposal->sourceVersion->sha256,
                        'bounds_basis_points' => $this->regionArray($region),
                        'candidate_pipeline' => $candidate->generation_recipe,
                    ],
                    'is_preferred' => true,
                ]);
                $region->forceFill(['output_media_item_id' => $item->id])->save();
                $created[] = $item;
            }

            $sourceItem = $proposal->sourceVersion->mediaItem;
            if ($sourceItem instanceof MediaItem) {
                $sourceItem->forceFill([
                    'review_status' => MediaReviewStatus::Hidden,
                    'approved_by' => null,
                    'approved_at' => null,
                ])->save();
            }
            $proposal->forceFill([
                'state' => 'published',
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ])->save();
        });

        foreach ($created as $item) {
            $this->derivatives->handle($item->fresh(), $actor);
        }

        return $created;
    }

    private function renderCandidate(PhotoSplitProposal $proposal, PhotoSplitRegion $region, MediaFileVersion $source, string $bytes): MediaFileVersion
    {
        $dimensions = @getimagesizefromstring($bytes);
        if (! is_array($dimensions)) {
            throw new RuntimeException('The immutable source could not be decoded for split rendering.');
        }
        $sourceWidth = (int) $dimensions[0];
        $sourceHeight = (int) $dimensions[1];
        $x = (int) floor($sourceWidth * ($region->x_basis_points / 10000));
        $y = (int) floor($sourceHeight * ($region->y_basis_points / 10000));
        $width = max(1, (int) ceil($sourceWidth * ($region->width_basis_points / 10000)));
        $height = max(1, (int) ceil($sourceHeight * ($region->height_basis_points / 10000)));
        $width = min($width, $sourceWidth - $x);
        $height = min($height, $sourceHeight - $y);
        $rendered = $this->candidateRenderer->render(
            $bytes,
            $x,
            $y,
            $width,
            $height,
            $region->rotation_degrees,
        );
        $output = $rendered->bytes;

        $path = 'split-candidates/'.substr($source->sha256, 0, 12).'/proposal-'.$proposal->id.'/'.$region->region_id.'-'.Str::uuid().'.webp';
        Storage::disk('archive_derivatives')->put($path, $output);

        return MediaFileVersion::query()->create([
            'media_item_id' => $source->media_item_id,
            'parent_version_id' => $source->id,
            'version_type' => MediaFileVersionType::EditedFull,
            'storage_disk' => 'archive_derivatives',
            'storage_path' => $path,
            'mime_type' => 'image/webp',
            'extension' => 'webp',
            'file_size_bytes' => strlen($output),
            'width' => $rendered->width,
            'height' => $rendered->height,
            'sha256' => hash('sha256', $output),
            'generation_status' => GenerationStatus::Ready,
            'generation_recipe' => [
                'operation' => 'multi_photo_split_candidate',
                'proposal_id' => $proposal->id,
                'region_id' => $region->region_id,
                'source_sha256' => $source->sha256,
                'bounds_basis_points' => $this->regionArray($region),
                ...$rendered->recipe,
            ],
            'is_preferred' => false,
        ]);
    }

    /** @return array{0:object,1:MediaFileVersion} */
    private function itemAndSource(int $itemId): array
    {
        $item = DB::table('cloud_import_items')->where('id', $itemId)->first();
        if ($item === null) {
            throw new RuntimeException('The batch item could not be found.');
        }
        $upload = IncomingUpload::query()->with('archivePromotion.originalVersion')->whereKey(data_get($item, 'incoming_upload_id'))->first();
        $source = $upload?->archivePromotion?->originalVersion;
        if (! $source instanceof MediaFileVersion) {
            throw ValidationException::withMessages(['item' => 'Prepare the immutable original before reviewing photo splits.']);
        }

        return [$item, $source];
    }

    private function verifiedBytes(MediaFileVersion $version): string
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($version->storage_disk);
        if (! $disk->exists($version->storage_path)) {
            throw new RuntimeException('The source bytes are unavailable.');
        }
        $bytes = $disk->get($version->storage_path);
        if (strlen($bytes) !== $version->file_size_bytes || ! hash_equals(strtolower($version->sha256), hash('sha256', $bytes))) {
            throw new RuntimeException('The source failed integrity verification.');
        }

        return $bytes;
    }

    /** @return array{x:int,y:int,width:int,height:int,rotation_degrees:int,included:bool,region_id?:string} */
    private function normalizeRegion(mixed $region): array
    {
        if (! is_array($region)) {
            throw ValidationException::withMessages(['regions' => 'Every split region needs valid bounds.']);
        }
        foreach (['x', 'y', 'width', 'height'] as $key) {
            if (! array_key_exists($key, $region) || ! is_int($region[$key])) {
                throw ValidationException::withMessages(['regions' => 'Every split region needs numeric bounds.']);
            }
        }
        if ($region['x'] < 0 || $region['y'] < 0 || $region['width'] < 250 || $region['height'] < 250
            || $region['x'] + $region['width'] > 10000 || $region['y'] + $region['height'] > 10000) {
            throw ValidationException::withMessages(['regions' => 'Split regions must stay inside the original and retain a usable size.']);
        }

        $rotation = $region['rotation_degrees'] ?? 0;
        if (! is_int($rotation) || ! in_array($rotation, [0, 90, 180, 270], true)) {
            throw ValidationException::withMessages(['regions' => 'Photo rotation must be 0, 90, 180 or 270 degrees.']);
        }

        $normalized = [
            'x' => $region['x'],
            'y' => $region['y'],
            'width' => $region['width'],
            'height' => $region['height'],
            'rotation_degrees' => $rotation,
            'included' => ($region['included'] ?? false) === true,
        ];
        if (isset($region['region_id']) && is_string($region['region_id'])) {
            $normalized['region_id'] = $region['region_id'];
        }

        return $normalized;
    }

    /** @return array{x:int,y:int,width:int,height:int,rotation_degrees:int} */
    private function regionArray(PhotoSplitRegion $region): array
    {
        return [
            'x' => $region->x_basis_points,
            'y' => $region->y_basis_points,
            'width' => $region->width_basis_points,
            'height' => $region->height_basis_points,
            'rotation_degrees' => $region->rotation_degrees,
        ];
    }
}
