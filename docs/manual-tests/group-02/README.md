# Group 02 Manual Verification

Use fictional/sanitized data only. Do not capture real names, email addresses,
family media, dates, storage locations or machine paths.

## Preconditions

- Pack 1 completed successfully on `main`.
- The Pack 1 commit is pushed and local `HEAD` equals `origin/main`.
- The working tree is clean.
- The application is running with the local fictional Group 02 demo records.
- Browser viewport is 1600 x 900 with the existing dark theme.

## Browser checks

1. Sign in as the sanitized Owner account.
2. Open `/admin/archive-schema?view=overview`.
3. Confirm all three schema cards report Healthy and show fictional counts.
4. Open the Media item view and confirm archive ID, metadata, status and
   visibility render without any direct original/web/thumbnail path fields.
5. Open the Incoming upload view and confirm intake remains a distinct record
   linked to the archive record only after approval.
6. Open File versions and confirm original, web display and thumbnail are three
   records with explicit parent lineage.
7. Open Status contracts and compare every value with the Group 02 contract.
8. Open Access boundary and confirm the route shows `auth`, `verified` and `owner` middleware plus the Owner/non-owner/guest response matrix.
9. Confirm the page has no upload, replacement, deletion or storage mutation
   controls.
10. Confirm the layout remains usable at 1600 x 900 and at a narrow responsive
   width.

## Access checks

1. Confirm the Owner receives HTTP 200 for `/admin/archive-schema`.
2. Confirm an authenticated non-owner receives HTTP 403.
3. Confirm a guest is redirected to login.
4. Confirm `/register` remains HTTP 404.

## Required Pack 1 screenshot filenames

1. `01_Archive_Schema_Overview.png`
2. `02_Media_Item_Demo_Record.png`
3. `03_Incoming_Upload_Demo_Record.png`
4. `04_File_Version_Relationships.png`
5. `05_Status_And_Visibility_Contracts.png`
6. `06_Owner_Access_Boundary.png`
7. `07_Group02_Tests_Build_And_Security.png`
8. `08_Group02_Git_Sync_And_Clean.png`

Do not commit screenshots until they have been reviewed and approved for Pack 2.
