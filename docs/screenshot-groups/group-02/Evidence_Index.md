# Group 02 Evidence Index

This index maps each approved screenshot to the distinct requirement it proves. The seven-file evidence set supersedes the earlier padded eight-file layout; Git synchronization and cleanliness are included in the consolidated validation evidence.

| File | Requirement proved | Manual checks covered |
|---|---|---|
| `01_Archive_Schema_Overview.png` | Core schema health and preservation boundary | All three schema cards load; migrations show 3/3 applied; fictional counts only; no mutation controls. |
| `02_Media_Item_Demo_Record.png` | MediaItem record boundary | Metadata, status and visibility render; no direct original, web-display or thumbnail path fields exist on MediaItem. |
| `03_Incoming_Upload_Demo_Record.png` | Intake/archive separation | IncomingUpload exists separately, links only after approval and visibly retains its source state. |
| `04_File_Version_Relationships.png` | Original/derivative separation and lineage | Original, web display and thumbnail are distinct records; derivatives identify the original parent; no cascade-delete boundary is shown. |
| `05_Status_And_Visibility_Contracts.png` | Exact enum contracts | All Group 02 media, review, visibility, sensitivity, date, processing, duplicate, version and generation values are visible. |
| `06_Owner_Access_Boundary.png` | Owner-only authorization | Owner HTTP 200, non-owner HTTP 403, guest login redirect and `web`, `auth`, `verified`, `owner` middleware are consolidated. |
| `07_Group02_Validation_And_Repository_Closure.png` | Automated validation and repository closure | PHP syntax, disposable migrations, tests, production build, audits, privacy scan, migration status, branch, push, HEAD equality and clean tree all pass. |

## Capture integrity

- Each file is a non-empty PNG with dimensions of at least 1600 × 900.
- Filenames are exact and no additional PNG evidence files are permitted.
- Each screenshot proves a different requirement.
- No real names, account details, family media, local paths or private storage locations appear.
