# Screenshot Group 04 Manual Test Plan

Status: closed — evidence approved.

Use only fictional objects, manifests, repair cases, backup sets and operational
events. Do not use real media, provider accounts, endpoints, storage paths,
credentials or capacity figures.

## Application Evidence

1. Open `/dashboard` as the verified fictional Owner and confirm v0.44.0,
   Integrity & Production and Build Groups 37–44.
2. Open `/admin/operations` and confirm the no-overwrite transfer boundary,
   integrity observations and human repair review.
3. Confirm a fictional mismatch and open repair case appear without an object
   path, hash, provider account or endpoint.
4. Confirm backup and recovery entries are explicitly identified as synthetic,
   isolated rehearsals rather than production proof.
5. Confirm safe operational summaries do not expose private infrastructure.
6. Confirm the operations dashboard remains usable at mobile width.
7. Sign out and confirm `/admin/operations` requires authentication; confirm a
   verified non-Owner receives HTTP 403.

## Manual Terminal Evidence

Run both scripts yourself after the five application screenshots are approved:

1. `Validate-ScreenshotGroup04-NoOverwriteTransfer.ps1`
   - capture as `06_No_Overwrite_Transfer_Test.png`;
2. `Validate-ScreenshotGroup04.ps1`
   - capture as `07_v044_Full_Validation.png`.

The scripts validated the repository but did not capture or approve
screenshots. Screenshot Group 04 closed after all seven files were present,
inspected and approved.
