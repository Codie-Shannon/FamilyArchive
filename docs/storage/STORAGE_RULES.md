# Storage Rules

## Original Storage

Originals are permanent and checksum protected.

## Derived Storage

Web and thumbnail files are optimized for normal browsing.

Derived files are rebuildable.

A redundant edited-full copy should not be generated unless an actual edited or
restored version exists.

## Storage Pressure Cleanup Order

1. Temporary processing files
2. Failed unattached uploads
3. Oldest rejected duplicate source files
4. Rebuildable thumbnails
5. Rebuildable web versions
6. Other rebuildable derived versions

Approved originals are never automatically removed.