# Family Archive System Overview

Family Archive is a standalone, archive-grade private family media preservation
platform. It is built as a modular Laravel monolith with preservation,
privacy, review and auditability as primary design constraints.

## Product Boundary

Family Archive is authoritative for:

- preserved media and its stable archive identity;
- original, derivative and edited-file lineage;
- integrity facts and storage coordinates;
- intake, duplicate review and archive acceptance;
- archival metadata, provenance and revision history; and
- archive-specific visibility and access decisions.

The product may integrate with other flagship systems through stable,
permission-aware interfaces. Other products must not directly manipulate
Family Archive originals, storage paths or database records.

## Current Media Scope

Photos are currently supported.

The long-term media model also reserves explicit types for:

- video;
- documents;
- audio; and
- other archive records.

Support for a media type is not complete until its validation, preservation,
derivative, review, access and recovery boundaries are implemented.

## Implemented Domain Modules

| Module | Responsibility |
|---|---|
| Intake | Technical validation, incoming identity and quarantine retention |
| Duplicates | Exact-match candidates and human-reviewed decisions |
| Archive | Stable IDs, hardened paths and verified original promotion |
| Derivatives | Rebuildable viewing versions and generation recipes |
| Browsing | Approved private gallery and detail read models |
| Metadata | Controlled descriptive edits and immutable revision history |
| Provenance | Structured historical dates, stable physical sources and scan batches |
| Media | Shared media records, versions, statuses and enums |

Future roadmap groups add structured provenance, events, locations, people,
relationships, collections, broader roles, contributor workflows, restoration,
cloud storage, integrity operations and production custodianship.

## Core Records

Implemented records include:

- `IncomingUpload`
- `MediaItem`
- `MediaFileVersion`
- `DuplicateCandidate`
- `DuplicateReviewEvent`
- `ArchivePromotion`
- `PhotoMetadataRevision`

Planned records include concepts such as people, family branches, relationships,
events, locations, sources, collections, scan batches, processing jobs,
integrity manifests and access grants. Their final schemas are introduced only
by the roadmap group that owns the capability.

## Current Photo Lifecycle

```text
Owner upload
  -> technical validation
  -> private quarantine retention
  -> SHA-256 and byte verification
  -> exact-duplicate candidate detection
  -> manual duplicate review
  -> verified original promotion
  -> private derivative generation
  -> approved archive browsing
  -> controlled metadata revision
```

The workflow does not silently infer acceptance, delete a suspected duplicate
or replace an accepted fact.

## Storage Model

Logical private disks separate:

- quarantine sources;
- accepted originals;
- rebuildable derivatives; and
- future integrity manifests.

Stable archive IDs and deterministic paths are database-backed contracts.
No-overwrite writers use exclusive creation and verify stored sizes and hashes.
Original paths never become public URLs.

## Access Model

The current archive and administration surface requires an authenticated,
verified user with the Owner role. Public registration is disabled.

The full role and policy model, controlled registration, family access,
branch-based visibility and explicit original-access grants belong to Groups
20-23. Until those groups close, Owner-only access remains the security
boundary.

## Current Build State

Groups 01-12 are completed and closed. They establish:

1. application foundation;
2. core archive schema;
3. storage identity and path contracts;
4. controlled Owner photo intake;
5. controlled quarantine persistence;
6. exact duplicate candidate detection;
7. controlled manual duplicate review;
8. archive acceptance and original promotion;
9. private viewing derivatives;
10. private archive browsing; and
11. controlled metadata and revision history; and
12. structured dates and source provenance.

Group 13 - Events, Locations and Provenance Browsing - is the next official
capability. It must preserve Group 12 review and privacy boundaries while
adding events, normalized locations and safe entity browsing.

See [Roadmap](../ROADMAP.md) for Groups 13-46.
