# Capability Inventory

Family Archive v4.0.0 supports photos through a complete preservation, review,
discovery and controlled-sharing workflow.

## Preservation and intake

- Signature, MIME, dimension and decode validation
- Private quarantine with SHA-256 and byte-count verification
- Exact-duplicate candidates and human review decisions
- Promotion to immutable, no-overwrite originals
- Resumable browser and high-volume batch intake
- Durable checkpoints, bounded retry and per-file failure isolation
- Decision-preserving recalibration of prepared review samples, so threshold
  changes can reduce false exceptions without approving or rejecting media
- Read-only preflight with relative-path hashes and capacity estimates
- Repeatable source-subtree exclusions pruned before file discovery, with no
  excluded name or path persisted and keyed policy continuity on resume
- Synthetic 30,000-entry interruption, replay and reconciliation qualification

## Restoration and derivatives

- Private WebP display and thumbnail derivatives with explicit lineage
- EXIF orientation, conservative deskew and crop suggestions
- Optional gentle tonal cleanup
- Side-by-side original and suggestion review
- Manual rotate, straighten, crop, colour, exposure, denoise, sharpen and cleanup
- Every edit recreated from the verified original and saved as a separate candidate
- Human approval before a restoration becomes preferred
- Confidence-ranked, variable-row and variable-column multi-photo scan detection
  for uneven grids with or without visible gutters
- One recommended split layout by default, with weak layouts retained as a
  single original for manual review
- Free-form manual split regions rendered from the verified immutable original
- Independently reviewable child photos with explicit source and region lineage

Perspective correction, damage reconstruction and upscaling remain unavailable
until supported processors and review contracts exist.

## Metadata, provenance and integrity

- Optimistic metadata editing with immutable revisions
- Uncertain historical dates without manufactured precision
- Stable source collections, scan batches and multiple provenance records
- Append-only integrity findings, review history and access history
- Stable reviewed identities for people, events, locations and family branches

## Discovery

- Private approved-photo gallery and detail views
- Album-centred browsing and searchable batch album membership
- Permission-aware search across reviewed archive knowledge
- Connected people, events, places and family branches behind album journeys
- Interactive public map limited to separately reviewed coarse locations
- Fallback reviewed list when the external map provider is unavailable

Living, private, sensitive and unreviewed facts are filtered before display.
Exact archive coordinates are never sent to the public map.

## Access and family operations

- Owner, administrator, trusted contributor, contributor and viewer roles
- Invite-only approval and branch-aware access
- Guided member-name access, printable one-time codes and assisted recovery
- Delegated routine account, intake and moderation decisions
- Owner-only elevated-role, original-access and policy exceptions
- Participant-controlled mute, archive and block for private conversations
- Report-specific administrator access to private message content

## Storage and production

- Private local storage for development
- Isolated Wasabi prefixes with versioned no-overwrite writes
- Exact-version readback verification and least-privilege policy generation
- Copy-first, resumable storage migration without source deletion
- Laravel Cloud production, HTTPS, health checks and restrictive response headers
- Database, cache and queue-worker production boundaries

## Deliberate limits

- The completed media workflow is for photos; video, audio and document import are
  planning models, not completed preservation pipelines.
- External providers require operator-owned accounts, billing, credentials and
  monitoring.
- Public evidence proves synthetic scenarios, not the private family migration.
- Human acceptance, custodianship and recovery drills remain operational duties.
