# Monitoring, Alerting & Backups

This document describes the Silent Oak Ranch observability and disaster recovery stack introduced in Phase 5.

## Architecture overview

```
+----------------------------+        +----------------------------+
| Traefik (TLS, ACME)        |        | OAuth2 Proxy (Grafana SSO) |
| - status.silentoakranch.de +------->+ metrics.silentoakranch.de  |
| - metrics.silentoakranch.de|        +----------------------------+
| - silentoakranch.de / API  |
+-------------+--------------+
              |
              v
   +-----------------------+      +-----------------------------+
   | Uptime Kuma           |      | Prometheus                  |
   | (synthetic monitors)  |      |  - Node exporter            |
   +-----------------------+      |  - Postgres exporter        |
                                  |  - Traefik metrics          |
                                  +-------------+---------------+
                                                |
                                                v
+-----------------------------+      +---------------------------+
| Loki + Promtail             |      | Alertmanager              |
| - Centralised container log |<-----+ Email / Telegram routing |
|   ingestion (PII scrubbed)  |      +---------------------------+
+-----------------------------+
```

Key components:

- **Traefik** terminates TLS, redirects HTTP→HTTPS and exposes the protected monitoring dashboards.
- **Uptime Kuma** handles external health checks for the public API endpoints.
- **Prometheus** aggregates metrics from Traefik, Node Exporter and the Postgres exporter (queue lag, booking activity) and evaluates alert rules.
- **Grafana** provides the operator dashboard (`monitoring/grafana/dashboards/silent-oak-overview.json`).
- **Loki / Promtail** collect container logs with basic redaction for emails and bearer tokens, satisfying the “no personal data in logs” requirement.
- **Alertmanager** distributes alerts via email and optional Telegram channels.
- **Backup Cron** container executes `scripts/backup.sh` every night at 03:00 UTC, storing encrypted archives on the Hetzner Storage Box through rclone.

## Dashboards

Grafana autoprovisions the *Silent Oak Ranch Overview* dashboard containing the following panels:

1. **Backend API Status Codes (5m rate)** – sourced from Traefik metrics to spot spikes in HTTP 5xx responses.
2. **Booking events / minute** – exposes the `sor_booking_events_last_minute` metric computed from the audit log to monitor sales activity.
3. **Contract queue delay** – visualises `sor_queue_oldest_seconds` so operators can react before PDFs stall.
4. **Root filesystem usage** – aggregates Node Exporter filesystem metrics for the docker host volumes (`shared/agreements`, `shared/audit`, `wp-content`).

Dashboards are persisted via Grafana provisioning and survive restarts because the Grafana data directory is stored in the `grafana-data` volume.

## Backups & restore

`scripts/backup.sh` produces compressed SQL dumps for Postgres and MariaDB plus tar archives for:

- `shared/agreements`
- `shared/audit`
- `shared/backend/var`

Each backup run writes a `SHA256SUMS` manifest, uploads to `${STORAGE_BOX_REMOTE}/daily/<timestamp>` and applies the retention policy:

- **Daily**: keep the last 7 days.
- **Weekly**: every Monday copy to `weekly/` and keep 4 weeks.
- **Monthly**: copy the first backup of each month to `monthly/` and retain 12 months.

Restore procedure:

1. Download the desired archive set using `rclone copy ${STORAGE_BOX_REMOTE}/<tier>/<timestamp> ./restore`.
2. Verify checksums with `cd restore && sha256sum -c SHA256SUMS`.
3. Restore Postgres (`gunzip -c postgres.sql.gz | psql`) and MariaDB (`gunzip -c mariadb.sql.gz | mysql`).
4. Extract the tar archives into the corresponding shared directories (`shared/agreements`, `shared/audit`, `shared/backend/var`).
5. Redeploy the stack with `docker compose up -d`.

A nightly GitHub Action (`daily-backup-test`) runs `backup.sh --verify` against a local test remote to ensure the verification path stays operational.

## Alert escalation matrix

| Severity | Trigger                                                                 | Primary channel        | Escalation |
|----------|-------------------------------------------------------------------------|------------------------|------------|
| Critical | `BackendHighErrorRate`, `ContractQueueDelay`                            | Email `admin@silentoakranch.de` | Telegram on-call (optional) |
| Warning  | `VolumeDiskUsageHigh` (>80% usage)                                      | Email `admin@silentoakranch.de` | Ops ticket within 24h |
| Info     | Recoveries (`send_resolved: true`)                                      | Email summary          | None |

Alert messages follow the template `[SilentOak] $service $alertname on $instance ($value)` as configured in Alertmanager.

## Security notes

- HTTP traffic is force-redirected to HTTPS via Traefik entrypoint rules.
- Dashboard access is restricted: Uptime Kuma uses basic-auth credentials stored in `STATUS_DASH_AUTH_USERS` (generate with `htpasswd`), Grafana sits behind OAuth2 Proxy.
- Secrets (API keys, SMTP credentials, OAuth client secrets, Storage Box access) are injected via `.env` and Docker secrets/variables. Rotate them quarterly and on team changes.
- Containers that support non-root execution (Prometheus, Grafana, Loki, Uptime Kuma) run with non-privileged UIDs. The backup container requires elevated permissions to execute database dumps; limit host access by mounting only the necessary directories.
- Promtail scrubs emails and bearer tokens from logs before shipping them to Loki to prevent leakage of personal data.

## Operations checklist

1. Update `.env` with SMTP credentials (`ALERTMANAGER_SMTP_*`), OAuth provider settings, and the Hetzner Storage Box remote name before deployment.
2. Run `docker compose up -d traefik prometheus grafana alertmanager loki promtail uptime-kuma` to bootstrap the monitoring plane.
3. Import the Grafana dashboard (auto-provisioned) and confirm metrics show data.
4. Trigger a test alert by temporarily scaling down the backend to validate email/Telegram delivery.
5. Use `scripts/backup.sh --verify` after the first production backup to ensure Storage Box connectivity.

