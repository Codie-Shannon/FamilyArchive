# Group 07 — Controlled Manual Duplicate Review Decisions

Use fictional or sanitized records only. Sign in as a verified Owner and open `/admin/duplicate-candidates`.

1. Confirm pending and resolved queues are separate.
2. Record a confirmed-duplicate decision and confirm the retained-source warning remains visible.
3. Record alternate-source with a note; confirm no archive attachment or promotion control exists.
4. Record related-but-distinct and not-duplicate decisions; confirm intake remains unapproved.
5. Correct a decision with an explicit reason and confirm both immutable audit events remain visible.
6. Verify guest redirect, non-owner 403, and no delete/download/merge/promotion routes.
7. Capture the consolidated Pack 1 validation and synchronized repository closure output.

Required evidence filenames are exactly the seven names defined in the Group 07 private build-context PDF. Never commit that PDF or real family media.


## Static-analysis compatibility

Pack 1 also corrects two pre-existing type declarations/checks required for the repository-wide PHPStan level 7 gate: nullable incoming-upload SHA-256 metadata and the guaranteed `getimagesize()` result shape after a false-result check. These changes do not alter Group 07 storage or decision behavior.

