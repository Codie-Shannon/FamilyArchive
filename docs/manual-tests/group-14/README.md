# Group 14 Manual Test Plan

Status: implementation complete — evidence pending.

Use only fictional records. Do not enter real people, family history, addresses
or media.

## Preparation

1. Confirm Groups 01-13 are closed.
2. Confirm `php artisan migrate:status` reports no pending migrations.
3. Run `npm run build`.
4. Sign in as the verified fictional Owner.
5. Confirm the dashboard shows v0.14.0, completed Groups 01-13 and Group 14
   evidence pending.

## Reviewed Family Branch

1. Open `/archive/family-branches`.
2. Create a fictional branch with reviewed source evidence.
3. Confirm its stable `BRN-` identity and first immutable revision.
4. Edit the branch and confirm revision 2 is appended.
5. Mark a second fictional branch sensitive.
6. Confirm its stored name, description, people and provenance details are not
   present on browse surfaces.

## Reviewed Person

1. Open `/archive/people`.
2. Create a fictional person with a probable name, alternate name, year-only
   birth and decade-only death.
3. Confirm no month or day is displayed or stored for those facts.
4. Repeat with exact, approximate and unknown precision.
5. Confirm conflicting precision fields are rejected.
6. Confirm a living person cannot have a reviewed death date.
7. Confirm a suggestion-state or merged identity cannot be browsed as accepted
   knowledge.

## Privacy, Provenance and Revisions

1. Mark a fictional person sensitive.
2. Confirm the stored name, alternate names, life dates, notes, branch and
   provenance details are absent from browse output.
3. Attach an existing fictional source and matching scan batch to a
   non-sensitive person.
4. Confirm a batch from another source is rejected.
5. Confirm the provenance attachment increments the metadata revision.
6. Inspect person and branch revision history for actor, reason and changed
   fields.
7. Resubmit an older edit and confirm optimistic locking rejects it.

## Access and Deferred Features

1. Sign out and request both indexes; confirm authentication is required.
2. Sign in as a non-Owner verified user; confirm HTTP 403.
3. Confirm no relationship, person-photo tagging, identity merge or
   unknown-person resolution control is present.
4. Compare original, derivative and quarantine inventories before and after
   curation; confirm no preserved object changed.

## Manual Validation

Run `Validate-Group14.ps1` yourself from PowerShell after the six application
screenshots have been inspected. Capture the terminal result as screenshot 07.
The script does not capture or approve screenshots.

Group 14 remains open until the six application screenshots and your manual
validation screenshot are inspected and approved.
