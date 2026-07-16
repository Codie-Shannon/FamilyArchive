<?php

namespace App\Domain\Intake\Enums;

enum IncomingProcessingStatus: string
{
    case Pending = 'pending';
    case Validating = 'validating';
    case Processing = 'processing';
    case Processed = 'processed';
    case Failed = 'failed';
    case Quarantined = 'quarantined';
}
