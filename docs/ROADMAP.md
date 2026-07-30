# Family Archive Roadmap

Family Archive has 46 official build groups arranged into 11 screenshot groups.
Screenshot Group 01, covering Build Groups 13–20, is closed with approved
v0.20.0 Archive Knowledge evidence. Screenshot Group 02 covers Build Groups
21–28 and is closed with approved v0.28.0 Family Access & Conversation
evidence. Screenshot Group 03 covers Build Groups 29–36 and is closed with
approved v0.36.0 Collaboration & Restoration evidence. Screenshot Group 04
covers Build Groups 37–44 and is closed with approved v0.44.0 Integrity &
Production evidence. Screenshot Group 05 covers Build Groups 45–46 and is
closed with approved v1.0.0 Family Archive v1.0 screenshot evidence. Real
pilot, production and custodian acceptance gates remain human work. Screenshot
Group 06 is closed with approved v1.1.0 Advanced Media Intelligence evidence.
Screenshot Group 07 is closed with approved v1.2.0 Media & Cloud Import
evidence. Screenshot Group 08 is implemented as the v1.3.0 Public Discovery &
Archive Maps boundary, with its four-file evidence pack approved. Screenshot
Group 09 is implemented as the v1.4.0 Real-Time Family Community boundary,
with its four-file evidence pack approved. Screenshot Group 10 is implemented
as the v1.5.0 Secure & Federated Communication boundary, with its four-file
evidence pack approved. Screenshot Group 11 is implemented as the v1.6.0
Portfolio Showcase boundary, with its seven-file evidence pack pending.

This file records the repository-safe roadmap. Private chat context and planning
PDFs remain external artifacts and must not be committed.

## Completed Foundation

| Group | Capability |
|---:|---|
| 01 | Application Foundation |
| 02 | Core Archive Schema |
| 03 | Storage Identity and Path Contracts |
| 04 | Controlled Admin Photo Intake |
| 05 | Controlled Quarantine Persistence |
| 06 | Exact Duplicate Candidate Detection |
| 07 | Controlled Manual Duplicate Review |
| 08 | Archive Acceptance and Original Promotion |
| 09 | Private Viewing Derivatives |
| 10 | Private Archive Browsing |
| 11 | Controlled Metadata and Revision History |
| 12 | Structured Dates and Source Provenance |

## Knowledge Layer

| Group | Capability |
|---:|---|
| 13 | Events, Locations and Provenance Browsing |
| 14 | People Records and Family Branches |
| 15 | Family Relationships and Person Tagging |
| 16 | Unknown People and Identity Resolution |
| 17 | Archive Search and Faceted Filtering |
| 18 | Timeline and Entity Exploration |
| 19 | Saved Views and Curated Collections |

## Roles and Access

| Group | Capability |
|---:|---|
| 20 | Role Model and Policy Foundation |
| 21 | Registration, Approval and User Profiles |
| 22 | Visibility, Sensitivity and Branch Access |
| 23 | Original Access Grants and Revocation |

## Contributor Intake

| Group | Capability |
|---:|---|
| 24 | Contributor Photo Intake and Status |
| 25 | Trusted Contributor and Moderation |
| 26 | Mobile and Multi-File Uploads |
| 27 | Upload Templates and Resumable Intake |
| 28 | Stories, Comments, Family Conversations, Public Chat and Metadata Suggestions |
| 29 | Identity Suggestions, Corrections, Anonymous Messaging and Notifications |

## Processing and Restoration

| Group | Capability |
|---:|---|
| 30 | Processing Jobs and Recipe Versioning |
| 31 | Orientation, Deskew and Auto-Crop Candidates |
| 32 | Exposure, Colour and Tonal Restoration |
| 33 | Noise, Grain, Sharpening and Surface Cleanup |
| 34 | Damage Restoration, Upscaling and Approval Workspace |
| 35 | Batch Profiles, Reprocessing and Quality Regression |

## Cloud Storage and Integrity

| Group | Capability |
|---:|---|
| 36 | Storage Provider Abstraction and Wasabi |
| 37 | Verified Cloud Transfer and Storage Migration |
| 38 | Integrity Manifests and Scheduled Verification |
| 39 | Corruption Detection and Repair Queue |
| 40 | Scan-Batch Import and Inventory |
| 41 | Resumable Processing and 30,000-Photo Scale |
| 42 | Backup Verification and Restore |
| 43 | Disaster Recovery, Monitoring and Capacity |

## Launch and Custodianship

| Group | Capability |
|---:|---|
| 44 | Production Hosting and Security Hardening |
| 45 | Family Pilot, Accessibility and Portfolio Case Study |
| 46 | Family Archive v1.0 Acceptance and Custodianship |

## Post-v1 Generated Releases

