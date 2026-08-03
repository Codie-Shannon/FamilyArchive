# Threat Model

## Assets

- Immutable family-photo originals
- Restoration and viewing derivatives
- Descriptive metadata, provenance and family relationships
- Account, role, branch and original-access decisions
- Credentials, storage identities and operational logs

## Trust boundaries

| Boundary | Main control |
| --- | --- |
| Browser to application | Authentication, authorization, CSRF protection and restrictive headers |
| Incoming media to quarantine | Content validation, checksum verification and private storage |
| Quarantine to original | Human acceptance, no-overwrite identity and append-only evidence |
| Original to derivative | Explicit lineage, versioned output and human preference decision |
| Application to Wasabi | Least-privilege credentials, isolated prefixes and exact-version readback |
| Private archive to public discovery | Separate reviewed read model and location precision reduction |
| Local migration source to archive | Read-only inventory, drift detection, checkpoints and reconciliation |

## Principal threats and mitigations

| Threat | Mitigation |
| --- | --- |
| Unauthorized archive access | Invite approval, role and branch policies, explicit original grants |
| Original replacement or deletion | Immutable identity, no-overwrite writers, object versioning and copy-first migration |
| Malicious or malformed upload | Signature and decode validation, quarantine and bounded processing |
| Duplicate or replayed retention | Content hashes, database uniqueness and idempotency checks |
| Processing damages the only copy | Every candidate derives from a verified original; originals remain unchanged |
| Sensitive knowledge leaks publicly | Reviewed publication model, coarse map points and permission-aware queries |
| Private message misuse | Participant blocking, report-specific moderation and limited operational views |
| Credential or infrastructure disclosure | Environment-managed secrets and privacy-safe evidence checks |
| Interrupted large migration | Durable checkpoints, bounded retries, failure isolation and exact reconciliation |
| Source changes during migration | Immediate pre-write checksum verification and fail-closed inventory drift handling |

## Residual risk

Operational security still depends on account protection, secret rotation,
provider availability, database backups, monitoring and recovery exercises.
Image heuristics can produce poor suggestions, so human review and the manual
original-first editor remain essential. The synthetic qualification run does not
remove the need for a private rehearsal before the real family migration.
