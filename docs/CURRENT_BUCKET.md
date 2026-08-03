# Current Maintenance Boundary

Family Archive v3.8.0 is product-complete and its 33 official screenshot groups
are approved and closed. Work now belongs to maintenance and migration readiness,
not uncontrolled feature expansion.

## In scope

- Security and dependency maintenance
- Defect fixes and compatibility updates
- Accessibility, performance and operational hardening
- Documentation and evidence corrections
- Private preflight and rehearsal for the real family-photo migration
- Bounded execution, monitoring, reconciliation and rollback planning for that migration

## Out of scope without a new release boundary

- A speculative Screenshot Group 34
- New social-network features or unsupported media types
- Public evidence containing real family data
- Destructive source cleanup during migration
- Claims that the real 30,000-photo migration has completed before reconciliation

## Operator-owned next step

The real source collection must remain outside the repository. Before import, the
operator selects the private source root, confirms destination capacity and runs
the read-only migration preflight. The system must stop on inventory drift,
retain completed checkpoints and isolate failures without deleting source files.
