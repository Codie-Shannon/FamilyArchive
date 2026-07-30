# Reviewed Events and Locations

## Status

Group 13 implementation is complete. Evidence is pending.

This status does not close Group 13. Only human-reviewed screenshots and final
evidence validation can change the group to closed.

## Boundary

Group 13 adds reviewed historical events, normalized locations and
provenance-aware browsing. It does not activate the provisional people,
relationships, identity-resolution, saved-view or collection schemas owned by
Groups 14-19.

All Group 13 routes remain inside the authenticated, verified Owner boundary.
The broader role and policy model remains Group 20 scope.

## Reviewed Records

`ArchiveEvent` and `ArchiveLocation` use stable public identifiers:

- events use `EVT-` plus a ULID;
- locations use `LOC-` plus a ULID.

Creation and updates run through transactional review actions. Each accepted
change records its actor, review timestamp, reason and monotonic metadata
revision.

Optimistic locking rejects a form opened against an older revision.

## Uncertain Event Dates

Event dates use the same preservation principle established by Group 12:
missing precision is never manufactured.

Supported representations are:

| Precision | Stored facts |
|---|---|
| Exact | Start date and optional end date |
| Approximate | Approximate start date and optional end date |
| Year only | Year without an invented month or day |
| Decade only | Decade without an invented year |
| Unknown | No date values |

Confidence and source notes remain separate from precision. Known dates require
a source note. Unknown dates require unknown confidence.

## Location Privacy

Location precision is one of country, region, locality, exact or private.

A sensitive location must use private browse precision. Accepted browse pages
replace its stored label and locality with:

```text
Private family location
Exact location details are withheld from archive browsing.
```

The stored curation values remain available only on the Owner review form.
They are not rendered by event indexes, event details, location indexes or
location details.

## Provenance and Media Links

An event may link to:

- an existing `SourceCollection`;
- an optional `ScanBatch` belonging to that source; and
- approved archive media.

Every link is human-reviewed and increments the event revision. Media links
require explicit confidence and a source note. Unapproved media cannot be
linked.

Browse read models select archive identity and descriptive facts only. They do
not load or render file hashes, original paths, derivative paths or embedded
GPS.

## Immutable Evidence

`archive_event_revisions` and `archive_location_revisions` are append-only.
Application-level update and delete attempts throw. Revision records include:

- actor;
- before and after revision numbers;
- changed fields;
- before and after values;
- review reason; and
- creation time.

Source and media attachments are represented as revision changes rather than
silent relationship mutations.

## Storage Boundary

Group 13 writes database knowledge only. Its actions do not call archive,
derivative or quarantine storage disks. Automated tests use fake disks and
assert that no objects are created, moved, renamed, replaced or deleted.

## Prototype Compatibility

The original `7cabecd` integration-spike migration remains applied and
unchanged. Group 13 refines only its event, location and event-media structures
through the additive
`2026_07_30_120000_refine_group13_events_and_locations` migration.

The migration is restart-safe because MySQL may commit individual DDL
statements. It uses explicit short index names compatible with MySQL's
identifier limit. No prototype table is dropped.
