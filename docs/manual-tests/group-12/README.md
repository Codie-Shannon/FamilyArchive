# Group 12 Manual Test Plan

Use only the isolated `Group12DemoSeeder` dataset. Confirm the database contains
no non-demo media records before capturing evidence.

## Structured Date Review

1. Sign in as the fictional verified Owner.
2. Open `G12-DEMO-001` and select **Edit metadata**.
3. Confirm the structured date form exposes representation, confidence, review
   state, source note and reasoning.
4. Confirm exact, year-only, decade-only, approximate and unknown
   representations require only their valid fields.
5. Confirm a stale revision, future date, conflicting representation or missing
   required source note is rejected without creating a revision.

## Approved Date Detail

1. Open the approved photo detail.
2. Confirm the reviewed year, precision, confidence, accepted state, source note
   and reasoning are displayed.
3. Confirm no original filename, path, hash or download action is present.

## Source Collection and Scan Batch

1. Open **Source Provenance**.
2. Confirm the fictional physical album has a stable `SRC-` identifier.
3. Open the collection detail and confirm the stable `SCAN-` batch and linked
   approved photo.
4. Confirm an unapproved photo cannot appear on the collection detail.

## Provenance Revision

1. Open the photo revision history.
2. Confirm the date review and provenance attachment are separate immutable
   revisions.
3. Open the provenance revision and confirm stable source and scan-batch IDs are
   shown in the before/after evidence.
4. Confirm there is no update, delete, revert, rewrite, download or storage
   action on the revision.

## Access and Preservation

1. Sign out and request a source, date-edit or provenance route.
2. Confirm the request redirects to login.
3. Run the focused Group 12 tests.
4. Confirm the no-storage-mutation assertions pass for originals, derivatives
   and quarantine.
