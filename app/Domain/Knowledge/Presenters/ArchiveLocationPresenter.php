<?php

namespace App\Domain\Knowledge\Presenters;

use App\Domain\Knowledge\Enums\LocationPrecision;
use App\Domain\Knowledge\Models\ArchiveLocation;

final class ArchiveLocationPresenter
{
    public function browseLabel(ArchiveLocation $location): string
    {
        if ($location->is_sensitive || $location->precision === LocationPrecision::Private) {
            return 'Private family location';
        }

        return $location->label;
    }

    public function browseSubtitle(ArchiveLocation $location): ?string
    {
        if ($location->is_sensitive || $location->precision === LocationPrecision::Private) {
            return null;
        }

        return $location->subtitle;
    }

    public function browseDetail(ArchiveLocation $location): string
    {
        if ($location->is_sensitive || $location->precision === LocationPrecision::Private) {
            return 'Exact location details are withheld from archive browsing.';
        }

        if ($location->address !== null && $location->address !== '') {
            return $location->address;
        }

        return collect([
            $location->locality,
            $location->region,
            $location->country_code,
        ])->filter()->implode(', ');
    }
}
