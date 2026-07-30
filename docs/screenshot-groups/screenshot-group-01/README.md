# Screenshot Group 01 Evidence Plan

Status: closed — evidence approved.

This directory contains the application and manual validation evidence defined
by the generated Screenshot Group 01 pack.

## Planned Captures

| Filename | Surface | Evidence |
|---|---|---|
| `01_v020_Release_Dashboard.png` | `/dashboard` desktop | v0.20.0 Archive Knowledge release boundary |
| `02_Archive_Knowledge_Hub.png` | `/archive/knowledge` desktop | Reviewed knowledge counts and safe discovery surface |
| `03_Knowledge_Search_Results.png` | `/archive/knowledge?q=Wellington` desktop | Permission-aware reviewed search results |
| `04_Knowledge_Hub_Mobile.png` | `/archive/knowledge` mobile | Responsive private knowledge surface |
| `05_Private_Knowledge_Boundary.png` | Signed-out `/archive/knowledge` | Authentication boundary |
| `06_v020_Validation.png` | Manual PowerShell validation | Tests, build, audits, migrations and repository state |

## Capture Requirements

- Fixture: isolated database with fictional New Zealand records
- Real family identities, faces, records or media: prohibited
- Living-person detail rendered: no
- Sensitive or private location rendered: no
- Original storage paths, hashes or EXIF GPS rendered: no
- Human approval: complete

The first five screenshots were captured from an isolated fictional browser
fixture. The repository owner ran the validation script manually and supplied
screenshot 06. All six PNGs passed human review before canonical evidence was
committed.
