# Screenshot Group 35 Manual Test

Screenshot Group 35 proves the v4.0.0 Private Source Exclusion boundary with a
fictional 30,000-photo preflight. It demonstrates that configured private
subtrees are pruned before discovery and that a resumed run must present the
same exclusion policy before any retention work begins.

## Prepare the fictional dataset

```powershell
php artisan migrate --force
php artisan db:seed --class=ScreenshotGroup35DemoSeeder
```

Use this local-only Owner account:

- email: `sg35-owner@example.test`
- password: `SG35Demo!2026`

Open the focused operator view:

```text
/admin/batch-imports?batch=35000000-0000-4000-8000-000000000001
```

The page contains synthetic aggregate facts only. It must not reveal a real
source directory, excluded directory name, filename or family-media detail.

## Operator command contract

Supply every excluded subtree during both planning and continuation:

```powershell
php artisan archive:batch-preflight "X:\Archive Source" --exclude="Private Subtree"
php artisan archive:batch-import "X:\Archive Source" --exclude="Private Subtree" --until-complete
```

`--exclude` is repeatable. A missing directory, the source root itself or a
directory outside the source root stops before discovery. Changing or omitting
an exclusion on resume stops before retention.

## Required screenshots

1. `01_v400_Strict_Source_Exclusion.png` — source-boundary summary with no private names or paths.
2. `02_v400_Prune_Before_Discovery.png` — enforcement and excluded-subtree aggregate.
3. `03_v400_Resume_Policy_Continuity.png` — keyed policy continuity for resumed runs.
4. `04_v400_Fail_Closed_Boundary.png` — operator-facing fail-closed explanation.
5. `05_v400_Responsive_Source_Exclusion.png` — narrow-viewport boundary view.
6. `06_v400_Validation.png` — manually run validation and capture its successful output.

Application screenshots may be automated. The validation script and final pack
approval remain manual. Do not use private family media in this evidence pack.