| Screenshot group | Generated boundary | Version | Release |
|---:|---|---:|---|
| 06 | Post-v1 A | 1.1.0 | Advanced Media Intelligence |
| 07 | Post-v1 B | 1.2.0 | Media & Cloud Import |
| 08 | Post-v1 C | 1.3.0 | Public Discovery & Archive Maps |
| 09 | Post-v1 D | 1.4.0 | Real-Time Family Community |
| 10 | Post-v1 E | 1.5.0 | Secure & Federated Communication |
| 11 | Post-v1 F | 1.6.0 | Portfolio Showcase |

## Group 13 Boundary

Group 13 started from the verified Group 12 closure. It introduced events,
normalized locations and permission-aware provenance browsing.

Group 12 established these permanent boundaries for later work:

- incomplete dates never manufacture precision;
- source and scan-batch identities remain stable;
- inferred or embedded facts require human review;
- provenance changes append immutable revision evidence; and
- date and provenance curation never mutates preserved files.

Group 13 preserved those contracts while adding events, normalized locations,
safe entity browsing and privacy-aware location precision.

## Screenshot Group 01 Boundary

Screenshot Group 01 combines Build Groups 13–20 in the generated Archive
Knowledge release. Its hardened implementation includes reviewed events,
locations, people and stable family branches behind a permission-aware hub.

Implementation status: closed — six-file evidence pack approved.

The Build Group 14 portion:

- add to the provisional people and family-branch schema through forward-only
  migrations;
- preserve uncertain names and life dates without manufacturing precision;
- attach reviewed provenance and append immutable revision evidence;
- keep sensitive person facts inside the Owner-only boundary;
- keeps incomplete relationship and person-media tagging workflows outside the
  visible hub; and
- keeps incomplete unknown-person resolution and merge workflows outside the
  visible hub.

## Family Communication Boundary

Groups 28-29 introduce archive-aware family communication plus deliberately
separated public and anonymous communication surfaces:

- members can hold permission-aware conversation threads around photos,
  people, events, collections and family-history questions;
- comments, replies and mentions retain authorship and timestamps;
- participants receive controlled in-application notifications;
- members can participate in a moderated public chat without receiving access
  to private archive entities;
- visitors can send anonymous messages through a constrained contact surface
  without acquiring an archive identity, conversation membership or browsing
  permission;
- visibility follows the same family-branch, sensitivity and entity-access
  policies established by Groups 20-23;
- moderation supports reporting, locking and hiding content without erasing
  required audit evidence;
- public and anonymous surfaces require rate limits, abuse detection, spam
  controls, moderation queues and operator blocking controls;
- anonymous senders receive non-identifying correlation tokens so abuse can be
  investigated without presenting them as verified family members;
- conversations cannot expose inaccessible originals, storage coordinates or
  restricted archive metadata; and
- public chat and anonymous messages remain technically and visually distinct
  from trusted family-history evidence.

Conversation content can inform a metadata or identity suggestion, but it does
not become an accepted archive fact until it passes the relevant human-review
workflow. Public or anonymous content must never be accepted automatically.

## Screenshot Group 02 Boundary

Screenshot Group 02 combines Build Groups 21–28 in the generated v0.28.0 Family
Access & Conversation release.

Implementation status: closed — seven-file evidence pack approved.

The release:

- records approved, pending, rejected and suspended account states;
- associates approved members with family branches without widening the Owner
  administration boundary;
- stores sensitive-media flags and explicit, revocable original-access grants;
- provides contributor submissions, upload templates and resumable sessions;
- separates moderated public conversation from private archive knowledge;
- admits posts only from approved signed-in accounts;
- sends anonymous contact into moderation without creating an identity or
  archive access; and
- keeps email addresses, network fingerprints, correlation tokens and private
  family content out of public output.

## Screenshot Group 03 Boundary

Screenshot Group 03 combines Build Groups 29–36 in the generated v0.36.0
Collaboration & Restoration release.

Implementation status: closed — seven-file evidence pack approved.

The release:

- stores identity suggestions and archive notifications without automatic fact
  acceptance;
- versions restoration recipes and rejects unknown processing operations;
- queues jobs only from preferred immutable originals;
- records candidate output separately for human review;
- keeps the restoration workspace inside the verified Owner boundary;
- reports local storage as private and configured;
- fails closed when external Wasabi configuration is incomplete; and
- never renders credentials, bucket names or endpoints or claims an unverified
  live Wasabi connection.

## Screenshot Group 04 Boundary

Screenshot Group 04 combines Build Groups 37–44 in the generated v0.44.0
Integrity & Production release.

Implementation status: closed — seven-file evidence pack approved.

The release:

- refuses verified transfer when a destination already exists;
- verifies source identity before writing and destination identity before
  permitting later cutover;
- appends integrity observations without mutating stored objects;
- separates repair cases from observations for human review;
- records manifest, scan-import, backup and operational readiness state;
- marks restore records and recovery metrics as synthetic isolated rehearsals;
- keeps the operations dashboard inside the verified Owner boundary; and
- never renders credentials, provider accounts, endpoints, storage paths,
  hashes or real capacity figures.

## Screenshot Group 05 Boundary

Screenshot Group 05 combines Build Groups 45–46 in the generated v1.0.0 Family
Archive v1.0 release candidate.

