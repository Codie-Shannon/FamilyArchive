<?php

namespace App\Domain\Intake\Models;

use App\Domain\Intake\Enums\DuplicateStatus;
use App\Domain\Intake\Enums\IncomingProcessingStatus;
use App\Domain\Intake\Enums\IncomingReviewStatus;
use App\Domain\Media\Models\MediaItem;
use App\Models\User;
use Database\Factories\IncomingUploadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $upload_id
 * @property int $uploader_id
 * @property int|null $reviewed_by
 * @property int|null $media_item_id
 * @property string $original_filename
 * @property string|null $incoming_path
 * @property string $mime_type
 * @property string|null $extension
 * @property int $file_size_bytes
 * @property int|null $width
 * @property int|null $height
 * @property int|null $duration_ms
 * @property string $sha256
 * @property string|null $perceptual_hash
 * @property IncomingProcessingStatus $processing_status
 * @property IncomingReviewStatus $review_status
 * @property DuplicateStatus $duplicate_status
 * @property bool $source_file_retained
 * @property Carbon|null $retained_at
 * @property Carbon|null $source_file_removed_at
 * @property Carbon|null $submitted_at
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'upload_id',
    'uploader_id',
    'reviewed_by',
    'media_item_id',
    'original_filename',
    'incoming_path',
    'mime_type',
    'extension',
    'file_size_bytes',
    'width',
    'height',
    'duration_ms',
    'sha256',
    'perceptual_hash',
    'processing_status',
    'review_status',
    'duplicate_status',
    'source_file_retained',
    'source_file_removed_at',
    'submitted_at',
    'reviewed_at',
])]
class IncomingUpload extends Model
{
    /** @use HasFactory<IncomingUploadFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration_ms' => 'integer',
            'processing_status' => IncomingProcessingStatus::class,
            'review_status' => IncomingReviewStatus::class,
            'duplicate_status' => DuplicateStatus::class,
            'source_file_retained' => 'boolean',
            'retained_at' => 'immutable_datetime',
            'source_file_removed_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return BelongsTo<MediaItem, $this>
     */
    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class);
    }

    protected static function newFactory(): IncomingUploadFactory
    {
        return IncomingUploadFactory::new();
    }
}
