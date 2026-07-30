# Group 13 Manual Test Plan

Status: implementation complete, evidence pending.

Use only fictional records. Do not enter real addresses, people, family
history or media.

## Preparation

1. Confirm the branch contains the five approved corrective commits.
2. Confirm `php artisan migrate:status` reports no pending migrations.
3. Run `npm run build`.
4. Sign in as the verified fictional Owner.
5. Confirm the dashboard says Groups 01-12 are complete and Group 13 evidence
   is pending.

## Reviewed Location

1. Open `/archive/locations`.
2. Confirm the empty state is safe when no accepted records exist.
3. Add a fictional locality-level location with a source note and review
   reason.
4. Confirm its `LOC-` identity, confidence and immutable revision.
5. Edit the record with its current revision.
6. Confirm revision 2 is appended.
7. Resubmit an older form and confirm the stale revision is rejected.

## Sensitive Location

1. Add a fictional sensitive location using private precision.
2. Confirm locality precision plus the sensitive flag is rejected.
3. Open the location index and detail page.
4. Confirm the stored label and locality are absent.
5. Confirm `Private family location` and the precision-withheld explanation
   appear.

## Reviewed Event

1. Open `/archive/events`.
2. Add an event with a year-only date.
3. Confirm no month or day is displayed or stored.
4. Repeat with exact, approximate, decade-only and unknown dates.
5. Confirm conflicting date fields are rejected.
6. Confirm a suggestion-state event cannot be browsed as accepted knowledge.

## Provenance and Media

1. Attach an existing fictional source collection and matching scan batch.
2. Confirm a batch from another source is rejected.
3. Attach an approved fictional photo with confidence and a source note.
4. Confirm unapproved media is rejected.
5. Confirm the event detail links to the source, batch and archive photo.
6. Inspect the page source and confirm it contains no storage paths, SHA-256
   values or EXIF GPS.

## Access and Preservation

1. Sign out and request both indexes; confirm login redirection.
2. Sign in as a non-Owner verified user; confirm HTTP 403.
3. Compare original, derivative and quarantine inventories before and after
   curation.
4. Confirm no preserved object changed.

## Closure Rule

Do not mark Group 13 closed until the planned screenshots are human-captured,
inspected and approved, then the complete validation suite is rerun.
