# Family Archive Group 02 Evidence

This folder contains the approved, sanitized evidence for Group 02: Core Archive Schema.

The evidence count is requirement-driven. Seven screenshots are included because each proves a distinct requirement. Repository synchronization and cleanliness are consolidated into the final validation screenshot rather than consuming a separate evidence slot.

## Evidence files

1. `01_Archive_Schema_Overview.png` — Owner-only overview of the three healthy core tables, fictional counts, migration health and original-preservation boundary.
2. `02_Media_Item_Demo_Record.png` — Fictional `MediaItem` metadata, review state and visibility, with explicit proof that direct original/web/thumbnail paths are not stored on the archive record.
3. `03_Incoming_Upload_Demo_Record.png` — Fictional intake record showing that `IncomingUpload` remains separate until approval and retains its source-file state.
4. `04_File_Version_Relationships.png` — Original, web display and thumbnail as separate `MediaFileVersion` records with derivative lineage and restrictive deletion behavior.
5. `05_Status_And_Visibility_Contracts.png` — Complete read-only enum and workflow contract matrix.
6. `06_Owner_Access_Boundary.png` — Consolidated Owner, non-owner and guest authorization proof, including active route middleware and the absence of mutation actions.
7. `07_Group02_Validation_And_Repository_Closure.png` — Consolidated PHP, migration, test, production build, audit, privacy, Git synchronization and clean-working-tree proof.

All displayed records and identities are fictional. No real family media, private names, email addresses, storage locations or local machine paths are included.
