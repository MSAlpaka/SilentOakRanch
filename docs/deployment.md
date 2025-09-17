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

