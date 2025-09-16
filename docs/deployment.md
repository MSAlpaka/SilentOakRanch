# Deployment

## Backend Build & Run

The backend service is built with a multi-stage Dockerfile that compiles application dependencies before producing a lightweight runtime image. The final stage runs PHP-FPM and exposes port 9000 so the proxy container can forward web requests to the PHP application process.

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
