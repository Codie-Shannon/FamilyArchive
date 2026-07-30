# Reviewed People and Family Branches

Status: Group 14 implemented — evidence pending.

Group 14 refines the provisional `archive_people` and `family_branches` tables
through a forward-only migration. It does not activate the provisional
relationship, person-media, merge or unknown-person workflows owned by later
groups.

## Permanent Records

- `ArchivePerson` has a stable `PER-` identity.
- `FamilyBranch` has a stable `BRN-` identity.
- accepted records are the only records available on browse surfaces;
- suggestion records remain outside accepted archive knowledge;
- people may reference only an accepted family branch; and
- the original provisional migration remains unchanged.

## Uncertain Facts

Person names carry explicit certainty: confirmed, probable, uncertain or
unknown. Birth and death evidence independently records exact, approximate,
year-only, decade-only or unknown precision. Conflicting fields are rejected
instead of being normalized into invented dates.

Alternate names remain separate reviewed values. Living people cannot receive
a reviewed death date, and death evidence cannot precede birth evidence.

## Privacy

All Group 14 routes require a verified Owner. A sensitive person or family
branch is additionally redacted on browse surfaces:

- stored names and alternate names are withheld;
- life dates and reviewed notes are withheld;
- branch membership and member lists are withheld; and
- provenance descriptions are withheld.

The controlled review form remains available to the Owner so facts can be
corrected without weakening the browse boundary.

## Provenance and Revisions

People and branches may link to existing source collections and matching scan
batches. A mismatched batch is rejected. Every create, reviewed edit or
provenance attachment appends an immutable revision containing the actor,
reason, changed fields and before/after evidence. Optimistic locking rejects
stale edits.

Group 14 changes database knowledge only. It does not move, rename, replace,
delete or expose quarantine objects, archive originals or viewing derivatives.

## Deferred Boundaries

- family relationships and person-media tagging belong to Group 15;
- unknown-person identity resolution and merges belong to Group 16; and
- the generated Archive Knowledge Hub remains an inactive prototype.
