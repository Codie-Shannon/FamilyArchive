<?php

namespace App\Domain\Processing\Models;

use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $photo_split_proposal_id
 * @property string $region_id
 * @property int $position
 * @property int $x_basis_points
 * @property int $y_basis_points
 * @property int $width_basis_points
 * @property int $height_basis_points
 * @property string $review_state
 * @property int|null $candidate_version_id
 * @property int|null $output_media_item_id
 * @property MediaFileVersion|null $candidateVersion
 */
#[Fillable([
    'photo_split_proposal_id',
    'region_id',
    'position',
    'x_basis_points',
    'y_basis_points',
    'width_basis_points',
    'height_basis_points',
    'confidence',
    'source',
    'review_state',
    'candidate_version_id',
    'output_media_item_id',
])]
final class PhotoSplitRegion extends Model
{
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'x_basis_points' => 'integer',
            'y_basis_points' => 'integer',
            'width_basis_points' => 'integer',
            'height_basis_points' => 'integer',
            'confidence' => 'float',
        ];
    }

    /** @return BelongsTo<PhotoSplitProposal, $this> */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(PhotoSplitProposal::class, 'photo_split_proposal_id');
    }

    /** @return BelongsTo<MediaFileVersion, $this> */
    public function candidateVersion(): BelongsTo
    {
        return $this->belongsTo(MediaFileVersion::class, 'candidate_version_id');
    }

    /** @return BelongsTo<MediaItem, $this> */
    public function outputMediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class, 'output_media_item_id');
    }
}
