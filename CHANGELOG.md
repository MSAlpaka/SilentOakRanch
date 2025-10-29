# Changelog

## Release 1.0.0 – 2024-07-22

### Security & Operations
- Added `security-scan` GitHub Actions job using Trivy to fail the pipeline on CRITICAL findings.
- Enforced HSTS, secure headers, and Traefik compression across all public routes.
- Introduced Redis-backed Symfony cache with dedicated resource limits and CI-safe fallbacks.

### Performance & Reliability
- Tuned PostgreSQL with `pg_stat_statements` and slow-query logging; capped container CPU/RAM budgets.
- Added k6-based `load-test` job to assert <400 ms average latency on the `/health` endpoint.
- Enabled static caching middleware for frontend & WordPress traffic and promoted page caching via `WP_CACHE`.

### Compliance & Release Management
- Documented compliance evidence in `docs/compliance-report.md` and release freeze in `docs/release-1.0.0.md`.
- Packaged release artifacts for tag builds and published production readiness badge in `README`.
- Introduced `PROD_FREEZE` guard and release notes update for tagged deployments.

---

Historical notes for previous maintenance previews remain available in repository history (`tags/pre-1.0-preview`).
