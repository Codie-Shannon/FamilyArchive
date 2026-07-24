# Build and Evidence Process

Each roadmap group is completed in two controlled phases.

## Phase 1 - Implementation

1. Verify `main` is synchronized and the working tree is clean.
2. Implement one bounded capability slice.
3. Run focused tests, then commit, push and resynchronize.
4. Add a second related slice only when it belongs to the same official group.
5. Run focused tests and the combined regression suite.
6. Run the production asset build, static analysis, privacy checks and
   `git diff --check`.
7. Prepare fictional demonstration data only after implementation is stable.

Implementation must preserve all earlier group contracts. A new group cannot
weaken original preservation, privacy, authorization, integrity or audit
boundaries established by a completed group.

## Phase 2 - Evidence Closure

1. Capture the planned screenshots as one coherent evidence set.
2. Use approved, numbered filenames.
3. Verify every PNG is readable and has the expected dimensions.
4. Copy only approved evidence into `docs/screenshot-groups/group-XX/`.
5. Add or update that folder's `README.md` and `Evidence_Index.md`.
6. Rerun tests, the production build, analysis, privacy checks and repository
   validation after evidence is filed.
7. Confirm the working tree contains only the intended evidence changes.
8. Close the group using the established evidence commit convention.

Validation proof is the final screenshot in a group.

## Screenshot Ordering

When a screenshot plan is prepared, list artifacts in this order:

1. desktop launch script, when required;
2. desktop screenshots;
3. mobile or browser launch script, when relevant;
4. mobile or browser screenshots; and
5. validation script and final validation proof.

## Evidence Rules

- Screenshots must use fictional, synthetic archive material.
- No real family identities, faces, records or media may appear without
  explicit approval.
- Evidence must prove the capability, access boundary, persistence and relevant
  preservation constraints.
- Private chat context and planning PDFs are never repository evidence.
- Evidence documentation must distinguish PNG screenshot count from total files
  in the evidence folder.
