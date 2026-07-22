# Family Archive Group 08 Evidence Closure

## Baseline

Pack 2 is pinned to the synchronized post-security-patch Pack 1 baseline:

`a4aea64d59e6de3dcf5225b999335b3a913333d2`

## Closed capability

An Owner can accept one eligible retained photo into the archive. The workflow allocates a stable photo archive ID, copies the quarantined source to private original storage without overwrite, verifies exact bytes and SHA-256, creates the MediaItem and original MediaFileVersion, links the IncomingUpload, records immutable promotion evidence, and preserves the quarantine source.

## Permanent boundary

Group 08 does not move or delete quarantine evidence, overwrite an existing original, generate derivatives, expose an original download, attach alternate sources, add public URLs, or provide bulk acceptance. Newly written uncommitted output may be removed only as scoped rollback residue when acceptance fails.

## Pack 2 verification

The finalization runner validates exactly seven distinct readable PNGs, exact approved hashes, absent PNG text/EXIF metadata, scoped privacy rules, focused Group 08 tests, full regression, PHPStan, Composer validation, production build, Composer and npm security audits, byte-for-byte archive-storage neutrality, Git hygiene, exact evidence scope, commit, push, re-fetch, synchronized HEAD and a clean working tree.

Expected commit subject:

`Close Family Archive Group 8 evidence`
