# Screenshot Group 11 Manual Test Plan

Status: implemented — screenshot evidence pending.

Use only the fictional Aotearoa dataset. Do not expose real identities, real
archive paths, hashes, precise private locations, credentials or production
claims.

## Application Evidence

Open `/admin/portfolio-showcase` as the verified fictional Owner and capture:

1. `01_v160_Product_Promise.png` — preservation-first promise, safe metrics
   and product-positioning boundary.
2. `02_v160_Core_Journey.png` — ingest, verify, review, enrich, preserve and
   share workflow with visible human gates.
3. `03_v160_Integrity_Proof.png` — verification state, lineage, no-overwrite
   transfer and synthetic recovery boundaries without raw hashes or paths.
4. `04_v160_Privacy_Proof.png` — roles, publication review and reduced public
   location precision.
5. `05_v160_Architecture.png` — application modules, interfaces, private
   storage zones and consequence gates.
6. `06_v160_Responsive_Accessibility.png` — desktop/mobile layout evidence,
   keyboard order, semantic structure and non-colour status cues.

Confirm the route requires authentication and the Owner role. With portfolio
demo mode enabled in a disposable local environment, confirm safe reads remain
available and authenticated writes return HTTP 403.

## Manual Terminal Evidence

After all six application screenshots have been inspected, run:

```powershell
.\docs\manual-tests\screenshot-group-11\Validate-ScreenshotGroup11.ps1
```

Capture the completed PowerShell window as `07_v160_Validation.png`.

The script validates the repository and all six application captures. It does
not capture or approve evidence. Closure still requires visual, privacy,
dimension and PNG metadata inspection plus explicit human approval.
