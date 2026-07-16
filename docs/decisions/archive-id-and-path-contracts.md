# Decision: archive ID and path contracts

## Decision

Use stable archive IDs as the only media identity included in approved archive paths. Keep physical disk selection and relative path values separate. Define all prefixes, media segments, derivative segments, bucket size, and logical disk contracts in `config/archive.php`.

## Reasons

- User filenames and collection names must not leak into approved archive identities.
- A deterministic bucket contract avoids huge flat directories at expected archive scale.
- Original files must remain isolated from all rebuildable derivatives.
- Logical disk names can be proven safely without revealing machine-specific roots.
- Validation must fail before any later persistence layer can receive unsafe path input.

## Boundaries

This decision does not authorize uploads, writes, directory creation, copying, replacement, deletion, checksums, duplicate matching, derivatives, manifests, object storage, or public URLs. Those remain later-group work with separate collision and recovery proof.
