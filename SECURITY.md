# Security Policy

## Supported release

Security maintenance applies to the current `3.8.x` release on the `main`
branch. Older evidence releases are historical and are not maintained as
independent deployment branches.

## Reporting a vulnerability

Do not disclose a suspected vulnerability in a public issue, screenshot pack or
discussion. Use GitHub private vulnerability reporting when it is available for
the repository. Otherwise contact the maintainer privately through the portfolio
contact channel and include only the minimum information needed to reproduce the
problem.

Never include real family media, passwords, API keys, bucket identifiers,
database exports or absolute local paths in a report. Redact personal data and
use synthetic examples.

## Response priorities

- Active credential exposure, authorization bypass or original-media disclosure
  is treated as urgent.
- Integrity, availability and privacy failures are prioritized by reach and
  recoverability.
- Reports are reproduced against synthetic data before remediation evidence is
  made public.

## Deployment responsibilities

Operators must keep production secrets outside source control, use least-
privilege storage credentials, maintain database backups, protect administrator
accounts with multi-factor authentication and verify security headers after
deployment. A passing test suite does not replace operational monitoring,
credential rotation or incident response.
