# Integrity and Production

Status: Screenshot Group 04 closed — evidence approved.

Screenshot Group 04 covers Build Groups 37–44 as the v0.44.0 Integrity &
Production release.

## Verified Transfer

Archive transfers:

- refuse to write when the destination object already exists;
- verify the source against its expected SHA-256 identity before writing;
- read the new destination object back and compare both bytes and SHA-256;
- remove only the newly written destination candidate when read-back
  verification fails; and
- never modify or delete the source object.

A verified transfer is a storage fact, not permission to cut over or remove the
previous provider copy.

## Integrity Observation

Integrity checks append an observation with a safe result such as verified,
missing, size mismatch, hash mismatch or provider error. A failed check does not
repair, replace, promote or delete a stored object.

Repair cases are separate records for human review. Recovery sources and new
object paths are not rendered by the operations dashboard.

## Operational Readiness

The release records:

- versioned integrity manifests;
- scan-import reconciliation state;
- backup-verification and isolated restore-rehearsal results; and
- safe operational event summaries.

The dashboard omits provider accounts, endpoints, credentials, storage paths and
real capacity figures. Rehearsal metrics are synthetic evidence, not proof of a
production restore, deployment, monitoring delivery or live cloud migration.

Production hosting, external monitoring, provider credentials and restore
infrastructure remain external manual work.
