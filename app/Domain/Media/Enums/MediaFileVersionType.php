<?php

namespace App\Domain\Media\Enums;

enum MediaFileVersionType: string
{
    case Original = 'original';
    case EditedFull = 'edited_full';
    case WebDisplay = 'web_display';
    case Thumbnail = 'thumbnail';
    case VideoStream = 'video_stream';
    case VideoPreview = 'video_preview';
    case DocumentPreview = 'document_preview';
}
