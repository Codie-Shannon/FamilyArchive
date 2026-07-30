# Portfolio Showcase Architecture

Screenshot Group 11 packages the cumulative platform as a focused,
preservation-first portfolio narrative. It does not introduce production
claims or bypass existing security boundaries.

## Narrative Boundary

The showcase leads with:

1. immutable originals, hash verification and derivative lineage;
2. provenance, uncertain facts and human-reviewed metadata;
3. granular private access and controlled public read models;
4. integrity checking, backup verification and recovery evidence; and
5. a coherent ingest-to-preservation workflow.

Messaging, presence and voice notes remain supporting experiences. Generic
social-network expansion is de-emphasized and must not overshadow the archive
product.

## Read-only Demonstration

`PortfolioDemoSeeder` is restricted to the local application environment and
requires an explicit, non-empty demonstration password. It uses only the
fictional Aotearoa dataset.

When `PORTFOLIO_DEMO_MODE=true`, the `demo.readonly` middleware rejects
authenticated `POST`, `PUT`, `PATCH` and `DELETE` requests across the product
and settings route groups. Authentication and safe `GET` requests remain
available.

The demonstration mode must never:

- connect to production storage;
- reuse a production or personal password;
- contain real family identities, images or locations;
- render credentials, hashes or private object paths; or
- be described as production acceptance evidence.

## Evidence Views

The Owner-only portfolio route provides six focused read models:

- product promise and safe aggregate metrics;
- the ingest, verify, review, enrich, preserve and share journey;
- integrity, lineage, backup and recovery boundaries;
- roles, publication gates and reduced public location precision;
- modular-monolith and private-storage architecture; and
- responsive and keyboard-accessible interaction evidence.

All views use safe aggregate counts or static boundary descriptions. They do
not expose row-level private records, identifiers, paths, credentials or
precise locations.
