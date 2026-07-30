<?php

namespace App\Domain\Processing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $created_by
 * @property string $recipe_id
 * @property string $name
 * @property int $version
 * @property array<string, mixed> $operations
 * @property string $automation_source
 * @property bool $is_batch_profile
 * @property bool $is_active
 */
#[Fillable([
    'created_by',
    'recipe_id',
    'name',
    'version',
    'operations',
    'automation_source',
    'is_batch_profile',
    'is_active',
])]
final class ProcessingRecipe extends Model
{
    protected function casts(): array
    {
        return [
            'operations' => 'array',
            'version' => 'integer',
            'is_batch_profile' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
