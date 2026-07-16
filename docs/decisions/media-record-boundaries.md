# Media Record Boundaries

## Decision

Family Archive separates intake, archive metadata and stored file versions into
three records with different responsibilities.

## Boundaries

### IncomingUpload is not an approved archive record

An intake record may be pending, invalid, failed, quarantined, rejected or a
possible duplicate. It therefore exists independently and links to a
`MediaItem` only when review reaches an approved archive outcome.

### MediaItem is metadata and workflow state

`MediaItem` owns stable archive identity and accepted metadata. Direct file paths
are prohibited on this record because they would couple archive identity to one
mutable storage representation.

### MediaFileVersion owns file-version facts

Originals, edited full versions, web-display files, thumbnails and future stream
or preview forms are separate `MediaFileVersion` rows. Derivatives may point to a
parent version so provenance is explicit.

## Original-preservation consequence

Deleting a `MediaItem` cannot cascade into its file versions. Deleting a parent
version cannot cascade into derivatives. A future approved deletion workflow
must therefore make an explicit, audited decision rather than relying on model
cleanup behavior.

## Rejected alternatives

- Storing original, web and thumbnail paths directly on `MediaItem`.
- Converting `IncomingUpload` into `MediaItem` before human approval.
- Using uncontrolled free-text workflow statuses.
- Cascade-deleting original versions through parent or archive relationships.
