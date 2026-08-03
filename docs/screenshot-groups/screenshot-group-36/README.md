# Screenshot Group 36 — Clipping-Safe Photo Separation

This synthetic pack proves the v4.1.0 clipping-safe child-rendering boundary.
Each photo is extracted onto a padded canvas, independently rotated or deskewed
and only then cropped to final geometry. The immutable composite source remains
unchanged, while reviewers retain a per-photo manual orientation override.

Implementation status: approved and closed.

See [Evidence Index](Evidence_Index.md) and the
[manual test](../../manual-tests/screenshot-group-36/README.md).
