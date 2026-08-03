# Screenshot Evidence

The approved release evidence consists of `screenshot-group-01` through
`screenshot-group-35`. Each closed directory contains a README, an evidence
index and approved PNG files. Group 35 closes the v4.0.0 Private Source
Exclusion evidence boundary.

The older `group-XX` directories are retained as legacy build-group and
corrective evidence. They are useful for traceability but are not counted as
additional official screenshot groups. Evidence directories are never renamed
or renumbered after closure.

Public evidence must use synthetic data and omit credentials, private family
media, production storage identifiers and absolute local paths. PNGs must be
genuine, readable images with metadata removed. Run this verification before a
release or handoff:

```bash
composer release:verify
```

The verifier also checks the canonical release state and rejects missing packs,
invalid images, duplicate evidence and tracked private artifacts.
