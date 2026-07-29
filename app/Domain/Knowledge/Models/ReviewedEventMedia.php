<?php

namespace App\Domain\Knowledge\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property string $confidence
 * @property string $source_note
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 */
final class ReviewedEventMedia extends Pivot
{
    protected $table = 'archive_event_media';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'immutable_datetime',
        ];
    }
}
