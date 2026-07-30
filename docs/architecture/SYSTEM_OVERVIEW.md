# Family Archive System Overview

Family Archive is a standalone, archive-grade family media preservation
platform. Its archive is private, while v1.0 also provides deliberately
separated public-chat and anonymous-contact surfaces. It is built as a modular
Laravel monolith with preservation, privacy, review and auditability as primary
design constraints.

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
| Knowledge | Reviewed events, locations, people, family branches and provenance-aware browsing |
| Access | Account approval, branch membership, sensitive-media facts and original-access grants |
| Communication | Moderated public threads, approved member posts and anonymous contact intake |
| Collaboration | Identity suggestions and archive notifications |
| Processing | Versioned restoration recipes, queued jobs and review candidates |
| Providers | Provider-neutral private storage configuration and safe readiness status |
| Media | Shared media records, versions, statuses and enums |

Future roadmap groups add deeper relationships, collections, restoration,
cloud storage, integrity operations and production custodianship. Private
family content and archive facts remain distinct from moderated public chat and
constrained anonymous contact.

## Core Records

Implemented records include:

- `IncomingUpload`
- `MediaItem`
- `MediaFileVersion`
- `DuplicateCandidate`
- `DuplicateReviewEvent`
- `ArchivePromotion`
- `PhotoMetadataRevision`
- `SourceCollection`
- `ScanBatch`
- `ArchiveEvent`
- `ArchiveEventRevision`
- `ArchiveLocation`
- `ArchiveLocationRevision`
- `ArchivePerson`
- `ArchivePersonRevision`
- `FamilyBranch`
- `FamilyBranchRevision`
- `OriginalAccessGrant`
- `ContributorSubmission`
- `UploadTemplate`
- `UploadSession`
- `ArchiveStory`
- `ConversationThread`
- `ConversationMessage`
- `AnonymousMessage`
- `MetadataSuggestion`
- `IdentitySuggestion`
- `ArchiveNotification`
- `ProcessingRecipe`
- `ProcessingJob`
- `RestorationCandidate`
- `StorageProviderStatus`

Planned records include concepts such as deeper relationships, collections,
processing jobs, integrity manifests, notifications and expanded moderation
events. Their final schemas are introduced only by the roadmap group that owns
the capability.

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

The archive and administration surface requires an authenticated, verified user
with the Owner role. Public registration is disabled. The public conversation
surface exposes only explicitly public moderated threads. Approved accounts may
post; anonymous contact enters a moderation queue and never grants archive
access.

Account state, family-branch association and explicit original-access grants are
stored for later policy expansion. Owner-only archive access remains the
runtime security boundary for this release.

## Current Build State

Screenshot Group 01 covers Build Groups 13–20 and is closed as the v0.20.0
Archive Knowledge release with its six-file evidence pack approved. Screenshot
Group 02 covers Build Groups 21–28 and is closed as v0.28.0 Family Access &
Conversation with its seven-file evidence pack approved. Screenshot Group 03
covers Build Groups 29–36 and is implemented as v0.36.0 Collaboration &
Restoration with its seven-file evidence pack pending. The implemented
foundation establishes:

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
11. controlled metadata and revision history;
12. structured dates and source provenance; and
13. reviewed events, normalized locations and provenance-aware browsing.

The Archive Knowledge hub adds reviewed, permission-aware discovery across
events, safe locations, non-private non-living people and family branches.
Family Access & Conversation adds account approval facts, contributor intake
records and moderated communication while preserving the private archive
boundary. Collaboration & Restoration adds versioned recipes, immutable-source
job queues, review candidates and fail-closed provider configuration.
Incomplete relationship, person-media, identity-merge and resolution workflows
remain unavailable.

See [Roadmap](../ROADMAP.md) for the complete 46-group sequence.
