# Group 14 Evidence Plan

Status: implementation complete — evidence pending.

This directory will contain six application screenshots produced from an
isolated fictional Group 14 fixture and one PowerShell validation screenshot
captured manually by the repository owner.

## Planned Captures

| Filename | Surface | Evidence |
|---|---|---|
| `01_Reviewed_People_Index.png` | `/archive/people` | Accepted identities, uncertain life dates and sensitive-person redaction |
| `02_Reviewed_Person_Detail.png` | Person detail | Stable identity, probable name, alternate name and incomplete life dates |
| `03_Family_Branch_Detail.png` | Family branch detail | Stable branch identity and reviewed member grouping |
| `04_Sensitive_Person_And_Branch_Redaction.png` | Sensitive person detail | Stored identity facts withheld from browsing |
| `05_Person_Source_Provenance.png` | Person detail | Source collection, scan batch and reviewed provenance note |
| `06_Immutable_Person_Branch_Revisions.png` | Person detail | Append-only revision history with actor, fields and reasons |
| `07_Group14_Validation_And_Evidence_Pending.png` | Manual PowerShell validation run | Tests, build, audits, migrations and evidence-pending repository state |

## Capture Requirements

- Canvas: 1920 × 1080 PNG
- Application screenshots: 6
- Manual validation screenshots: 1
- Fixture: isolated SQLite database with fictional New Zealand records
- Real family identities, faces, records or media: prohibited
- Sensitive stored person and branch facts rendered: no
- Original storage paths, hashes or EXIF GPS rendered: no
- Human approval: pending

The application screenshots may be automated in an isolated browser. The
validation script must be run and captured manually by the repository owner.
All seven PNGs require human approval before canonical evidence is committed.
