<?php

namespace App\Domain\Knowledge\Enums;

enum EventType: string
{
    case Birth = 'birth';
    case Marriage = 'marriage';
    case Death = 'death';
    case Reunion = 'reunion';
    case Migration = 'migration';
    case Education = 'education';
    case Employment = 'employment';
    case Celebration = 'celebration';
    case Custom = 'custom';
}
