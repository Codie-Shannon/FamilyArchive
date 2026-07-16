# Group 05 Evidence Index

| File | Requirement proven | Manual review |
|---|---|---|
| `01_Quarantine_Retention_Result.png` | Owner submission reports retained in private quarantine and explicitly states not approved, not promoted and no derivatives. | Success banner, upload ID, retention and integrity state are readable. |
| `02_Retained_Incoming_Upload_Detail.png` | `UP_<ULID>`, relative quarantine path, exact bytes, SHA-256, `retained=true`, `retained_at` and null archive/perceptual fields. | Full retained record detail is visible without absolute storage roots. |
| `03_Incoming_Queue_Retention_State.png` | Queue shows retained and verified states without archive approval or mutation controls. | Top retained row and read-only boundary are visible. |
| `04_No_Overwrite_Collision.png` | Forced collision fails closed and preserves the pre-existing object. | Focused collision test passes with four assertions. |
| `05_Failure_Recovery_And_Disk_Isolation.png` | Controlled retention verifies bytes/SHA-256 and leaves originals, derivatives and manifests isolated. | Focused persistence test passes with nine assertions. |
| `06_Owner_Access_And_No_Download_Boundary.png` | Owner-only read-only access and absence of download/delete/replace/promote/approve/share actions. | Owner label, integrity state and explicit no-action boundary are visible. |
| `07_Group05_Validation_And_Repository_Closure.png` | Focused Group 04/05 tests, audits, Pack 1 commit, HEAD/origin synchronization and clean tree. | Ten tests and 57 assertions pass; both audits pass; branch and commit closure match. |

## Closure contract

- Pack 1 commit: `d772a28dea4389c360c0d207b2b01de3f16f814e`
- Pack 1 subject: `Build Family Archive Group 5 quarantine persistence`
- Pack 2 subject: `Close Family Archive Group 5 evidence`
- Evidence destination: `docs/screenshot-groups/group-05/`
- Required count: exactly seven PNG screenshots
