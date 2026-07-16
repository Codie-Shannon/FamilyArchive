<?php

namespace App\Domain\Media\Enums;

enum GenerationStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
    case NotRequired = 'not_required';
}
