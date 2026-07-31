# Laravel Cloud Deployment

This runbook deploys the complete Laravel application. It is intentionally
separate from static-site publishing because Family Archive requires PHP,
MySQL, queues, sessions and private server-side Wasabi access.

## 1. Create the Application

1. Create a Laravel Cloud application from the Family Archive Git repository.
2. Select the `ap-southeast-2` region.
3. Create a production environment from the
   `feature/screenshot-group-15-production-hosting` branch for the initial
   verification.
4. Attach a managed MySQL database.
5. Add a queue worker for `php artisan queue:work --sleep=1 --tries=3
   --timeout=120`.
6. Use the assigned HTTPS domain first. A custom domain can be added after the
   release proof passes.

## 2. Configure the Environment

Enter secrets only in the provider's encrypted environment settings. Never
paste their values into documentation, screenshots, chat or source control.

Required application settings:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://assigned-domain.example
LOG_CHANNEL=stderr

SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
CACHE_STORE=database
QUEUE_CONNECTION=database

MAIL_MAILER=smtp

ARCHIVE_PROVIDER=wasabi
WASABI_ENDPOINT=https://s3.ap-southeast-2.wasabisys.com
WASABI_REGION=ap-southeast-2
WASABI_USE_PATH_STYLE_ENDPOINT=true
```

Also provide a generated `APP_KEY`, the injected database settings, working
mail settings and the private Wasabi bucket and application-user credentials.
Do not use the Wasabi account root key.

For the fictional portfolio dataset, set:

```dotenv
PORTFOLIO_DEMO_MODE=true
PORTFOLIO_DEMO_OWNER_EMAIL=archive-owner@example.test
```

Set `PORTFOLIO_DEMO_PASSWORD` privately only while running the one-time demo
seed, then remove it after the password hash has been stored.

## 3. Build and Deploy

Build commands:

```text
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan optimize
```

Deploy commands:

```text
php artisan migrate --force
```

After the first successful migration, run the demo seed once if required:

```text
php artisan db:seed --class=PortfolioDemoSeeder --force
```

Do not add that seed to every deployment.

## 4. Verify

Run:

```text
php artisan archive:production-verify
```

The command must report a verified deployment before any live-production
claim is made. Then sign in as the fictional Owner, open **Production
Readiness**, and confirm every gate is ready.

If verification fails, correct the named gate or check. Do not capture
screenshots from a partly configured environment and do not bypass a failing
gate.

## 5. Evidence Safety

Capture only the pages and terminal boundaries listed in the Screenshot Group
15 manual test pack. Crop out browser profiles and unrelated tabs. Do not
capture provider dashboards, environment settings, repository connection
screens, deployment log lines containing infrastructure identifiers or any
real family data.
