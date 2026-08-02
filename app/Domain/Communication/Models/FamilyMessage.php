<?php

namespace App\Domain\Communication\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $message_id
 * @property int $thread_id
 * @property int $sender_user_id
 * @property string $body
 * @property string $state
 * @property int|null $reported_by_user_id
 * @property Carbon|null $reported_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FamilyMessage extends Model
{
    protected $fillable = ['message_id', 'thread_id', 'sender_user_id', 'body', 'state', 'reported_by_user_id', 'reported_at'];

    protected function casts(): array
    {
        return ['reported_at' => 'datetime'];
    }

    /** @return BelongsTo<FamilyMessageThread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(FamilyMessageThread::class, 'thread_id');
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}
