<?php

namespace App\Domain\Provenance\Enums;

enum SourceCollectionType: string
{
    case Collection = 'collection';
    case PhysicalAlbum = 'physical_album';
    case Box = 'box';
    case Envelope = 'envelope';
}
