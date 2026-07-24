# Family Archive

Family Archive is an archive-grade private family media preservation platform
and a flagship product.

It preserves original family media, creates optimized viewing versions, detects
possible duplicates, manages human-reviewed knowledge and provides controlled
family access. The application is designed as a standalone, commercial-grade
system and is not part of another product or student-work archive.

## Current Status

- Official roadmap: 46 groups
- Completed and closed: Groups 01-12
- Next official group: Group 13 - Events, Locations and Provenance Browsing
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
- manage stable source collections and scan batches; and
- attach multiple reviewed provenance records with immutable revision evidence.

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
for the closure requirements used by each roadmap group.
