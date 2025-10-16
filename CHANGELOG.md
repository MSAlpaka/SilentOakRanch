# Changelog
## [1.1.0] - 2025-10-01 – Production Hardening and CI Separation
### Summary
- Production hardening documentation covering systemd restart integration and container health monitoring.
- CI separation between build/test workflows documented for the release process.
- Docker base images pinned to `php:8.3.6-fpm-alpine` and `node:20-alpine` for deterministic builds.

## [1.0.0] - 2025-09-14
### Added
- Symfony 7.3 backend with Auth, Booking, Stripe, QR codes, Docs, Mail
- React/Redux Toolkit frontend with Vite, Vitest, ESLint, i18n
### Fixed
- Stabilized CI and tests across backend and frontend
