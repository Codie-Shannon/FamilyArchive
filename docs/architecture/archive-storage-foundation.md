# Archive storage foundation

Group 03 defines storage identity and path contracts without persisting media bytes.

## Stable archive IDs

`ArchiveIdGenerator` allocates one monotonically increasing sequence per `MediaType` inside a database transaction and row lock. Prefixes are fixed in `config/archive.php`: `PH`, `VD`, `DC`, `AU`, and `OT`. The numeric component uses at least six digits and can grow beyond six digits.

## Logical private disks

The approved disks are `archive_originals`, `archive_derivatives`, `archive_quarantine`, and `archive_manifests`. They are local, private, non-serving filesystem contracts with no public URL entry or symbolic-link contract. Machine roots are intentionally absent from the Owner interface and repository evidence.

## Deterministic relative paths

`ArchiveStoragePath` creates slash-separated relative paths from stable archive IDs. Bucket selection is `intdiv(numeric_id, 1000)` with a minimum three-digit display. Originals and derivatives always use different logical disks. Quarantine and manifest methods plan paths only.

## Security boundary

`StoragePathValidator` rejects absolute paths, drive letters, backslashes, colons, null bytes, traversal or empty segments, and invalid extensions. Group 03 services expose no overwrite flag and do not call filesystem write, copy, move, delete, URL, image-processing, checksum, or media-processing APIs.
