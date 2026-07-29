<?php

namespace App\Domain\Knowledge\Presenters;

use App\Domain\Knowledge\Models\ArchiveEvent;
use App\Domain\Media\Enums\DatePrecision;

final class ArchiveEventDatePresenter
{
    public function display(ArchiveEvent $event): string
    {
        return match ($event->date_precision) {
            DatePrecision::Exact => $this->exactRange($event),
            DatePrecision::Approximate => 'About '.$this->exactRange($event),
            DatePrecision::YearOnly => (string) $event->date_year,
            DatePrecision::DecadeOnly => $event->estimated_decade.'s',
            DatePrecision::Unknown => 'Date unknown',
        };
    }

    private function exactRange(ArchiveEvent $event): string
    {
        $start = $event->starts_on?->format('j M Y') ?? 'Date unknown';

        return $event->ends_on === null
            ? $start
            : $start.' – '.$event->ends_on->format('j M Y');
    }
}
