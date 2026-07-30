# Screenshot Group 04 Evidence Plan

Status: closed — evidence approved.

## Planned Captures

| Filename | Surface | Evidence |
|---|---|---|
| `01_v044_Release_Dashboard.png` | `/dashboard` desktop | v0.44.0 Integrity & Production boundary |
| `02_Integrity_Operations_Dashboard.png` | `/admin/operations` desktop | Owner-only integrity and operational overview |
| `03_Verification_And_Repair_State.png` | Fictional mismatch and repair workspace | Observation without automatic repair or original mutation |
| `04_Backup_And_Recovery_Readiness.png` | `/admin/operations` | Synthetic isolated rehearsal state without production claims |
| `05_Operations_Responsive.png` | `/admin/operations` mobile | Responsive Owner operations dashboard |
| `06_No_Overwrite_Transfer_Test.png` | Manual focused test | Existing source and destination preserved when transfer is refused |
| `07_v044_Full_Validation.png` | Manual PowerShell validation | Tests, build, audits, migrations and repository state |

## Capture Requirements

- Fixture: isolated database with fictional operations records
- Real media, providers, accounts and infrastructure: prohibited
- Credentials, endpoints, paths, hashes and real capacity rendered: no
- Simulated restore, hosting or monitoring presented as production proof: no
- Human approval: complete

The first five screenshots were captured from an isolated fictional browser
fixture. The repository owner ran both terminal validations manually and
supplied screenshots 06 and 07. All seven PNGs passed human review before
canonical evidence was committed.
