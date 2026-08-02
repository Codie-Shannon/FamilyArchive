<?php

namespace App\Domain\Communication\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $thread_id
 * @property int $user_id
 * @property Carbon|null $last_read_at
 * @property Carbon|null $muted_at
 * @property Carbon|null $archived_at
 * @property Carbon|null $blocked_at
 */
class FamilyMessageParticipantSetting extends Model
{
    protected $fillable = ['thread_id', 'user_id', 'last_read_at', 'muted_at', 'archived_at', 'blocked_at'];

    protected function casts(): array
    {
        return [
            'last_read_at' => 'datetime',
            'muted_at' => 'datetime',
            'archived_at' => 'datetime',
            'blocked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<FamilyMessageThread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(FamilyMessageThread::class, 'thread_id');
    }
}
