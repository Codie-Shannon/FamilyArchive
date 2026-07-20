# Family Archive Group 08 Manual Verification

Use fictional or generated media only. Never point these steps at real family media.

1. Launch the application and seed `Database\Seeders\Group08DemoSeeder`.
2. Open `/admin/archive-promotions` as a verified Owner.
3. Confirm eligible, blocked and promoted sections are separate and the no-overwrite/quarantine-preservation warning is visible.
4. Open `UP-G08-ELIGIBLE-001` and verify photo facts, source bytes, SHA-256, duplicate state, retained source and null archive link.
5. Select **Accept and verify original** once.
6. Confirm a stable `PH_` archive ID, approved MediaItem, ready original MediaFileVersion and immutable promotion audit.
7. Confirm source/target bytes and SHA-256 are equal, the original uses `archive_originals`, and the MediaItem itself has no path.
8. Confirm the quarantine path and retained state remain unchanged and no derivative record exists.
9. Run `php artisan test tests/Feature/Group08 --stop-on-failure` to prove collision and rollback cleanup, no orphan records, archive-ID non-reuse and access boundaries.
10. Confirm no download, delete, cleanup, derivative, public URL, bulk approval or overwrite control exists.

Required screenshots:

- `01_Archive_Acceptance_Review.png`
- `02_New_Media_Item_And_Archive_ID.png`
- `03_Original_Promotion_Integrity.png`
- `04_Quarantine_Source_Preserved.png`
- `05_Promotion_Failure_Recovery.png`
- `06_Owner_Access_And_No_Derivative_Boundary.png`
- `07_Group08_Validation_And_Repository_Closure.png`
