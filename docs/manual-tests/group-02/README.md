# Group 02 Manual Verification

Use fictional and sanitized data only. Do not capture real names, email addresses, family media, dates, storage locations or machine paths.

## Preconditions

- Pack 1 completed successfully on `main`.
- The Pack 1 commit is pushed and local `HEAD` equals `origin/main`.
- The working tree is clean.
- The application is running with the local fictional Group 02 demo records.
- Browser evidence is readable, unclipped and at least 1600 × 900.

## Browser checks

1. Sign in as the sanitized Owner account.
2. Open `/admin/archive-schema?view=overview` and confirm all three schema cards report Healthy, migrations show 3/3 applied and only fictional counts appear.
3. Open the Media item view and confirm archive ID, metadata, status and visibility render without direct original, web-display or thumbnail path fields.
4. Open the Incoming upload view and confirm intake remains a distinct record linked to the archive record only after approval.
5. Open File versions and confirm original, web display and thumbnail are separate records with explicit parent lineage and no cascade-delete behavior.
6. Open Status contracts and compare every value with the Group 02 contract.
7. Open Access boundary and confirm `web`, `auth`, `verified` and `owner` middleware plus the Owner/non-owner/guest response matrix.
8. Confirm the schema overview contains no upload, replacement, deletion or storage mutation controls.
9. Confirm `/register` remains HTTP 404.
10. Confirm the layout remains usable at a narrow responsive width as a manual check; no extra screenshot is required.

## Approved evidence filenames

1. `01_Archive_Schema_Overview.png`
2. `02_Media_Item_Demo_Record.png`
3. `03_Incoming_Upload_Demo_Record.png`
4. `04_File_Version_Relationships.png`
5. `05_Status_And_Visibility_Contracts.png`
6. `06_Owner_Access_Boundary.png`
7. `07_Group02_Validation_And_Repository_Closure.png`

The evidence count is requirement-driven. Git synchronization and clean-working-tree proof are consolidated into screenshot 07 rather than using a redundant standalone screenshot.
