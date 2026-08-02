# Screenshot Group 27 Manual Test

Screenshot Group 27 proves the v3.2.0 Interactive Archive Map boundary with a
shared archive navigation position and a real privacy-safe Google map.

## One-time map configuration

Enable the Google Maps JavaScript API in a billing-enabled Google Cloud
project. Create a browser key restricted to these website origins:

- `http://127.0.0.1:8000/*`
- `https://familyarchive.bayforgesystems.com/*`

Place the key in the local environment as `GOOGLE_MAPS_BROWSER_KEY`, then run:

```powershell
php artisan config:clear
```

The browser key is intentionally public at runtime. Its API and website
restrictions are the security boundary; never commit the key.

## Prepare the fictional dataset

```powershell
php artisan migrate --force
php artisan db:seed --class=ScreenshotGroup27DemoSeeder
```

Use this local-only fictional account:

- member: `sg27-member@example.test`
- password: `SG27Demo!2026`

Open the map at `/discover/map`.

## Required screenshots

1. `01_v320_Interactive_Archive_Map.png` — real map tiles and the complete
   privacy-reduced fictional marker set.
2. `02_v320_Map_Marker_Detail.png` — a selected marker and its linked reviewed
   place card.
3. `03_v320_Reviewed_Places_Navigation.png` — reviewed Places with the shared
   archive navigation in the same position.
4. `04_v320_Event_Detail_Navigation.png` — an Event detail view retaining that
   shared navigation position.
5. `05_v320_Responsive_Archive_Map.png` — the shared archive navigation and
   interactive map composition at a narrow viewport.
6. `06_v320_Validation.png` — manually run the validation script and capture
   its successful final output.

Application screenshots may be automated. The validation script and final
pack approval remain manual.
