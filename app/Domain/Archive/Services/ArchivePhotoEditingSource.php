<?php

namespace App\Domain\Archive\Services;

use App\Domain\Derivatives\Services\ApprovedPhotoViewingSource;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use Illuminate\Validation\ValidationException;

final class ArchivePhotoEditingSource
{
    public function __construct(private ApprovedPhotoViewingSource $sources) {}

    public function current(MediaItem $item): MediaFileVersion
    {
        $source = $this->sources->resolve($item);
        if (! $source instanceof MediaFileVersion) {
            throw ValidationException::withMessages(['editor' => 'No verified full-resolution editing source is available.']);
        }

        return $source;
    }

    public function original(MediaItem $item): MediaFileVersion
    {
        $source = $this->current($item);
        $visited = [];
        for ($depth = 0; $depth < 32; $depth++) {
            if ($source->version_type === MediaFileVersionType::Original
                && $source->generation_status === GenerationStatus::Ready) {
                return $source;
            }
            if ($source->parent_version_id === null || isset($visited[$source->id])) {
                break;
            }
            $visited[$source->id] = true;
            $source = MediaFileVersion::query()->findOrFail($source->parent_version_id);
        }

        throw ValidationException::withMessages(['editor' => 'This photo is not linked to a preserved original source.']);
    }

    public function resolve(MediaItem $item, string $basis): MediaFileVersion
    {
        if (! in_array($basis, ['current', 'original'], true)) {
            throw ValidationException::withMessages(['source_basis' => 'Choose the current correction or the preserved original.']);
        }

        return $basis === 'original' ? $this->original($item) : $this->current($item);
    }
}
