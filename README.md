# Family Archive

[![Tests](https://github.com/Codie-Shannon/FamilyArchive/actions/workflows/tests.yml/badge.svg)](https://github.com/Codie-Shannon/FamilyArchive/actions/workflows/tests.yml)
[![Lint](https://github.com/Codie-Shannon/FamilyArchive/actions/workflows/lint.yml/badge.svg)](https://github.com/Codie-Shannon/FamilyArchive/actions/workflows/lint.yml)

Family Archive is a privacy-first, preservation-grade family history platform.
It protects immutable originals, creates traceable viewing and restoration
derivatives, and gives approved family members a simpler way to contribute,
review and discover shared history.

**Live product:** [familyarchive.bayforgesystems.com](https://familyarchive.bayforgesystems.com)

![Migration qualification dashboard](docs/screenshot-groups/screenshot-group-33/01_v380_Thirty_Thousand_Qualification.png)

## Release state

- Current release: **v3.8.0 — Migration Qualification**
- Official evidence: **33 approved and closed screenshot groups**
- Media supported by the completed workflow: **photos**
- Production services: Laravel Cloud, MySQL, Wasabi object storage and Google Maps
- Migration qualification: a synthetic 30,000-entry run proved checkpoints,
  interruption recovery, isolated failures, idempotent replay and reconciliation
- Real family migration: deliberately separate and not represented by public evidence

The product release is closed. The repository is in maintenance and migration
readiness mode; new feature expansion requires an explicit new release boundary.

## What the product demonstrates

- Immutable, checksum-verified originals with no-overwrite storage boundaries
- Quarantine, duplicate review, human acceptance and append-only audit history
- Automatic and manual restoration from the verified original, with versioned lineage
- Resumable batch intake designed for tens of thousands of photos
- Delegated family roles, guided non-email access and owner-controlled exceptions
- Albums, permission-aware search and privacy-reviewed interactive maps
- Embedded family messaging with participant-controlled mute, archive and block
- Live production health, restrictive browser headers and private Wasabi storage

The complete feature inventory is in [Capabilities](docs/CAPABILITIES.md). The
architecture and preservation rules are described in the
[System Overview](docs/architecture/SYSTEM_OVERVIEW.md).

## Preservation contract

Accepted originals are never overwritten, silently replaced or automatically
deleted. Quarantine objects, originals and derivatives have separate identities.
Every viewing or restoration derivative records its source lineage. Automated
processing produces suggestions; people make consequential archive decisions.

## Privacy and evidence

The repository, tests and public screenshot evidence use synthetic people,
places and media. Real family photos, names, storage identifiers, credentials and
local source paths are excluded. The release verifier checks those boundaries and
validates the official PNG evidence packs.

See [Security](SECURITY.md), [Threat Model](docs/THREAT_MODEL.md) and
[Evidence Guide](docs/screenshot-groups/README.md).

## Technology

- PHP 8.3+ and Laravel 13
- Livewire 4, Flux, Tailwind CSS and Vite
- MySQL in hosted production
- Pest, Larastan and Laravel Pint
- Wasabi S3-compatible private object storage
- Google Maps for privacy-reviewed public locations

## Run locally

Install and initialize the project:

```bash
composer setup
```

Run the local application services:

```bash
composer dev
```

Run the full engineering and release gate:

```bash
composer test
composer release:verify
```

The portfolio dataset is synthetic. Configure a unique
`PORTFOLIO_DEMO_PASSWORD`, then seed a fresh local database:

```bash
php artisan db:seed --class=PortfolioDemoSeeder
```

`PORTFOLIO_DEMO_MODE=true` makes authenticated demonstration writes fail
closed. Never reuse demonstration credentials or data in production.

## Documentation

- [Documentation index](docs/MASTER.md)
- [Current maintenance boundary](docs/CURRENT_BUCKET.md)
- [Capabilities](docs/CAPABILITIES.md)
- [Release history](docs/RELEASE_HISTORY.md)
- [v3.8.0 release notes](docs/release-notes/v3.8.0.md)
- [Roadmap and closed groups](docs/ROADMAP.md)
- [Build and evidence process](docs/BUILD_AND_EVIDENCE.md)
- [Development process](docs/DEVELOPMENT_PROCESS.md)

Family Archive is developed by BayForge Systems.
