# Group 04 Evidence Index

| Evidence | Requirement proved | Manual verification |
|---|---|---|
| `01_Admin_Photo_Intake_Form.png` | Owner-only one-photo intake, accepted JPEG/PNG/WebP/TIFF contract, 100 MiB application limit, dimension/pixel limits and permanent no-retention warning. | Opened `/admin/photo-intake` as Archive Owner and confirmed the single-file form and boundary wording. |
| `02_Valid_Photo_Intake_Record.png` | Exactly one fictional `UP_<ULID>` record, detected MIME/extension/size/dimensions, planned `archive_quarantine` relative path, pending defaults, null hashes/archive link and source bytes not retained. | Submitted one sanitized photo and reviewed the success/detail state. |
| `03_Incoming_Upload_Queue.png` | Read-only incoming-upload queue with fictional technical facts, planned paths, workflow states and no mutation actions. | Opened `/admin/photo-intake/queue` as Archive Owner. |
| `04_Incoming_Upload_Detail.png` | Read-only detail with logical disk and relative path only; null SHA-256, perceptual hash and archive link; `source_file_retained=false`. | Opened the created fictional upload record. |
| `05_Validation_Rejection_Matrix.png` | Distinct rejection states for unsupported type, MIME/extension mismatch, unreadable image and unsafe filename/path input. | Submitted generated disposable rejection fixtures; no records were created. |
| `06_Owner_Access_And_No_Write_Boundary.png` | Owner HTTP 200, authenticated non-owner HTTP 403, guest redirect, approved routes and all archive disks empty. | Ran focused access/no-write tests and route inspection. |
| `07_Group04_Validation_And_Repository_Closure.png` | Focused/full tests, no-write proof, production build, audits, Pack 1 commit, HEAD/origin synchronization and clean tree. | Ran the final Pack 1 validation and repository-state check. |

## Group 04 boundary

The application validates selected photo bytes only through the temporary upload lifecycle. Group 04 creates database records and deterministic planned paths only. It does not retain media bytes, calculate hashes, create manifests, promote originals, generate derivatives or perform duplicate matching.
