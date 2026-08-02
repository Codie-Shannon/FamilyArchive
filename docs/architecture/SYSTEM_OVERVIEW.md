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
| High-volume intake | Path-safe inventory fingerprints and resumable quarantine checkpoints for large local photo batches |
| Knowledge | Reviewed events, locations, people and family branches with one role-aware browsing and search policy; place titles and optional subtitles remain distinct from geographic addresses |
| Access | Invitation-only accounts, verified-email or guided member-name setup, enforced approval, branch-filtered browsing, one-time assisted recovery, immutable access events and active original grants |
| Communication | Immediate approved-family-member one-to-one messaging, per-participant mute/archive/block controls, recipient-reported moderation exceptions, family posts and moderated anonymous contact intake |
| Collaboration | Identity suggestions and archive notifications |
| Processing | Versioned restoration recipes, queued jobs and review candidates |
| Verified Photo Workflow | Retained contributor input, delegated batch review and approved-candidate viewing lineage |
| Providers | Provider-neutral private storage, Wasabi versioned no-overwrite writes, live protection verification and copy-first migration |
| Operations | Redacted production readiness, database/cache health and live deployment verification |
| Administration | Owner-only action queue and grouped access to established specialist workflows |
| Integrity | No-overwrite transfer, observations, repair review and recovery evidence |
| Release | Deterministic acceptance readiness and explicit human gates |
| Custodianship | Proposed, confirmed and revoked long-term responsibility |
| Intelligence | Perceptual similarity, alternate originals and metadata merge proposals |
| Cloud Import | Provider readiness, mixed-media preflight and playback profiles |
| Public Discovery | Restricted public showcase, privacy-reviewed map labels and publication receipts |
| Community | Membership-aware spaces, expiring presence, moderated voice and call readiness |
| Secure Communication | Recipient-scoped DM consent, encrypted envelopes, attachment scans, constrained guidance and official business bridges |
| Portfolio | Preservation-first evidence views and local read-only demonstration safeguards |
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
- `UserInvitation`
- `AccountAccessEvent`
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
- `MediaPerceptualFingerprint`
- `VisualSimilarityCandidate`
- `AlternateMediaSource`
- `MetadataMergeProposal`
- `CloudImportConnection`
- `CloudImportSession`
- `CloudImportItem`
- `MediaPlaybackProfile`
- `PublicShowcaseEntry`
- `PublicMapPoint`
- `SocialPublicationReceipt`
- `CommunitySpace`
- `CommunityChannel`
- `CommunityMembership`
- `CommunityPresence`
- `VoiceMessage`
- `VoiceCallSession`
- `PublicIdentityAlias`
- `PublicDirectThread`
- `EncryptedMessageEnvelope`
- `MessageAttachment`
- `GuidanceBotInteraction`
- `MessagingBridgeDelivery`
- `PortfolioDemoSeeder`

Planned records include concepts such as deeper relationships, collections,
processing jobs, integrity manifests, notifications and expanded moderation
events. Their final schemas are introduced only by the roadmap group that owns
the capability.

## Current Photo Lifecycle

