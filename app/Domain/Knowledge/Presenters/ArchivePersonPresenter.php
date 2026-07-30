<?php

namespace App\Domain\Knowledge\Presenters;

use App\Domain\Knowledge\Enums\PersonDatePrecision;
use App\Domain\Knowledge\Models\ArchivePerson;

final class ArchivePersonPresenter
{
    public function browseName(ArchivePerson $person): string
    {
        return $person->is_private
            ? 'Restricted person record'
            : $person->display_name;
    }

    public function lifeDates(ArchivePerson $person): string
    {
        if ($person->is_private) {
            return 'Life dates withheld';
        }

        return $this->date('Born', $person->birth_precision, $person->birth_on?->format('j M Y'), $person->birth_year, $person->birth_decade)
            .' · '
            .$this->date('Died', $person->death_precision, $person->death_on?->format('j M Y'), $person->death_year, $person->death_decade);
    }

    public function alternateNames(ArchivePerson $person): string
    {
        if ($person->is_private) {
            return 'Alternate names withheld';
        }

        return collect($person->alternate_names)->filter()->implode(', ') ?: 'No reviewed alternate names';
    }

    private function date(
        string $label,
        PersonDatePrecision $precision,
        ?string $exact,
        ?int $year,
        ?int $decade
    ): string {
        $value = match ($precision) {
            PersonDatePrecision::Exact => $exact ?? 'unknown',
            PersonDatePrecision::Approximate => 'about '.($exact ?? 'unknown'),
            PersonDatePrecision::YearOnly => (string) ($year ?? 'unknown'),
            PersonDatePrecision::DecadeOnly => ($decade ?? 'unknown').'s',
            PersonDatePrecision::Unknown => 'unknown',
        };

        return "{$label} {$value}";
    }
}
