<?php

namespace App\Domain\Archive\Enums;

enum ArchiveStorageDisk: string
{
    case Originals = 'archive_originals';
    case Derivatives = 'archive_derivatives';
    case Quarantine = 'archive_quarantine';
    case Manifests = 'archive_manifests';
}
