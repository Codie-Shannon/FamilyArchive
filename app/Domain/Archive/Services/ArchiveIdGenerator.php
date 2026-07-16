<?php

namespace App\Domain\Archive\Services;

use App\Domain\Archive\Models\ArchiveIdSequence;
use App\Domain\Media\Enums\MediaType;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ArchiveIdGenerator
{
    public function allocate(MediaType $mediaType): string
    {
        return DB::transaction(function () use ($mediaType): string {
            $sequence = ArchiveIdSequence::query()
                ->where('media_type', $mediaType->value)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                ArchiveIdSequence::query()->create([
                    'media_type' => $mediaType,
                    'last_value' => 0,
                ]);

                $sequence = ArchiveIdSequence::query()
                    ->where('media_type', $mediaType->value)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            if ($sequence->last_value >= PHP_INT_MAX) {
                throw new RuntimeException('Archive ID sequence is exhausted.');
            }

            $next = $sequence->last_value + 1;
            $sequence->forceFill(['last_value' => $next])->save();

            return $this->format($mediaType, $next);
        }, 5);
    }

    public function format(MediaType $mediaType, int $number): string
    {
        if ($number < 1) {
            throw new RuntimeException('Archive ID numbers must be positive.');
        }

        $prefix = config("archive.prefixes.{$mediaType->value}");

        if (! is_string($prefix) || ! preg_match('/^[A-Z]{2}$/', $prefix)) {
            throw new RuntimeException("No valid archive prefix is configured for {$mediaType->value}.");
        }

        return sprintf('%s_%06d', $prefix, $number);
    }
}
