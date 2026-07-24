# Reviewed Date and Provenance Decision

## Decision

Historical date precision, confidence, review state and source evidence are
separate archive facts. Physical provenance uses stable source and scan-batch
records linked to media through reviewed, revisioned relationships.

## Why

Historical family material commonly provides incomplete or conflicting dates.
Storing `1964`, `1960s` or `around 16 February 1974` as if each were the same
kind of exact date would manufacture certainty.

Likewise, a photo may exist in several albums, envelopes or scan sessions.
Provenance belongs in explicit reusable records rather than free text or file
paths.

## Consequences

- Unknown precision is represented explicitly.
- Year-only and decade-only facts do not invent lower-order date components.
- Suggestions remain distinguishable from accepted facts.
- Every accepted change records source, reasoning and an immutable revision.
- One photo may link to multiple sources.
- Scan batches cannot be attached across source boundaries.
- Source identity remains stable even if its descriptive label changes later.
- No date or provenance action mutates preserved media.
