<?php

namespace App\Domain\Processing\Models;

use App\Domain\Media\Models\MediaFileVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $cloud_import_item_id
 * @property int $source_version_id
 * @property string $state
 * @property float $confidence
 * @property string $detection_method
 * @property array<string, mixed> $analysis
 */
#[Fillable([
    'cloud_import_item_id',
    'source_version_id',
    'created_by',
    'reviewed_by',
    'state',
    'confidence',
    'detection_method',
    'analysis',
    'reviewed_at',
])]
final class PhotoSplitProposal extends Model
{
    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'analysis' => 'array',
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<MediaFileVersion, $this> */
    public function sourceVersion(): BelongsTo
    {
        return $this->belongsTo(MediaFileVersion::class, 'source_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<PhotoSplitRegion, $this> */
    public function regions(): HasMany
    {
        return $this->hasMany(PhotoSplitRegion::class)->orderBy('position');
    }
}
