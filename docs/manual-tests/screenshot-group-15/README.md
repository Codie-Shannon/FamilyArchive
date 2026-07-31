# Screenshot Group 15 Manual Test

Screenshot Group 15 closes v2.0.0 Hosted Production only after a real HTTPS
deployment passes the application-owned proof.

## Prerequisites

- Screenshot Groups 01-14 are approved and closed.
- The deployment uses the SG15 branch and has completed its migration.
- The production environment satisfies
  `docs/deployment/LARAVEL_CLOUD.md`.
- Fictional portfolio data is loaded.
- No provider dashboard, secret, identifier or real family content is visible.

## Required Screenshots

1. `01_v200_Live_Public_Product.png`
   - open the deployed public home page at desktop width;
   - show the preservation-first product entry point and HTTPS browser state;
   - do not include other tabs or browser profile information.
2. `02_v200_Live_Archive_Experience.png`
   - sign in as the fictional Owner and open the private archive;
   - show synthetic archive content only.
3. `03_v200_Production_Readiness.png`
   - open **Production Readiness** as the fictional Owner;
   - show all configuration and live verification gates ready;
   - confirm no infrastructure identifier is rendered.
4. `04_v200_Validation.png`
   - run `Validate-ScreenshotGroup15.ps1` manually;
   - capture the final successful output in PowerShell.

Use the exact filenames above. Do not commit a screenshot until it has been
visually reviewed and approved.

## Manual Sequence

1. Deploy and run `php artisan archive:production-verify`.
2. Capture screenshots 01-03.
3. Place them in
   `docs/screenshot-groups/screenshot-group-15/`.
4. Run:

   ```powershell
   .\docs\manual-tests\screenshot-group-15\Validate-ScreenshotGroup15.ps1
   ```

5. Capture the validation window as screenshot 04.
6. Review all four images before approval.
