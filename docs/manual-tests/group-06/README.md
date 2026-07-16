# Group 06 manual verification

Use fictional data only. Run against a disposable local database and private local test disks.

1. Run migrations and `php artisan db:seed --class=Database\\Seeders\\Group06DemoSeeder`.
2. Sign in as Owner and open `/admin/duplicate-candidates`; confirm pending exact matches, truncated hashes and the manual-review warning.
3. Open both candidate details; confirm one retained IncomingUpload target and one original MediaFileVersion target, `exact_sha256`, confidence `1.0000`, and the preservation boundary.
4. Open `UP-G06-NOMATCH-001`; confirm `duplicate_status=no_match` and no candidate row.
5. Run `php artisan archive:detect-exact-duplicates UP-G06-SOURCE-001` twice; confirm the same candidate set and no duplicate rows.
6. Record inventories for `archive_originals`, `archive_derivatives`, `archive_quarantine`, and `archive_manifests` before and after detection; confirm identical paths.
7. Confirm guest redirect, authenticated non-owner 403, and Owner 200 for queue and detail.
8. Run `php artisan route:list --path=duplicate-candidates`; confirm GET/HEAD only and no download, delete, replacement, promotion or resolution route.
9. Run focused Group 06 tests, the full regression suite, production build, audits, privacy scan and Git hygiene checks.
