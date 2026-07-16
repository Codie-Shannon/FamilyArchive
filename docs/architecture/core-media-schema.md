# Core Media Schema

## Group 02 scope

Group 02 establishes the archive-grade record skeleton only. It does not ingest,
write, resize, hash or delete real media.

## Core records

### MediaItem

`MediaItem` is the stable archive record. It stores archive identity, descriptive
metadata, date confidence, visibility, review state, sensitivity state and
approval audit fields.

It deliberately stores no original, web-display or thumbnail path. File storage
belongs to `MediaFileVersion`.

### IncomingUpload

`IncomingUpload` is the intake record. It can exist without approval and without
a linked `MediaItem`. It records fictional source facts, technical metadata,
integrity values, workflow status, retention state and review audit fields.

### MediaFileVersion

`MediaFileVersion` stores each original or derivative as a separate record. Its unique indexed storage path is bounded to 768 characters so the contract remains compatible with common MySQL `utf8mb4` index limits.
`parent_version_id` records derivative lineage. The original, web-display and
thumbnail records are never collapsed into one mutable path.

## Relationship map

```text
User (uploader) ----< IncomingUpload >---- optional MediaItem
User (reviewer) ----/

User (creator) -----< MediaItem >---------< MediaFileVersion
User (approver) ----/                           |
                                               +---- parent MediaFileVersion
```

## Preservation rules

- Media and parent-version foreign keys use restrictive deletion behavior.
- No relationship cascade-deletes an original version.
- Approved originals are represented independently from rebuildable derivatives.
- Demo records use fictional IDs and relative `demo/` paths only.
- The Group 02 schema overview is Owner-only and read-only.
- The access-boundary view exposes the active `auth`, `verified` and `owner` route middleware without adding mutation actions.

## Migration safety

The three Group 02 migrations are forward-only. Pack validation proves them from
an empty disposable SQLite database. The local archive database receives normal
forward migration only; the Group 02 runner never uses rollback or destructive
schema commands against it.