Implementation status: closed — seven-file screenshot evidence approved; real
human acceptance gates remain pending.

The release:

- records non-identifying pilot and accessibility feedback for review;
- evaluates deterministic readiness gates and records blocked or ready runs;
- displays missing pilot approval, production proof and custodian confirmation
  honestly;
- models primary, successor and emergency custodianship with explicit
  confirmation state;
- keeps the acceptance surface inside the verified Owner boundary;
- preserves authentication and authorization regardless of designation; and
- does not fabricate participants, production infrastructure, family approval
  or custodian confirmation.

## Screenshot Group 06 Boundary

Screenshot Group 06 is the generated Post-v1 A boundary for v1.1.0 Advanced
Media Intelligence.

Implementation status: closed — three-file screenshot evidence approved.

The release:

- validates deterministic 64-bit perceptual fingerprints;
- creates similarity candidates in pending review state;
- keeps alternate originals as separate immutable file and provenance records;
- previews blank-field additions separately from conflicting reviewed facts;
- stores merge proposals without applying them automatically;
- keeps the intelligence workspace inside the verified Owner boundary; and
- never renders fingerprints, internal file identifiers, paths or hashes.

## Screenshot Group 07 Boundary

Screenshot Group 07 is the generated Post-v1 B boundary for v1.2.0 Media &
Cloud Import.

Implementation status: closed — three-file screenshot evidence approved.

The release:

- plans Google Photos Picker and manual Apple Photos import sessions;
- admits photo, video, audio and document selections into preflight only;
- sanitizes displayed source names and stores no provider secret in evidence;
- records versioned playback and preview profiles for non-photo media;
- keeps Apple native access explicitly unvalidated without Apple test hardware;
- keeps document OCR and searchable scan text explicitly excluded;
- preserves quarantine, duplicate review and human acceptance boundaries; and
- keeps the cloud-import workspace inside the verified Owner boundary.

## Screenshot Group 08 Boundary

Screenshot Group 08 is the generated Post-v1 C boundary for v1.3.0 Public
Discovery & Archive Maps.

Implementation status: closed — four-file screenshot evidence approved.

The release:

- publishes only explicitly reviewed showcase entries;
- keeps draft, review and withdrawn entries out of public output;
- requires map points to pass a separate privacy review;
- permits only neighbourhood, town or region precision on the public map;
- rejects exact points from public output even when incorrectly marked
  reviewed;
- removes source coordinates before rendering the map view;
- records social-publication receipt state without displaying external
  references or provider responses; and
- keeps publication administration inside the verified Owner boundary.

## Screenshot Group 09 Boundary

Screenshot Group 09 is the generated Post-v1 D boundary for v1.4.0 Real-Time
Family Community.

Implementation status: closed — four-file screenshot evidence approved.

The release:

- stores large community spaces, channels, memberships and roles;
- filters the community read model through active, non-suspended membership;
- expires presence and typing signals rather than preserving them as facts;
- renders only allowed voice messages after moderation;
- excludes storage keys, checksums and raw permission overrides from member
  output;
- keeps call identifiers, diagnostics, endpoints and secrets out of the
  Owner operations view;
- fails closed when signalling or TURN infrastructure is absent; and
- does not claim live calls until browser interoperability testing passes.

## Screenshot Group 10 Boundary

Screenshot Group 10 is the generated Post-v1 E boundary for v1.5.0 Secure &
Federated Communication.

Implementation status: closed — four-file screenshot evidence approved.

The release:

- scopes anonymous public direct-message requests to the intended recipient;
- requires explicit consent before encrypted-envelope summaries are available;
- validates versioned envelope structure and hexadecimal SHA-256 digests
  without inspecting plaintext;
- renders only safe attachment facts from authorized accepted threads;
- keeps ciphertext, wrapped keys, digests, moderation fingerprints, storage
  paths and checksums outside user-facing read models;
- disables the guidance bot by default and prohibits private archive access;
- fails closed while end-to-end encryption runtime setup is incomplete;
- models only the official WhatsApp Business Cloud API and Messenger Platform;
  and
- never claims access to arbitrary personal chats.

## Screenshot Group 11 Boundary

Screenshot Group 11 is the generated Post-v1 F boundary for v1.6.0 Portfolio
Showcase.

Implementation status: implemented — seven-file screenshot evidence pending.

The release:

- positions preservation integrity and provenance as the product promise;
- presents one coherent ingest, verify, review, enrich, preserve and share
  journey;
- summarizes hashes, immutable lineage, backup and recovery through safe
  aggregate evidence without exposing private values;
- demonstrates roles, publication review gates and reduced public location
  precision;
- documents the modular-monolith boundary and distinct private storage zones;
- presents desktop, mobile, keyboard and semantic-accessibility evidence;
- restricts the fictional portfolio seeder to the local environment and an
  explicit password;
- rejects authenticated product writes when read-only demonstration mode is
  explicitly enabled; and
- de-emphasizes generic social-network expansion without deleting its bounded
  architectural history.
