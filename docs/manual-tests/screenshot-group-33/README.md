# Screenshot Group 33 Manual Test

Screenshot Group 33 proves the v3.8.0 Migration Qualification boundary. It
exercises the migration control plane with 30,000 deterministic synthetic
entries before the real private family batch is introduced.

## Prepare the fictional dataset

```powershell
php artisan migrate --force
php artisan db:seed --class=ScreenshotGroup33DemoSeeder
```

Use this local-only fictional Owner account:

- email: `sg33-owner@example.test`
- password: `SG33Demo!2026`

Open the focused evidence page:

```text
/admin/migration-qualification
```

The page proves manifest scale, checkpoint progression, interruption and
resume, isolated exception recovery, replay rejection and final accounting.
It does not use real photographs or persist a real source path.

## Operator qualification command

The fictional seeder has already completed a deterministic run for evidence.
To create another qualification run explicitly:

```powershell
php artisan archive:qualify-migration --files=30000 --chunk=500 --interrupt-after=12000 --operator=sg33-owner@example.test
```

The real private family migration remains separate. Do not point this command
at a photo directory; it accepts only synthetic manifest counts.

## Required screenshots

1. `01_v380_Thirty_Thousand_Qualification.png` — qualified 30,000-entry run.
2. `02_v380_Interrupted_Run_Recovery.png` — forced restart, isolated failures,
   recovery and replay protection.
3. `03_v380_Checkpoint_Reconciliation.png` — exact final accounting and durable
   checkpoint coverage.
4. `04_v380_Private_Migration_Boundary.png` — explicit synthetic-versus-private
   claim boundary.
5. `05_v380_Responsive_Qualification.png` — narrow-viewport qualification.
6. `06_v380_Validation.png` — manually run the validation script and capture
   its successful final output.

Application screenshots may be automated. The validation script and final pack
approval remain manual.
