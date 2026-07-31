# Family Archive

Family Archive is a privacy-first, preservation-grade family history platform
and a flagship product.

It preserves original family media, creates optimized viewing versions, detects
possible duplicates, manages human-reviewed knowledge and provides controlled
family access. The application is designed as a standalone, commercial-grade
system and is not part of another product or student-work archive.

## Current Status

- Official screenshot groups: 18 planned
- Completed and closed: SG01 - Build Groups 13-20
- Completed and closed: SG02 - Build Groups 21-28
- Completed and closed: SG03 - Build Groups 29-36
- Completed and closed: SG04 - Build Groups 37-44
- Completed and closed: SG05 - Build Groups 45-46
- Completed and closed: SG06 - Post-v1 A
- Completed and closed: SG07 - Post-v1 B
- Completed and closed: SG08 - Post-v1 C
- Completed and closed: SG09 - Post-v1 D
- Completed and closed: SG10 - Post-v1 E
- Completed and closed: SG11 - Post-v1 F
- Completed and closed: SG12 - Corrective Accounts & Contributor Access
- Completed and closed: SG13 - Corrective Restoration Automation
- Completed and closed: SG14 - Wasabi Production Storage
- Completed and closed: SG15 - Hosted Production
- Completed and closed: SG16 - Unified Member Experience
- Completed and closed: SG17 - Owner Command Centre
- Completed and closed: SG18 - Archive Exploration
- Current release: v2.3.0 - Archive Exploration
- Current evidence state: Screenshot Groups 01-18 approved and closed
- Current media support: photos
- Current access model: invite-only, verified and approved role/branch access with
  owner-controlled administration and original grants
- Current restoration model: uploader-controlled, integrity-verified,
  non-destructive candidates with explicit human review
- Current storage model: private local mode by default, with a fail-closed
  Wasabi production mode using isolated prefixes and versioned no-overwrite
  writes
- Current hosting model: verified Laravel Cloud production at the branded HTTPS
  origin, with hardened response headers, database/cache health checks and
  passing application-owned live verification

The completed system can:

- authenticate a verified Owner and protect the administration boundary;
- model archive media, incoming uploads and versioned files;
- validate photo signatures, MIME types and dimensions;
- retain uploads in private quarantine without overwriting existing files;
- calculate and verify SHA-256 integrity facts;
- create exact-duplicate candidates for human review;
- record auditable duplicate-review decisions;
- promote an accepted source to a verified, immutable original;
- generate private WebP display and thumbnail derivatives with lineage;
- browse approved photos through private archive views;
- edit descriptive metadata with optimistic locking and immutable revisions;
- represent uncertain historical dates without manufacturing precision;
- manage stable source collections and scan batches;
- attach multiple reviewed provenance records with immutable revision evidence;
- curate reviewed events and normalized locations with stable identities;
- preserve uncertain event dates with explicit precision and confidence;
- redact sensitive location precision from browse surfaces; and
- link events safely to approved media and existing source provenance;
- curate stable, reviewed person identities and family branches;
- preserve uncertain names and incomplete life dates without invented detail;
- redact sensitive person and family-branch facts from browse surfaces;
- append immutable person and branch revisions with reviewed provenance;
- search reviewed archive knowledge without exposing living people, private
  identities, sensitive locations or unreviewed records; and
- use the responsive Archive Knowledge hub across Build Groups 13-20 inside
  the verified Owner boundary;
- record account approval, branch membership, sensitive-media flags and
  explicit original-access grants;
- retain contributor submissions, upload templates and resumable sessions;
- publish moderated public family conversations without exposing the private
  archive; and
- accept anonymous messages into moderation without creating an account or
  granting archive access;
- record identity suggestions and archive notifications;
- create versioned restoration recipes from an approved operation set;
- queue restoration work only from preferred immutable originals;
- retain restoration output as review candidates rather than replacing source
  records; and
- validate local and external archive-provider configuration without exposing
  credentials or claiming an unverified live connection;
- refuse to overwrite an existing destination during verified transfer;
- append integrity findings without repairing or mutating stored objects;
- separate repair review from verification observations; and
- record synthetic backup, recovery and operational readiness without claiming
  production infrastructure;
- evaluate deterministic v1 readiness gates without granting human acceptance;
- retain non-identifying pilot and accessibility feedback review state; and
- record proposed and confirmed custodianship responsibilities without
  bypassing normal access controls;
- compare validated perceptual fingerprints as review candidates;
- retain alternate originals as separate immutable source records; and
- preview metadata additions and conflicts without silently merging reviewed
  facts;
- plan Google Photos Picker and manual Apple Photos imports through preflight;
- represent photo, video, audio and document selections without bypassing
  quarantine, duplicate review or acceptance; and
