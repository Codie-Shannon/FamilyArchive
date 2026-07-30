# Advanced Media Intelligence

Screenshot Group 06 is the Post-v1 A release boundary for v1.1.0 Advanced
Media Intelligence.

## Perceptual Similarity

`PerceptualSimilarity` compares two validated 64-bit hexadecimal fingerprints
with deterministic Hamming distance. A match creates a
`visual_similarity_candidates` record in `pending` state. It does not delete a
file, mark a duplicate or change an archive identity.

Fingerprint records retain their algorithm and generator version so future
implementations can be compared without silently reinterpreting earlier
evidence.

## Alternate Originals

An alternate source remains a separate `MediaFileVersion` linked to its media
item and optional source collection. The record preserves provenance and an
explicit preferred-source flag. Merely recording an alternate never replaces
or overwrites the preferred immutable original.

## Metadata Merge Preview

`MetadataMergePreview` separates:

- safe proposals where the reviewed target field is blank; and
- conflicts where both source and target contain different values.

Conflicts are stored in `metadata_merge_proposals` for human review. No preview
mutates archive metadata or accepted provenance.

## Access and Privacy

The media-intelligence workspace remains inside the verified Owner boundary.
It displays review method, distance, confidence and state without rendering
fingerprints, internal file identifiers, paths, hashes or private source
coordinates.
