<?php

namespace App\Domain\Knowledge\Enums;

enum ArchiveAlbumType: string
{
    case Curated = 'album';
    case Event = 'event';
    case Place = 'place';
    case Person = 'person';
    case Branch = 'branch';

    public function label(): string
    {
        return match ($this) {
            self::Curated => 'Family album',
            self::Event => 'Event album',
            self::Place => 'Place album',
            self::Person => 'Person album',
            self::Branch => 'Family branch album',
        };
    }
}
