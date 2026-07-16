# Group 06 Evidence Index

| File | Requirement-driven proof |
|---|---|
| `01_Exact_Duplicate_Candidate_Overview.png` | Owner-only exact duplicate candidate queue showing two pending candidates, both target types, truncated SHA-256 facts and the manual-review-only warning. |
| `02_Exact_Match_Candidate_Detail.png` | Read-only source/target comparison with `exact_sha256`, deterministic confidence `1.0000`, pending review and the permanent preservation boundary. |
| `03_No_Match_Incoming_Upload_State.png` | Retained incoming upload with `duplicate_status=no_match`, pending review, no archive link, no original version, no derivatives and no mutation controls. |
| `04_Idempotency_And_Multiple_Target_Contracts.png` | Focused detection tests proving idempotency, one candidate per distinct eligible target, original-target matching, rollback and storage neutrality. |
| `05_Preservation_And_No_Storage_Mutation.png` | Focused test proving private archive disk inventory remains unchanged. |
| `06_Owner_Access_And_Read_Only_Boundary.png` | Access tests proving Owner read access, non-owner/guest protection and absence of mutation or resolution routes. |
| `07_Group06_Validation_And_Repository_Closure.png` | Full regression, production build, clean dependency audits, Pack 1 commit, synchronized `HEAD`/`origin/main` and clean working tree. |
