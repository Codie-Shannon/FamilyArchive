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
- Active screenshot group: SG02 - Build Groups 21-28
- Current release: v0.28.0 - Family Access & Conversation
- SG02 status: implementation complete, evidence pending
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
  granting archive access.

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
