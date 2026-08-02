<?php

namespace App\Domain\CloudImport\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $qualification_id
 * @property int $user_id
 * @property string $state
 * @property int $target_count
 * @property int $chunk_size
 * @property int $completed_count
 * @property int $checkpoint_count
 * @property int $isolated_failures
 * @property int $recovered_failures
 * @property int $duplicate_skips
 * @property int $restart_count
 * @property string $manifest_sha256
 * @property string|null $reconciliation_sha256
 * @property array<string, mixed> $qualification_profile
 * @property Carbon|null $started_at
 * @property Carbon|null $last_checkpoint_at
 * @property Carbon|null $completed_at
 */
#[Fillable([
    'qualification_id', 'user_id', 'state', 'target_count', 'chunk_size',
    'completed_count', 'checkpoint_count', 'isolated_failures', 'recovered_failures',
    'duplicate_skips', 'restart_count', 'manifest_sha256', 'reconciliation_sha256',
    'qualification_profile', 'started_at', 'last_checkpoint_at', 'completed_at',
])]
final class MigrationQualificationRun extends Model
{
    protected function casts(): array
    {
        return [
            'qualification_profile' => 'array',
            'started_at' => 'immutable_datetime',
            'last_checkpoint_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
