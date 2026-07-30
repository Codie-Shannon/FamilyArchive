# Screenshot Group 03 Evidence Plan

Status: closed — evidence approved.

## Planned Captures

| Filename | Surface | Evidence |
|---|---|---|
| `01_v036_Release_Dashboard.png` | `/dashboard` desktop | v0.36.0 Collaboration & Restoration boundary |
| `02_Restoration_Workspace.png` | `/admin/restoration` desktop | Owner-only restoration and immutable-original boundary |
| `03_Versioned_Recipe_Queue.png` | Fictional recipe/job workspace | Versioned approved operations and queued review work |
| `04_Wasabi_External_Config_Boundary.png` | `/admin/restoration` | Fail-closed external configuration without secrets |
| `05_Restoration_Workspace_Mobile.png` | `/admin/restoration` mobile | Responsive Owner restoration workspace |
| `06_No_Original_Mutation_Validation.png` | Manual focused test | Source disk, path, hash, type, preference and count unchanged |
| `07_v036_Full_Validation.png` | Manual PowerShell validation | Tests, build, audits, migrations and repository state |

## Capture Requirements

- Fixture: isolated database with fictional recipes, jobs and media records
- Real images or archive records: prohibited
- Credentials, buckets, endpoints, original paths and hashes rendered: no
- Live Wasabi connection claimed: no
- Human approval: complete

The first five screenshots were captured from an isolated fictional browser
fixture. The repository owner ran both terminal validations manually and
supplied screenshots 06 and 07. All seven PNGs passed human review before
canonical evidence was committed.
