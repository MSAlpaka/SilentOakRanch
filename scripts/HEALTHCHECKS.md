# Service health checks

This repository defines container health checks in `docker-compose.yml`. The
commands below are executed inside each container by Docker to determine
whether the service is healthy.

| Service     | Command | Purpose |
| ----------- | ------- | ------- |
| `db`        | `pg_isready -U $${POSTGRES_USER:-postgres} -d $${POSTGRES_DB:-postgres}` | Confirms PostgreSQL is accepting connections with the configured credentials. |
| `frontend`  | `status=0; wget -q --spider http://127.0.0.1/ >/dev/null 2>&1 || status=$$?; [ $$status -eq 0 ] || [ $$status -eq 8 ]` | Performs an HTTP GET on `/` and accepts both `200` and `503` style responses as proof that nginx is serving traffic. |
| `proxy`     | `status=0; wget -q --spider http://127.0.0.1/ >/dev/null 2>&1 || status=$$?; [ $$status -eq 0 ] || [ $$status -eq 8 ]` | Checks that the reverse proxy answers on port 80 even when only a fallback site is available. |
| `letsencrypt` | `pidof docker-gen >/dev/null 2>&1 && pidof letsencrypt_service >/dev/null 2>&1` | Verifies that both the `docker-gen` watcher and the ACME companion service are running. |

The `wget` command is provided by the Alpine-based nginx images, and `pidof`
comes from BusyBox. No additional packages need to be installed in the
containers.

## Manual verification

After starting the stack, you can inspect the health status of every container
with Docker Compose:

```bash
docker compose ps --format json
```

The resulting JSON includes a `Health` field for each service, indicating
whether the container passed its health check. For example, piping the output
through `jq` can make the statuses easier to scan:

```bash
docker compose ps --format json | jq '.[].Health'
```
