<?php

namespace App\Domain\Processing\Services;

use App\Domain\Archive\Services\ArchiveIdGenerator;
use App\Domain\Archive\Services\ArchiveStoragePath;
use App\Domain\Derivatives\Actions\GeneratePhotoViewingDerivatives;
use App\Domain\Derivatives\Contracts\NoOverwriteDerivativeWriter;
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
use App\Domain\Processing\ValueObjects\RenderedSplitPhoto;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class PhotoSplitReviewService
{
    public function __construct(
        private MultiPhotoLayoutDetector $detector,
        private PhotoSplitCandidateRenderer $candidateRenderer,
        private ArchiveIdGenerator $archiveIds,
        private ArchiveStoragePath $paths,
        private GeneratePhotoViewingDerivatives $derivatives,
        private NoOverwriteDerivativeWriter $writer,
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
        return $this->saveRegionsCheckpointed($proposal, $actor, $regions, PHP_INT_MAX);
    }

    /**
     * Persist reviewed geometry once and render at most the requested number
     * of missing candidates. Repeated calls resume deterministic region IDs.
     *
     * @param  array<mixed>  $regions
     */
    public function saveRegionsCheckpointed(
        PhotoSplitProposal $proposal,
        User $actor,
        array $regions,
        int $maximumCandidates = 1,
    ): PhotoSplitProposal {
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

        DB::transaction(function () use ($proposal, $normalized, $actor, &$keptIds): void {
            // Move existing positions out of the active range before applying a
            // reordered/manual layout, avoiding transient unique-key clashes.
            $proposal->regions()->update([
                'position' => DB::raw('position + 100'),
            ]);

            foreach ($normalized as $index => $input) {
                $regionUuid = isset($input['region_id']) && Str::isUuid($input['region_id'])
                    ? $input['region_id']
                    : $this->deterministicRegionUuid($proposal, $input, $index + 1);
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
                $keptIds[] = $region->id;
            }

            $proposal->regions()->whereNotIn('id', $keptIds)->delete();
            $proposal->forceFill([
                'state' => 'suggested',
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ])->save();
        });

        $maximumCandidates = max(1, $maximumCandidates);
        $missing = $proposal->regions()
            ->where('review_state', 'included')
            ->whereNull('candidate_version_id')
            ->orderBy('position')
            ->limit($maximumCandidates)
            ->get();
        foreach ($missing as $region) {
            $candidate = $this->renderCandidate($proposal, $region, $source, $sourceBytes);
            $region->forceFill(['candidate_version_id' => $candidate->id])->save();
        }

        $remaining = $proposal->regions()
            ->where('review_state', 'included')
            ->whereNull('candidate_version_id')
            ->count();
        if ($remaining === 0) {
            $proposal->forceFill(['state' => 'ready'])->save();
            DB::table('cloud_import_items')->where('id', $proposal->cloud_import_item_id)->update([
                'attention_code' => 'multi_photo_ready',
                'updated_at' => now(),
            ]);
        }

        return $proposal->fresh(['regions.candidateVersion', 'sourceVersion']);
    }

    /** @return list<MediaItem> */
    public function publish(
        PhotoSplitProposal $proposal,
        User $actor,
        MediaVisibility $visibility = MediaVisibility::PrivateArchive,
    ): array {
        if ($proposal->state !== 'ready') {
            throw ValidationException::withMessages(['items' => 'Review and save the split regions before publishing them.']);
        }

        $proposal->load(['regions.candidateVersion', 'sourceVersion.mediaItem']);
        $included = $proposal->regions->where('review_state', 'included')->filter(fn (PhotoSplitRegion $region): bool => $region->candidateVersion instanceof MediaFileVersion);
        if ($included->count() < 2) {
            throw ValidationException::withMessages(['items' => 'At least two included split photos are required.']);
        }

        $created = [];
        $writtenTargets = [];
        try {
            DB::transaction(function () use ($proposal, $included, $actor, $visibility, &$created, &$writtenTargets): void {
                foreach ($included as $region) {
                    if ($region->output_media_item_id !== null) {
                        $created[] = $region->outputMediaItem()->firstOrFail();

                        continue;
                    }

                    $candidate = $region->candidateVersion;
                    $bytes = $this->verifiedBytes($candidate);
                    $archiveId = $this->archiveIds->allocate(MediaType::Photo);
                    $target = $this->paths->derivative(MediaFileVersionType::EditedFull, MediaType::Photo, $archiveId, 'webp', 'split');
                    if ($target['disk']->value !== 'archive_derivatives') {
                        throw new RuntimeException('The split derivative target is outside archive_derivatives.');
                    }
                    $writtenObject = $this->writer->write($target['path'], $bytes);
                    $writtenTargets[] = $writtenObject;

                    $item = MediaItem::query()->create([
                        'archive_id' => $archiveId,
                        'media_type' => MediaType::Photo,
                        'title' => 'Split photo '.$region->position,
                        'description' => 'Individually reviewed photo derived from a preserved multi-photo source.',
                        'date_confidence' => DateConfidence::Unknown,
                        'visibility' => $visibility,
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
                        'file_size_bytes' => $writtenObject->bytes,
                        'width' => $candidate->width,
                        'height' => $candidate->height,
                        'sha256' => $writtenObject->sha256,
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
        } catch (Throwable $exception) {
            foreach ($writtenTargets as $target) {
                $this->writer->removeCreated($target);
            }

            throw $exception;
        }

        foreach ($created as $item) {
            $this->derivatives->handle($item->fresh(), $actor);
        }

        return $created;
    }

    private function renderCandidate(
        PhotoSplitProposal $proposal,
        PhotoSplitRegion $region,
        MediaFileVersion $source,
        string $bytes,
        ?RenderedSplitPhoto $rendered = null,
    ): MediaFileVersion {
        if (! $rendered instanceof RenderedSplitPhoto) {
            $dimensions = @getimagesizefromstring($bytes);
            if (! is_array($dimensions)) {
                throw new RuntimeException('The immutable source could not be decoded for split rendering.');
            }
            $pixelRegion = $this->pixelRegion($this->regionArray($region), (int) $dimensions[0], (int) $dimensions[1]);
            $rendered = $this->candidateRenderer->render(
                $bytes,
                $pixelRegion['x'],
                $pixelRegion['y'],
                $pixelRegion['width'],
                $pixelRegion['height'],
                $pixelRegion['rotation_degrees'],
            );
        }
        $output = $rendered->bytes;
        $outputSha256 = hash('sha256', $output);
        $renderKey = hash('sha256', json_encode([
            'source_sha256' => $source->sha256,
            'proposal_id' => $proposal->id,
            'region_id' => $region->region_id,
            'bounds' => $this->regionArray($region),
            'renderer_recipe' => $rendered->recipe,
        ], JSON_THROW_ON_ERROR));
        $path = 'split-candidates/'.substr($source->sha256, 0, 12).'/proposal-'.$proposal->id.'/'.$region->region_id.'-'.substr($renderKey, 0, 24).'.webp';
        $existing = MediaFileVersion::query()
            ->where('storage_disk', 'archive_derivatives')
            ->where('storage_path', $path)
            ->first();
        if ($existing instanceof MediaFileVersion) {
            $this->verifiedBytes($existing);

            if (! hash_equals($outputSha256, strtolower($existing->sha256))) {
                throw new RuntimeException('The deterministic split candidate does not match its existing record.');
            }

            return $existing;
        }

        $writtenObject = $this->writer->write($path, $output);
        try {
            return MediaFileVersion::query()->create([
                'media_item_id' => $source->media_item_id,
                'parent_version_id' => $source->id,
                'version_type' => MediaFileVersionType::EditedFull,
                'storage_disk' => 'archive_derivatives',
                'storage_path' => $path,
                'mime_type' => 'image/webp',
                'extension' => 'webp',
                'file_size_bytes' => $writtenObject->bytes,
                'width' => $rendered->width,
                'height' => $rendered->height,
                'sha256' => $writtenObject->sha256,
                'generation_status' => GenerationStatus::Ready,
                'generation_recipe' => [
                    'operation' => 'multi_photo_split_candidate',
                    'proposal_id' => $proposal->id,
                    'region_id' => $region->region_id,
                    'source_sha256' => $source->sha256,
                    'render_key' => $renderKey,
                    'bounds_basis_points' => $this->regionArray($region),
                    ...$rendered->recipe,
                ],
                'is_preferred' => false,
            ]);
        } catch (Throwable $exception) {
            $this->writer->removeCreated($writtenObject);

            throw $exception;
        }
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

    /**
     * @param  array{x:int,y:int,width:int,height:int,rotation_degrees:int}  $region
     * @return array{x:int,y:int,width:int,height:int,rotation_degrees:int}
     */
    private function pixelRegion(array $region, int $sourceWidth, int $sourceHeight): array
    {
        $x = (int) floor($sourceWidth * ($region['x'] / 10000));
        $y = (int) floor($sourceHeight * ($region['y'] / 10000));
        $width = max(1, (int) ceil($sourceWidth * ($region['width'] / 10000)));
        $height = max(1, (int) ceil($sourceHeight * ($region['height'] / 10000)));

        return [
            'x' => $x,
            'y' => $y,
            'width' => min($width, $sourceWidth - $x),
            'height' => min($height, $sourceHeight - $y),
            'rotation_degrees' => $region['rotation_degrees'],
        ];
    }

    /** @param array{x:int,y:int,width:int,height:int,rotation_degrees:int,included:bool,region_id?:string} $region */
    private function deterministicRegionUuid(PhotoSplitProposal $proposal, array $region, int $position): string
    {
        $hex = hash('sha256', json_encode([
            'namespace' => 'familyarchive-reviewed-split-region-v1',
            'proposal_id' => $proposal->id,
            'position' => $position,
            'x' => $region['x'],
            'y' => $region['y'],
            'width' => $region['width'],
            'height' => $region['height'],
            'rotation_degrees' => $region['rotation_degrees'],
            'included' => $region['included'],
        ], JSON_THROW_ON_ERROR));
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20, 12);
    }
}
