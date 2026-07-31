# Screenshot Group 16 Manual Test

Screenshot Group 16 proves the v2.1.0 Unified Member Experience using only the
fictional local dataset.

## Prepare the fictional dataset

```powershell
php artisan migrate --force
php artisan db:seed --class=ScreenshotGroup16DemoSeeder
```

Sign in locally as `sg12-contributor@example.test` with the fictional password
`SG12Demo!2026`.

## Required screenshots

1. `01_v210_Member_Home.png`
   - open **Home** at desktop width;
   - show the member summary, recent archive content and four-item contributor
     navigation.
2. `02_v210_Unified_Archive.png`
   - open **Archive**;
   - show Photos and Places & map as in-page exploration tabs.
3. `03_v210_Contributor_Journey.png`
   - open **Contribute**;
   - show the resumable session, automation mode and uploader-controlled
     processing choices.
4. `04_v210_Family_Activity.png`
   - follow **Open family activity** from Home;
   - show the fictional spaces and the route back to Home.
5. `05_v210_Messages.png`
   - open **Messages**;
   - show Requests and Attachments as the two private inbox views.
6. `06_v210_Responsive_Navigation.png`
   - use a 390×844 mobile viewport;
   - open the sidebar and show Home, Archive, Contribute and Messages only.
7. `07_v210_Validation.png`
   - run `Validate-ScreenshotGroup16.ps1` manually and capture its successful
     final output.

Application screenshots may be automated, but the validation script and final
approval remain manual.