```text
Owner or approved contributor upload
  -> technical validation
  -> private quarantine retention
  -> SHA-256 and byte verification
  -> exact-duplicate candidate detection
  -> trusted contributor or administrator batch review
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

Public registration is disabled. An owner creates an expiring one-use
invitation. Email-based accounts verify their address; guided accounts use a
printable one-time code and friendly member name. Both remain blocked until an
authorized family operator records approval. Approved viewers and contributors see only
family-visible records plus branch-visible records matching their reviewed
branch. Admins and owners retain archive-wide visibility. Approved members use
one shared archive journey across photos, reviewed places, people, events,
branches and search. The knowledge access service applies the same privacy
filter to indexes, details, counts and search before rendering. Owner-only
middleware continues to protect account administration and preservation
operations.

Preferred originals never become public URLs. A non-administrator can receive
an original only through a separately effective, unexpired and unrevoked
grant, and delivery verifies byte length and SHA-256 before returning a
private, non-cacheable response. Account changes append immutable access-event
evidence. Public conversation remains distinct: anonymous contact grants no
identity or archive access.

## Current Build State

Screenshot Group 01 covers Build Groups 13–20 and is closed as the v0.20.0
Archive Knowledge release with its six-file evidence pack approved. Screenshot
Group 02 covers Build Groups 21–28 and is closed as v0.28.0 Family Access &
Conversation with its seven-file evidence pack approved. Screenshot Group 03
covers Build Groups 29–36 and is closed as v0.36.0 Collaboration & Restoration
with its seven-file evidence pack approved. Screenshot Group 04 covers Build
Groups 37–44 and is closed as v0.44.0 Integrity & Production with its seven-file
evidence pack approved. Screenshot Group 05 covers Build Groups 45–46 and is
closed with its seven-file v1.0.0 Family Archive v1.0 screenshot evidence pack
approved. Real pilot, production and custodian acceptance gates remain human
work. Screenshot Group 06 is closed as the v1.1.0 Advanced Media Intelligence
post-v1 boundary with its three-file evidence pack approved. Screenshot Group
07 is closed as the v1.2.0 Media & Cloud Import post-v1 boundary with its
three-file evidence pack approved. Screenshot Group 08 is implemented as the
v1.3.0 Public Discovery & Archive Maps post-v1 boundary, with its four-file
evidence pack approved. Screenshot Group 09 is implemented as v1.4.0 Real-Time
Family Community, with its four-file evidence pack approved. Screenshot Group
10 is implemented as v1.5.0 Secure & Federated Communication, with its
four-file evidence pack approved. Screenshot Group 11 is implemented as v1.6.0
Portfolio Showcase, with its seven-file evidence pack approved. Screenshot
Group 12 is implemented as v1.7.0 Accounts & Contributor Access, with its
six-file evidence pack approved. Screenshot Group 13 is implemented as v1.8.0
Restoration Automation, with its six-file evidence pack approved. Its crop
processing fails closed when a sustained four-sided boundary cannot be
verified: original framing is retained and the batch item is routed to
`crop_check` instead of promoting a low-confidence suggestion. Pending batch
suggestions can be regenerated after a rule correction; reviewed decisions are
excluded and the former derivative candidate remains as rejected audit history.
Screenshot Group
14 is implemented as v1.9.0 Wasabi Production Storage, with its five-file
live evidence pack approved. Screenshot Group 15 is implemented as v2.0.0
Hosted Production, with its four-file live evidence pack approved. Screenshot
Groups 01-19 are closed. Screenshot Group 16 implements the v2.1.0 Unified
Member Experience with role-aware member navigation and approved evidence.
Screenshot Group 17 implements the v2.2.0 Owner Command Centre with approved
six-file evidence. Screenshot Group 18 implements the v2.3.0 Archive
Exploration boundary with shared, role-aware navigation across the archive
research surfaces; its eight-file evidence pack is approved and closed.
Screenshot Group 19 implements the v2.4.0 Verified Photo Workflow with an
approved privacy-safe seven-file evidence pack. Screenshot Group 20 implements
the v2.5.0 High-Volume Batch Intake boundary with an approved six-file evidence
pack. Screenshot Group 21 implements the v2.6.0 Consolidated Intake Review
boundary with an approved six-file evidence pack. Screenshot Group 22
implements the v2.7.0 Delegated Intake Completion boundary with an approved
seven-file evidence pack. Screenshot Group 23 implements the v2.8.0 Unified
Archive Experience boundary with role-aware discovery and its eight-file
evidence pack approved and closed. Screenshot Group 24 implements the v2.9.0
Delegated Family Operations boundary. Routine membership, reported-content and
communication decisions can be handled by archive administrators or their
direct recipients, while elevated roles, original access and policy exceptions
remain Owner-controlled; its eight-file evidence pack is approved and closed. The
v3.0.0 Role-Aware Workflow Polish boundary consolidates operational entry points
into one Work hub whose summaries and destinations are filtered by role. It does
not merge specialist authorization boundaries or expose operational queues to
ordinary members; its six-file Screenshot Group 25 evidence pack is approved and closed. The
v3.1.0 Original-First Manual Restoration boundary makes automatic output an
optional comparison and gives authorized reviewers equivalent manual controls
that always render from the verified original. Manual saves create separate
candidate versions with explicit lineage; its six-file Screenshot Group 26
evidence pack is approved and closed. The v3.2.0 Interactive Archive Map
boundary moves every archive destination into one position-stable navigation
shell and replaces the illustrative map surface with a real provider-backed
map. Its browser payload contains only published, separately reviewed points
after neighbourhood, town or region precision reduction; exact and unreviewed
coordinates remain server-side and its six-file Screenshot Group 27 evidence
pack is approved and closed. The
implemented foundation
establishes:

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
13. reviewed events, normalized locations and provenance-aware browsing;
14. moderated family access and contributor conversation boundaries;
15. versioned restoration and fail-closed provider configuration; and
16. verified no-overwrite transfer, integrity observation and repair review;
    and
17. deterministic acceptance readiness and explicit custodianship state; and
18. perceptual similarity review, alternate originals and merge previews.
19. mixed-media cloud-import planning and provider readiness without live
    provider claims; and
20. reviewed public showcase entries, reduced-precision map publication and
    social-publication receipt review; and
21. membership-aware community spaces, expiring presence, moderated voice
    messages and fail-closed call readiness; and
22. recipient-scoped public-DM consent, encrypted-envelope records, attachment
    scan states, constrained guidance and official business bridge readiness;
    and
23. a preservation-first portfolio narrative with local read-only
    demonstration safeguards; and
24. enforced invitations, verification, approval, branch filtering, original
    grants and resumable contributor intake; and
25. private Wasabi prefixes, versioned no-overwrite writes, exact-version
    verification, least-privilege policy generation and copy-first migration;
    and
26. hardened hosted responses, database/cache health, Owner-only readiness and
    redacted live deployment verification; and
27. a consolidated Owner queue and grouped operational views that preserve
    every specialist authorization boundary; and
28. a path-safe inventory and resumable checkpoint pipeline for high-volume
    photo intake that requests separate smart-crop, rotation and deskew
    candidates without automatic archive promotion; and
29. an exception-first trusted-intake workspace that compares verified
    originals with reversible suggestions and commits bounded bulk decisions;
    and
30. a role-authorized manual restoration editor that starts from the verified
    immutable original, exposes every implemented image adjustment, accepts
    work even when automation has no usable suggestion and saves only a new
    versioned review candidate.
31. delegated routine family operations that keep privileged access and
    original-file decisions inside the Owner exception boundary.

Photo acceptance preserves the verified source as the immutable original before
any review candidate can exist. Authorized trusted reviewers may compare an
automatic suggestion or build their own edit directly from that original. Only
an explicit later decision may change the preferred viewing version; no editor,
automation rule or acceptance action changes the original.

The Archive Knowledge hub adds reviewed, permission-aware discovery across
events, safe locations, non-private non-living people and family branches.
Location identity is deliberately separate from geography: every place has a
family-facing title, may have a descriptive subtitle, and may store an address
for context and mapping without replacing that title. Sensitive locations
continue to redact all of those browse details.

The album-centred archive presents Photos, Albums and Search as the stable
member journey. Curated albums store ordered membership links only; they do not
copy media or change immutable originals. Generated event, place, person and
branch albums are read models over existing reviewed relationships. Cover
selection, counts, combined search and album contents all pass through the same
archive and knowledge access policies before rendering. Owners, administrators
and trusted contributors may curate shared albums through a dedicated
searchable batch-add surface for up to 100 approved photos at a time. This
changes membership without changing or duplicating archive media, while
ordinary members browse the published result.
Family Access & Conversation adds account approval facts, contributor intake
records and moderated communication while preserving the private archive
boundary. Collaboration & Restoration adds versioned recipes, immutable-source
job queues, review candidates and fail-closed provider configuration. Integrity
& Production adds verified transfers, immutable integrity observations,
human-reviewed repair cases and synthetic recovery-readiness records without
claiming live infrastructure.
V1 Acceptance & Custodianship adds non-identifying pilot-feedback review,
recorded deterministic gates and proposed or confirmed responsibility. It
keeps family-pilot approval, production proof and custodian confirmation as
explicit human work and does not infer them from release metadata.
Advanced Media Intelligence adds deterministic similarity candidates,
separate alternate-original provenance and conflict-aware metadata proposals.
It never treats similarity as deletion authority or applies conflicting facts
without human review.
Media & Cloud Import adds provider readiness, mixed-media preflight sessions
and versioned playback profiles. Selected items still pass validation,
quarantine, duplicate review and acceptance. Apple native access remains
unvalidated and document OCR remains excluded.
Public Discovery & Archive Maps adds a deliberately restricted public read
model for reviewed showcase stories and privacy-reviewed place labels. Exact
coordinates, unpublished entries, external publication references and private
archive access remain outside public output. Publication review stays inside
the verified Owner boundary.
Real-Time Family Community adds membership-filtered spaces and channels,
temporary presence and typing signals, and moderated voice-message read
models. Live voice calls remain disabled until signalling, TURN and browser
interoperability requirements are externally satisfied.
Secure & Federated Communication adds recipient-filtered anonymous public-DM
requests, versioned encrypted-envelope validation and private attachment scan
states. Its guidance bot cannot read the private archive. WhatsApp and
Messenger records describe only official business platforms, and end-to-end
encryption remains disabled until runtime interoperability is proven.
Everyday Family Experience keeps these communication controls while separating
member language from operator evidence. Approved-family conversations use the
embedded Family chat surface. Public-site contacts remain isolated in Contact
requests with their checked attachments; administrators and Owners may expand
the underlying envelope, attachment and real-time service facts when
operational verification is required.
Portfolio Showcase packages the cumulative architecture into six safe,
Owner-only evidence views. Its fictional dataset is local-only, and optional
read-only mode rejects authenticated writes without weakening authentication
or exposing private records.
Accounts & Contributor Access turns the earlier access schema into enforced
runtime behavior. Contributor originals are retained unchanged in quarantine,
automation choices are preferences for later processing work, and owner
moderation remains ahead of duplicate review and archive promotion.
Incomplete relationship, person-media, identity-merge and resolution workflows
remain unavailable.

See [Roadmap](../ROADMAP.md) for the complete 46-group sequence.
