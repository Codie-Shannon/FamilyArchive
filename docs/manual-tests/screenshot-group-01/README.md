# Screenshot Group 01 Manual Test Plan

Status: Build Groups 13–20 implemented as v0.20.0 — evidence pending.

Use only fictional records. Do not enter real people, family history,
addresses or media.

## Application Evidence

1. Sign in as the verified fictional Owner and open `/dashboard`.
2. Confirm the release card shows v0.20.0, Archive Knowledge and Build Groups
   13–20.
3. Open `/archive/knowledge` and confirm reviewed archive counts are visible.
4. Search for `Wellington` and confirm only reviewed, non-sensitive results
   appear.
5. At a mobile viewport, confirm the hub remains readable and usable.
6. Sign out and request `/archive/knowledge`; confirm authentication is
   required.

Living people, sensitive or private locations, private identities and
unreviewed records must not appear. Original paths, hashes and EXIF GPS must
not appear.

## Manual Validation

After inspecting the five application screenshots, run
`Validate-ScreenshotGroup01.ps1` yourself from PowerShell. Capture the final
terminal result as `06_v020_Validation.png`.

The script validates the repository but does not capture or approve
screenshots. Screenshot Group 01 remains open until all six files are present,
inspected and approved.
