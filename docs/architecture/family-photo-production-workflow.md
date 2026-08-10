# Family Photo Production Workflow

The production family-photo batch is a split-first, fail-closed workflow. A
source is not approved merely because it has retained bytes or an existing
media record.

## Required phase order

1. Capture a read-only production snapshot and confirm storage capacity and
   provider rate limits.
2. Verify every promoted original against its database byte count and SHA-256.
   When it differs, recover only from an exact retained-quarantine match into
   a new no-overwrite object key, record and close an integrity repair case,
   and read the object back before cutover. Never change the immutable archive
   promotion record or delete the displaced object.
3. Run a representative canary and use its observed throughput for ETA.
4. Classify every in-scope source as a confident single photo, a reviewed
   multi-photo scan, or an ambiguity requiring review.
5. Resolve every ambiguity. Review and publish every multi-photo crop before
   original-photo approval starts.
6. Approve technically valid single photos under the owner-authorized policy.
7. Reconcile every source and generated output, then verify object
   availability.
8. Verify the approved gallery through an authenticated family-member access
   path. Only this phase permits a claim that the photos are live.

The census includes originals that are already family-visible. A combined
source remains preserved and is hidden only after all reviewed split outputs
have been written, read back, recorded, and made available to family members.
The documented Shannon Pictures exclusion remains in force.

Integrity recovery is separate from photo approval. It changes only the
mutable storage pointer on the original media-file version after an exact
readback; the promotion audit row and every pre-existing provider object remain
untouched. If an already-approved photo requires recovery, its preferred web
and thumbnail derivatives are regenerated to deterministic recovery keys and
verified before their records become preferred.

## Performance without weaker review

- Reuse retained hashes, existing split proposals, and verified recurring
  layout templates.
- Apply per-image edge validation even when a layout template is reused.
- Use confidence tiers to focus visual review on multi-photo and ambiguous
  sources; policy does not require identifying children individually.
- Detect aligned and uneven collage layouts with recursive full-span seam
  evidence, remove low-information gutter regions, and keep every proposed
  multi-photo source behind visual review.
- Generate a deterministic five-percent audit sample (minimum 50 while
  available) of high-confidence single-photo classifications before the
  readiness gate can pass.
- Defer OCR, face recognition, names, dates, and descriptions.
- Use bounded parallel queues for detection, rendering, derivative generation,
  and object checks, with storage backpressure.
- Quarantine individual failures and continue processing the independent work.

Automatic crop signals identify minimum-size, transparent-edge, low-detail,
rotation, and clipping concerns. They prioritize review but never replace the
required visual crop decision.

## Durable controls

The owner policy, readiness ledger, worker state, rotating logs, quarantine
ledger, and final reconciliation are stored outside conversation context. The
approval worker verifies the policy SHA-256 and refuses to mutate production
unless capacity, canary, exact census, and crop review gates are complete.

Worker state includes a run ID, process ID, start time, heartbeat, policy
digest, phase, and last completed item. Generated split candidates use a
deterministic render key and verified write-back so replay reuses identical
outputs. Individual technical failures retain their production attention code
and are appended to a bounded quarantine log.

An approval run ends in `awaiting_verification`, not `complete`. The final
reconciliation explicitly remains non-live until object availability and the
authenticated gallery have both been verified.
