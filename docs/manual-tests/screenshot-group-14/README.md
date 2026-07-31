# Screenshot Group 14 Manual Test Plan

Status: approved and closed.

Use the private Wasabi account and bucket configured by the maintainer. Never
paste access keys into chat, commit them, show them in a screenshot or place
them in shell history.

## Prepare the Application User

Generate the bucket-scoped policy locally:

```powershell
php artisan archive:wasabi-policy <private-bucket-name>
```

In the Wasabi console, create a policy from that JSON and attach it to a new
API-only sub-user such as `familyarchive-app-prod`. Do not give that user
console access, billing access, user administration, bucket administration,
public ACL permissions or deletion permission for originals and manifests.

Create one access-key pair for that API-only user and store it in a password
manager. Enter the values directly into the local `.env`; do not send them
through chat:

```dotenv
ARCHIVE_PROVIDER=wasabi
WASABI_ENDPOINT=https://s3.ap-southeast-2.wasabisys.com
WASABI_REGION=ap-southeast-2
WASABI_BUCKET=entered-locally
WASABI_ACCESS_KEY_ID=entered-locally
WASABI_SECRET_ACCESS_KEY=entered-locally
WASABI_USE_PATH_STYLE_ENDPOINT=true
```

Then prepare and verify:

```powershell
php artisan migrate
php artisan config:clear
php artisan archive:wasabi-verify
```

The verification must report success without printing a credential, bucket,
endpoint, object key or version ID.

## Application and Operational Evidence

Sign in as a verified Owner and open `/admin/archive-storage`.

1. Capture the provider state, private-only boundary and versioning/Object Lock
   facts as `01_v190_Production_Storage.png`.
2. Position the five isolated prefix cards and their deletion boundaries as
   `02_v190_Private_Prefix_Boundaries.png`.
3. Position the latest safe live verification proof as
   `03_v190_Live_Verification.png`.
4. Position the read-only migration plan, planned object/byte totals, zero
   remote writes and unavailable local deletion as
   `04_v190_Migration_Dry_Run.png`.

Do not use the migration command's `--execute` flag for screenshot evidence. A
production migration remains a separate, deliberate operator action after
backup review.

## Manual Terminal Evidence

After inspecting the four evidence screenshots, run:

```powershell
.\docs\manual-tests\screenshot-group-14\Validate-ScreenshotGroup14.ps1
```

Capture the completed PowerShell window as `05_v190_Validation.png`. The script
validates but does not capture, approve, migrate or delete anything.
