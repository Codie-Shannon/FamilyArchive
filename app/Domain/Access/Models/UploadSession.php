<?php

namespace App\Domain\Access\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $session_id
 * @property int $user_id
 * @property string|null $title
 * @property string|null $source_context
 * @property array<string, bool|string>|null $automation_preferences
 * @property int $expected_files
 * @property int $received_files
 * @property string $status
 * @property Carbon $expires_at
 */
#[Fillable([
    'session_id', 'user_id', 'title', 'source_context', 'automation_preferences',
    'expected_files', 'received_files', 'status', 'expires_at',
])]
final class UploadSession extends Model
{
    protected function casts(): array
    {
        return [
            'automation_preferences' => 'array',
            'expected_files' => 'integer',
            'received_files' => 'integer',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<ContributorSubmission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(ContributorSubmission::class);
    }
}
