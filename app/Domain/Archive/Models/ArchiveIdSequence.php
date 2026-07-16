<?php

namespace App\Domain\Archive\Models;

use App\Domain\Media\Enums\MediaType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['media_type', 'last_value'])]
class ArchiveIdSequence extends Model
{
    protected function casts(): array
    {
        return [
            'media_type' => MediaType::class,
            'last_value' => 'integer',
        ];
    }
}
