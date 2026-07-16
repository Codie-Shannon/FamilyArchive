<?php

namespace App\Domain\Media\Enums;

enum MediaType: string
{
    case Photo = 'photo';
    case Video = 'video';
    case Document = 'document';
    case Audio = 'audio';
    case Other = 'other';
}
