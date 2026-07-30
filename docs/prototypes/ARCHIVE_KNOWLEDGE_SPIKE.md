# Archive Knowledge Integration Spike

Commit `7cabecd522e9c8fb664878f7fb67f92e6b58ced6` introduced a generated
Groups 13-20 integration spike. It is retained in repository history as
planning and compatibility evidence, not as eight closed roadmap groups.

## Canonical Boundary

- Groups 01-13 are implemented and evidence-closed.
- Group 14 is implemented and awaiting evidence review and closure.
- The `v0.20.0` label is reserved until Groups 13-20 are independently
  complete.
- External Packs 02-11 are planning references and must not be applied as
  finished releases.

## Inactive Prototype Surface

The generated Archive Knowledge controller, query service and view remain
available only as inactive reference code. Their route and navigation entry
are disabled by default behind
`FAMILY_ARCHIVE_ARCHIVE_KNOWLEDGE_PROTOTYPE`.

Enabling that flag is for local inspection only. The prototype does not
implement the review, privacy, authorization, revision or provenance
boundaries needed for a production feature.

## Provisional Schema

The already-applied migration
`2026_07_25_010000_create_archive_knowledge_tables.php` is not edited or
removed. Its tables are provisional:

- Group 13 refined `archive_events` and `archive_locations` through an
  additive migration.
- Group 14 refined `archive_people` and `family_branches` through an additive
  migration.
- Tables belonging to Groups 15-19 remain inactive and unused until their
  owning groups.
- Schema presence does not establish roadmap completion.
- Corrective work must not drop these tables or assume that every machine has
  identical rows.

Group 14 preserves the structured-date, provenance, original-storage, privacy
and human-review contracts closed through Group 13.