- keep Apple native access unvalidated and document OCR excluded until their
  real external requirements are satisfied;
- publish only explicitly reviewed showcase stories through a restricted
  public read model;
- render only privacy-reviewed neighbourhood, town or region map labels while
  withholding exact coordinates; and
- review public entries, map privacy and social-publication receipt state
  inside the verified Owner boundary;
- restrict community spaces and channels to active memberships;
- resolve presence and typing as short-lived signals rather than durable
  identity facts;
- show only moderated, allowed voice messages without storage keys or
  checksums; and
- keep voice calls disabled until signalling, TURN and browser
  interoperability requirements are genuinely satisfied;
- scope anonymous public direct-message requests to their intended recipient
  and require explicit consent before envelope summaries appear;
- validate versioned encrypted-envelope records without rendering plaintext,
  ciphertext, wrapped keys or digests;
- retain private attachment scan states without exposing storage paths or
  checksums;
- prohibit the guidance bot from private archive access; and
- model only official WhatsApp Business Cloud API and Messenger Platform
  bridges without implying access to arbitrary personal chats; and
- present the preservation, provenance, integrity, privacy and recovery
  architecture through a focused read-only fictional portfolio narrative.
- turn enabled uploader automation preferences into versioned restoration
  recipes and separate WebP review candidates;
- apply EXIF orientation, conservative deskew/crop analysis and optional gentle
  tonal cleanup without changing an original;
- retain perspective correction, damage reconstruction and upscaling as
  manual-only requests until a supported processor exists; and
- append processing and review history while requiring human approval before a
  restored derivative can become preferred;
- switch the four logical archive disks between private local storage and one
  private Wasabi bucket without changing persisted logical disk identities;
- reserve remote objects atomically, require returned version identities and
  verify byte counts and SHA-256 through exact-version readback;
- verify bucket access, versioning and Object Lock capability with an isolated
  synthetic health object while keeping provider identifiers private;
- generate a least-privilege application-user policy that denies application
  deletion of originals and manifests; and
- plan or execute a copy-first, resumable Wasabi migration without deleting
  local source objects;
- expose a database-and-cache health endpoint without returning infrastructure
  details;
- apply restrictive browser security headers and disable caching on
  authenticated pages;
- evaluate production configuration through an Owner-only readiness view; and
- record a redacted live deployment proof only after HTTPS, health, response
  headers and isolated Wasabi verification all pass.

## Product Position

The portfolio story deliberately leads with preservation integrity,
provenance, human review, family access, recovery and controlled sharing.
Communication features support that story. Generic social-network expansion is
disabled or de-emphasized in the portfolio presentation.

See [Product Positioning](docs/PRODUCT_POSITIONING.md),
[System Overview](docs/architecture/SYSTEM_OVERVIEW.md) and
[Roadmap](docs/ROADMAP.md).

## Preservation Rules

Originals are sacred:

- accepted originals are never overwritten, silently replaced or
  automatically deleted;
- quarantine objects, originals and derivatives are stored separately;
- derivatives and edited versions are separate records with explicit lineage;
- hashes and byte counts are verified at preservation boundaries;
- duplicate detection and image processing create review candidates;
- humans make consequential archive decisions; and
- audit and revision records are append-only historical evidence.

## Privacy and Demonstration Data

Development, testing, screenshots and portfolio evidence use fully synthetic
data and fictional New Zealand family history. Real family faces, names,
records or media must not be used unless explicitly approved.

Private chat context, handoff documents and planning PDFs are external working
artifacts. They must not be committed to this repository.

## Technology

- PHP 8.3+
- Laravel 13
- Livewire 4 and Flux
- MySQL in the application environment
- Pest for automated tests
- Larastan for static analysis
- Vite and Tailwind CSS for frontend assets

## Development

Install and initialize the project:

```bash
composer setup
```

Run the local application services:

```bash
composer dev
```

Run the complete validation suite:

```bash
composer test
```

The complete command includes formatting verification, static analysis and
automated tests. See [Build and Evidence Process](docs/BUILD_AND_EVIDENCE.md)
for the closure requirements used by each roadmap group and
[Development Process and Tooling](docs/DEVELOPMENT_PROCESS.md) for the
project's engineering workflow and maintainer-ownership boundary.

## Portfolio Demonstration

Use a fresh local database and set a unique `PORTFOLIO_DEMO_PASSWORD`; then
run:

```bash
php artisan db:seed --class=PortfolioDemoSeeder
```

Enable `PORTFOLIO_DEMO_MODE=true` only after seeding. Authenticated product
writes are then rejected, keeping the demonstration repeatable and preventing
accidental edits. Never reuse the demonstration password or dataset in
production.
