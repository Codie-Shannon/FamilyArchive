# Collaboration and Restoration

Status: Screenshot Group 03 closed — evidence approved.

Screenshot Group 03 covers Build Groups 29–36 as the v0.36.0 Collaboration &
Restoration release.

## Collaboration Records

Identity suggestions and archive notifications are recorded separately from
accepted archive facts. Suggestions remain pending until an appropriate human
review workflow accepts or rejects them.

## Versioned Processing

Restoration recipes:

- have stable recipe identities and explicit versions;
- accept only the approved operation vocabulary;
- reject empty or unknown operations;
- may be marked as batch profiles without processing automatically; and
- never change an original or preferred version directly.

Jobs may be queued only from a preferred original. Queueing preserves the
source storage disk, path, hash, preferred flag and record count. Candidate
output belongs in a separate media-file version and restoration-candidate
record for human review.

## Provider Boundary

The local provider is private and configured. Wasabi remains an external
configuration boundary:

- credentials, buckets and endpoints come only from environment configuration;
- absent fields produce an unconfigured, fail-closed result;
- values are never rendered by the restoration workspace;
- no secrets are stored in Git; and
- configuration presence does not claim a live connection or successful
  remote write.

Real credentials and live provider verification remain external manual work.
