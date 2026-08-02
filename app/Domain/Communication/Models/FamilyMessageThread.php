<?php

namespace App\Domain\Communication\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $thread_id
 * @property int $user_one_id
 * @property int $user_two_id
 * @property int $started_by_user_id
 * @property Carbon|null $last_message_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FamilyMessageThread extends Model
{
    protected $fillable = ['thread_id', 'user_one_id', 'user_two_id', 'started_by_user_id', 'last_message_at'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    /** @return BelongsTo<User, $this> */
    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    /** @return HasMany<FamilyMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(FamilyMessage::class, 'thread_id');
    }

    /** @return HasMany<FamilyMessageParticipantSetting, $this> */
    public function settings(): HasMany
    {
        return $this->hasMany(FamilyMessageParticipantSetting::class, 'thread_id');
    }
}
