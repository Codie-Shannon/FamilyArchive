<?php

namespace App\Domain\Intake\Services;

use App\Domain\Intake\Models\IncomingUpload;
use Illuminate\Support\Str;

final class IncomingUploadIdGenerator
{
    public function generate(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $id = 'UP_'.strtoupper((string) Str::ulid());
            if (! IncomingUpload::query()->where('upload_id', $id)->exists()) {
                return $id;
            }
        }
        throw new \RuntimeException('Unable to allocate a unique incoming upload ID.');
    }
}
