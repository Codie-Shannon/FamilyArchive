# Screenshot Group 12 Manual Test Plan

Status: approved and closed.

Use only the local fictional SG12 dataset. Do not expose invitation tokens,
passwords, storage paths, hashes, credentials or real family information.

## Prepare the Local Evidence State

```powershell
php artisan migrate
php artisan db:seed --class=ScreenshotGroup12DemoSeeder
```

The local-only demonstration accounts use the documented test password
`SG12Demo!2026`. They must never be used outside a disposable local
environment.

## Application Evidence

1. Sign in as `sg12-owner@example.test`, open `/admin/access`, and capture
   `01_v170_Account_Administration.png` with invitation, account-state,
   branch-scope and grant controls visible.
2. Sign in as `sg12-contributor@example.test`, open `/contribute`, and capture
   `02_v170_Contributor_Automation.png`.
3. Open the fictional paused session and capture
   `03_v170_Resumable_Intake.png`.
4. Return to `/admin/access`, position the contributor moderation section, and
   capture `04_v170_Owner_Moderation.png`.
5. Sign in as `sg12-viewer@example.test`, open `/archive`, and capture
   `05_v170_Branch_Filtered_Archive.png`. Confirm Southern and custodian-only
   records are absent.

Confirm public `/register` remains unavailable, an unverified account is sent
to verification, a pending verified account receives HTTP 403, and non-owner
members receive HTTP 403 from `/admin/access`.

## Manual Terminal Evidence

After all five application screenshots have been inspected, run:

```powershell
.\docs\manual-tests\screenshot-group-12\Validate-ScreenshotGroup12.ps1
```

Capture the completed PowerShell window as `06_v170_Validation.png`. The script
validates but does not capture or approve evidence. Explicit human inspection
and approval are still required before the group can close.
