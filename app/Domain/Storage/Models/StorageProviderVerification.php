<?php

namespace App\Domain\Storage\Models;

use Illuminate\Database\Eloquent\Model;

final class StorageProviderVerification extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'configuration_complete' => 'boolean',
            'bucket_access' => 'boolean',
            'versioning_enabled' => 'boolean',
            'object_lock_enabled' => 'boolean',
            'write_read_delete_verified' => 'boolean',
            'checked_at' => 'immutable_datetime',
        ];
    }
}
