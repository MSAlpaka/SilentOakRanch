# Security Policy

## Supported Versions
Silent Oak Ranch maintains a single production track. All security patches are
applied to the `main` branch and propagated to production as soon as QA is
complete. Long-lived support branches are not maintained.

## Patch Management
- **Assessment cadence:** Critical upstream advisories are triaged within 24
  hours. High-severity advisories are reviewed within two business days.
- **Patch window:** Security fixes are merged within 72 hours of validation.
  Lower severity fixes roll into the next scheduled maintenance window (first
  Tuesday of each month).
- **Verification:** Every patch must pass the full CI pipeline, including the
  security scan and load test stages, before release.

## Responsible Disclosure
We welcome coordinated disclosure. Please report vulnerabilities via
security@silentoakranch.de using the subject line `SECURITY DISCLOSURE`. Include
all relevant reproduction steps, impact assessment, and proof-of-concept code.
We acknowledge reports within two business days and share status updates every
five business days until resolution. Encryption is available on request.

## Update Rhythm
- **Infrastructure containers:** Rebuilt weekly with the latest base images and
  package updates.
- **Application dependencies:** Reviewed monthly. Composer and npm updates are
  staged, scanned, and deployed as part of the scheduled maintenance window.
- **Certificates:** Let's Encrypt certificates auto-renew via Traefik; renewal
  status is monitored daily. HSTS is enforced for one year with
  `includeSubDomains` and `preload` flags.

Thank you for helping keep the Silent Oak Ranch platform secure.
