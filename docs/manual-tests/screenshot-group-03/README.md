# Screenshot Group 03 Manual Test Plan

Status: Build Groups 29–36 implemented as v0.36.0 — evidence pending.

Use only fictional recipes, jobs, candidates and media records. Do not use real
media, credentials, endpoints or bucket names.

## Application Evidence

1. Open `/dashboard` as the verified fictional Owner and confirm v0.36.0,
   Collaboration & Restoration and Build Groups 29–36.
2. Open `/admin/restoration` and confirm the immutable-original and
   human-review boundaries.
3. Confirm a fictional versioned recipe and queued job appear without an
   original path or hash.
4. Confirm Wasabi reports external configuration required without rendering
   credentials, endpoint, bucket or a live-connection claim.
5. Confirm the workspace remains usable at mobile width.
6. Sign out and confirm `/admin/restoration` requires authentication; confirm a
   verified non-Owner receives HTTP 403.

## Manual Terminal Evidence

Run both scripts yourself after the five application screenshots are approved:

1. `Validate-ScreenshotGroup03-NoOriginalMutation.ps1`
   - capture as `06_No_Original_Mutation_Validation.png`;
2. `Validate-ScreenshotGroup03.ps1`
   - capture as `07_v036_Full_Validation.png`.

The scripts validate the repository but do not capture or approve screenshots.
Screenshot Group 03 remains open until all seven files are present, inspected
and approved.
