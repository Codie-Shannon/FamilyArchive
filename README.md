# Family Archive

Family Archive is an archive-grade private family media preservation platform
and a flagship product.

It preserves original family media, creates optimized viewing versions, detects
possible duplicates, manages human-reviewed knowledge and provides controlled
family access. The application is designed as a standalone, commercial-grade
system and is not part of another product or student-work archive.

## Current Status

- Official screenshot groups: 11
- Completed and closed: SG01 - Build Groups 13-20
- Completed and closed: SG02 - Build Groups 21-28
- Completed and closed: SG03 - Build Groups 29-36
- Completed and closed: SG04 - Build Groups 37-44
- Completed and closed: SG05 - Build Groups 45-46
- Completed and closed: SG06 - Post-v1 A
- Completed and closed: SG07 - Post-v1 B
- Completed and closed: SG08 - Post-v1 C
- Completed and closed: SG09 - Post-v1 D
- Next screenshot group: SG10 - Post-v1 E
- Next release: v1.5.0 - Secure & Federated Communication
- Current evidence state: SG09 four-file evidence pack approved and closed
- Current media support: photos
- Current access model: verified Owner-only archive and administration

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
  interoperability requirements are genuinely satisfied.

See [System Overview](docs/architecture/SYSTEM_OVERVIEW.md) for the current
architecture and [Roadmap](docs/ROADMAP.md) for the official group sequence.

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
project's AI-assisted engineering disclosure and human-ownership boundary.
