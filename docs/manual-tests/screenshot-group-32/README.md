# Screenshot Group 32 Manual Test

Screenshot Group 32 proves the v3.7.0 Migration Preflight boundary. It adds a
read-only content inventory and capacity report before any family photo enters
quarantine, then proves that processing can resume or run unattended without
repeating successful work.

## Prepare the fictional dataset

```powershell
php artisan migrate --force
php artisan db:seed --class=ScreenshotGroup32DemoSeeder
```

Use this local-only fictional Owner account:

- email: `sg32-owner@example.test`
- password: `SG32Demo!2026`

Open the focused evidence page:

```text
/admin/batch-imports?batch=32000000-0000-4000-8000-000000000001
```

## Operator preflight command

The report destinations must already exist. The source is read-only and its
absolute path is not written into either report.

```powershell
php artisan archive:batch-preflight "D:\Photo Source" --json="D:\Migration Reports\preflight.json" --csv="D:\Migration Reports\preflight.csv"
```

After resolving unreadable-file exceptions, plan and run the retained batch:

```powershell
php artisan archive:batch-import "D:\Photo Source" OWNER_ID --chunk=500 --until-complete
```

If an interrupted retention attempt produced transient failures, use the
printed session identifier to retry only eligible items:

```powershell
php artisan archive:batch-import "D:\Photo Source" OWNER_ID --batch=SESSION_ID --retry-failed=500 --until-complete
```

## Required screenshots

1. `01_v370_Migration_Preflight.png` — 30,000-photo read-only inventory and
   aggregate readiness facts.
2. `02_v370_Content_Integrity_Exceptions.png` — isolated unreadable-image
   outcomes without source paths.
3. `03_v370_Capacity_And_Duplicate_Plan.png` — capacity, duplicate,
   orientation and date findings.
4. `04_v370_Unattended_Recovery_Controls.png` — bounded command and recovery
   guidance.
5. `05_v370_Responsive_Migration_Preflight.png` — narrow-viewport preflight.
6. `06_v370_Validation.png` — manually run the validation script and capture
   its successful final output.

Application screenshots may be automated. The validation script and final pack
approval remain manual.
