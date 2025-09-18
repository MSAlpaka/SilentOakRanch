# Deployment

## Backend Build & Run

The backend service is built with a multi-stage Dockerfile that compiles application dependencies before producing a lightweight runtime image. The final stage runs PHP-FPM and exposes port 8080 so the proxy container can forward web requests to the PHP application process.

- Build and start the containers:
  ```sh
  docker compose up -d --build
  ```
- Run the automated test suite:
  ```sh
  docker compose exec backend vendor/bin/phpunit --testdox
  ```
- Execute database migrations:
  ```sh
  docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction
  ```

### Reverse proxy headers

If the application is deployed behind a load balancer or ingress controller, configure
trusted proxies and hosts via environment variables so Symfony can rely on forwarded
headers for the original request metadata:

```bash
TRUSTED_PROXIES=10.0.0.0/8,192.168.0.0/16
TRUSTED_HOSTS=^app\\.example\\.com$,^api\\.example\\.com$
```

The proxy list accepts comma-separated CIDR ranges or IP addresses, and host patterns use
regular expressions. These values ensure HTTPS detection and host validation work correctly
once the app runs behind the proxy tier.

## Frontend Build & Run

The frontend is containerized with a multi-stage Dockerfile.
Build and start the full stack:

```bash
cp .env.example .env   # if applicable
docker compose up -d --build
```

* The frontend builds automatically inside the container and is served via Nginx.
* All routes fall back to `index.html` for SPA compatibility.
* Security headers and hidden-file protection are enabled in the Nginx config.
* If the proxy + SSL companion is active, traffic will be served over HTTPS with Let’s Encrypt certificates.

### Proxy VHost configuration

The default VHost override under `proxy/vhost.d/vhost.conf.template` wires `/api` requests to the backend container and forwards everything else to the frontend. Generate (or refresh) the concrete file for your domain via

```bash
./scripts/update-vhost.sh
```

The script reads `DOMAIN` from `.env` and writes the override to `proxy/vhost.d/<DOMAIN>`, removing outdated domain files in the process. The deployment helper `scripts/deploy.sh` invokes the same step automatically after extracting a release artifact, so updating `.env` before the next deployment is sufficient to switch domains.

