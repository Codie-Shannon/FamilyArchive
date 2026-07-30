# v1 Acceptance and Custodianship

Screenshot Group 05 covers Build Groups 45–46 and carries the release metadata
`1.0.0` / `Family Archive v1.0`. That metadata identifies the release
candidate. It does not manufacture a family-pilot approval, a production
deployment or a confirmed custodian.

## Deterministic Acceptance

`AcceptanceMatrix` evaluates repository-backed readiness facts:

- pilot feedback has received review;
- no blocking pilot feedback remains open;
- no integrity repair case remains open;
- at least one verified backup record exists;
- a primary custodian has confirmed the designation; and
- a private storage provider has a recorded healthy or degraded state.

Each recorded run stores the complete gate result as immutable JSON evidence
and is marked `blocked` or `ready`. A deterministic `ready` result still does
not grant human acceptance.

## Human Gates

The Owner-only acceptance page exposes three deliberately separate human
gates:

- controlled family-pilot approval;
- production-deployment proof; and
- primary-custodian confirmation.

Missing evidence is displayed as missing. Tests and screenshot fixtures use
fictional, non-identifying records and must never be presented as real family,
production or custodian approval.

## Custodianship Boundary

A custodian designation records a proposed responsibility, role, scope and
confirmation state. It does not bypass authentication, authorization or the
existing Owner boundary. Designation changes append custodianship events so
the reason and responsible actor remain reviewable.

The operational handbook in
[`docs/operations/V1_CUSTODIANSHIP.md`](../operations/V1_CUSTODIANSHIP.md)
defines the human process for nomination, confirmation, handover and
revocation.

## Privacy

The acceptance surface reports categories, severity and state only. It does
not render participant identities, private family details, storage
coordinates, credentials, provider accounts or production endpoints.
