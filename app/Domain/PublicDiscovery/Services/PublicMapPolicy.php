<?php

namespace App\Domain\PublicDiscovery\Services;

use InvalidArgumentException;

final class PublicMapPolicy
{
    /** @return array{latitude: float, longitude: float, precision: string} */
    public function protect(float $latitude, float $longitude, string $precision = 'town'): array
    {
        if (! in_array($precision, ['neighbourhood', 'town', 'region'], true)) {
            throw new InvalidArgumentException('Public map points may not expose exact archive coordinates.');
        }

        $decimals = match ($precision) {
            'neighbourhood' => 3,
            'town' => 2,
            default => 1,
        };

        return [
            'latitude' => round($latitude, $decimals),
            'longitude' => round($longitude, $decimals),
            'precision' => $precision,
        ];
    }
}
