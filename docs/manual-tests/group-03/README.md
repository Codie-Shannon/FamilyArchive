# Group 03 manual verification

1. Sign in as the fictional Owner account and open `/admin/archive-storage`.
2. Confirm all four logical disks show healthy, private, and no URL exposure without absolute roots.
3. Confirm `PH`, `VD`, `DC`, `AU`, and `OT` examples use at least six digits.
4. Confirm bucket examples: `PH_000999 -> 000`, `PH_001000 -> 001`, and `PH_030000 -> 030`.
5. Confirm original, edited full, web display, and thumbnail examples use separate logical disks and relative paths.
6. Confirm quarantine and manifest examples are labelled as path planning only.
7. Confirm traversal, absolute path, drive-letter, backslash, empty-segment, invalid-extension, and null-byte inputs are rejected.
8. Confirm there are no upload, write, copy, move, replace, regenerate, or delete controls.
9. Confirm a non-owner receives HTTP 403 and a guest is redirected to login.
10. Confirm `/admin/archive-schema`, authentication, dashboards, and registration blocking still work.
11. Confirm the page is usable at desktop and narrow responsive widths.

## Exact screenshots after Pack 1 closure

- `01_Archive_Storage_Overview.png`
- `02_Stable_Archive_ID_Contracts.png`
- `03_Original_And_Derivative_Paths.png`
- `04_Bucket_And_Manifest_Path_Examples.png`
- `05_Path_Security_Rejections.png`
- `06_Owner_Access_And_Read_Only_Boundary.png`
- `07_Group03_Validation_And_Repository_Closure.png`
