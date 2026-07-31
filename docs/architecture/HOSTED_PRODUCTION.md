# Hosted Production Boundary

Screenshot Group 15 turns the verified application and private Wasabi storage
boundary into a deployable production service. It does not treat a successful
build, a configured hostname or a provider dashboard as proof that the archive
is live.

## Runtime Responsibilities

The hosted runtime must provide:

- PHP 8.3 or newer and the required extensions;
- a durable MySQL database;
- durable database-backed sessions, cache and queues;
- an HTTPS application origin;
- a continuously running queue worker;
- transactional email delivery;
- private Wasabi credentials supplied only through encrypted environment
  settings; and
- application and infrastructure logs that do not expose secrets.

The application remains a Laravel monolith. Original and derivative media stay
on the logical private archive disks introduced before hosting; production
selects Wasabi as their provider without changing persisted disk identities.

## Health and Readiness

`GET /up` is deliberately small. It checks a database query and a cache
round-trip and returns the framework health response. It does not reveal host
names, database names, cache keys or provider details.

The Owner-only production-readiness page evaluates configuration gates and
shows the latest safe verification outcome. It is not a substitute for the
live probe.

`php artisan archive:production-verify` is the release proof. It requires all
configuration gates and then verifies:

1. the configured HTTPS origin;
2. the live `/up` response;
3. required browser security headers; and
4. an isolated Wasabi write, exact-version readback and cleanup.

Only boolean and timestamp evidence is retained. The command does not print or
persist hosts, addresses, bucket names, object keys, version identifiers or
credentials.

## Security Boundary

Every response receives content-type, framing, referrer, permissions and
content-security policies. HTTPS production responses also receive HSTS.
Authenticated responses are private and non-cacheable.

Production configuration must disable debug output, encrypt sessions, use
secure cookies and replace local-only database, cache, queue and mail drivers.
The application fails the production proof when any required gate remains
local or synchronous.

## Evidence Boundary

Repository evidence may show the public product page, redacted readiness
status and validation output. It must never include provider account pages,
environment-variable screens, infrastructure addresses, repository access
tokens or private archive identifiers.
