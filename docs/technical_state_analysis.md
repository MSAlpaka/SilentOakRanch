# Silent Oak Ranch App – Technical State Assessment

## Architecture Overview
- **Docker Compose** orchestrates PostgreSQL, Symfony backend, React frontend, nginx-proxy, and Let's Encrypt companion services, with health checks defined for core containers.【F:docker-compose.yml†L1-L72】
- Backend image is built from a multi-stage Dockerfile bundling PHP-FPM, nginx, and supervisord; runtime exposes port 8080 with health endpoint `/healthz`.【F:backend/Dockerfile†L1-L76】【F:backend/src/Controller/HealthCheckController.php†L1-L14】
- Frontend Dockerfile uses Vite build in Node 20 and serves via nginx with SPA routing and API proxying.【F:frontend/Dockerfile†L1-L17】【F:frontend/nginx.conf†L1-L27】
- CI workflow performs Composer validation, frontend lint/test/build, backend image build, PHPStan, and PHPUnit via Docker; deploy workflow builds/publishes images and remotely runs `docker compose up -d`.【F:.github/workflows/ci.yml†L1-L44】【F:.github/workflows/deploy.yml†L1-L58】

## Implementation & Code Quality
- Symfony backend dependencies cover Doctrine, Messenger, LexikJWT, Stripe, and templating; PSR-4 autoloading configured with baseline-level PHPStan (level 6).【F:backend/composer.json†L1-L93】【F:backend/phpstan.neon.dist†L1-L16】
- React frontend uses Redux Toolkit, React Router, Tailwind, Vitest, and ESLint 9 with TypeScript configuration enforcing `@typescript-eslint/no-unused-vars`.【F:frontend/package.json†L1-L40】【F:frontend/eslint.config.mjs†L1-L25】
- Environment variables centralised in `.env.example`, including DB credentials, JWT keys, Stripe keys, proxies, signing assets, and notification transports.【F:.env.example†L1-L37】
- Backend Docker build currently executes Doctrine migrations during image build, which is fragile without DB connectivity and mixes build/runtime responsibilities.【F:backend/Dockerfile†L25-L44】

## Security & Compliance
- TLS termination managed via nginx-proxy and Let's Encrypt companion with domain/email derived from `.env`; proxy template forwards `/api` to backend while serving frontend paths.【F:docker-compose.yml†L33-L68】【F:proxy/vhost.d/vhost.conf.template†L1-L12】
- Backend runtime image runs as root and bundles nginx+php-fpm under supervisord, lacking a non-root `USER` step; secrets mounted via bind volumes but no Docker secrets usage.【F:backend/Dockerfile†L46-L76】【F:docker-compose.yml†L22-L47】
- Logging in production streams JSON to stderr without explicit anonymisation; default `.env.example` trusts all proxies (`0.0.0.0/0`), which requires hardening before production.【F:backend/config/packages/monolog.yaml†L27-L42】【F:.env.example†L11-L14】
- Reminder service logs booking IDs only when optional channels disabled, limiting leakage of personal data in logs but no explicit GDPR documentation for data retention.【F:backend/src/Service/ReminderService.php†L33-L64】

## Build, Test, and CI Readiness
- PHPUnit 12 config with `failOnDeprecation`, tests located under `backend/tests`; PHPStan bootstrap and baseline present.【F:backend/phpunit.dist.xml†L1-L35】【F:backend/phpstan.neon.dist†L1-L16】
- Frontend scripts define `lint`, `test`, and `build`, integrated into CI alongside backend static analysis and tests executed inside Docker image for parity.【F:frontend/package.json†L6-L20】【F:.github/workflows/ci.yml†L25-L44】
- Docker Compose health checks cover db, frontend, proxy, and letsencrypt but backend service lacks dedicated healthcheck despite `/healthz` endpoint, and compose build relies on local context without pinned image tags for infrastructure services.【F:docker-compose.yml†L5-L67】【F:backend/src/Controller/HealthCheckController.php†L1-L14】
- Deployment workflow saves built images as artifacts but remote host still pulls `db`, `proxy`, `letsencrypt` without pinning to specific versions, leading to non-reproducible deployments.【F:.github/workflows/deploy.yml†L43-L85】

## Operations & Deployment
- Deployment helper stops/starts a systemd unit named `app`, downloads CI artifacts, refreshes vhost overrides, and runs Doctrine migrations, implying manual provisioning of unit/service and credentials outside repository.【F:scripts/deploy.sh†L1-L115】
- Shared directories for JWT keys, generated documents, and signing assets must exist on host to avoid runtime errors; documentation outlines required mounts and persistence strategy.【F:docker-compose.yml†L22-L47】【F:docs/deployment.md†L1-L47】
- Health checks documented but Compose omits restart policies and backend check; Let’s Encrypt companion depends on `volumes_from`, which is legacy and fragile in Compose v3 setups.【F:docker-compose.yml†L5-L67】【F:scripts/HEALTHCHECKS.md†L1-L31】

## Documentation & Governance
- Root README describes setup, environment variables, testing, deployment steps, and domain-specific flows; backend/frontend READMEs add service-specific instructions.【F:README.md†L1-L200】【F:backend/README.md†L1-L32】【F:frontend/README.md†L1-L26】
- CONTRIBUTING and CHANGELOG exist but CHANGELOG stops at v1.0.0; docs folder provides deployment notes and SSOT decision log.【F:CONTRIBUTING.md†L1-L10】【F:CHANGELOG.md†L1-L6】【F:docs/SSOT.md†L1-L52】

## Key Gaps & Risks
- No automation for generating proxy vhost overrides unless `scripts/update-vhost.sh` is run; repository ships only template without domain-specific file, requiring manual step in deployments.【F:scripts/update-vhost.sh†L1-L39】【F:proxy/vhost.d/vhost.conf.template†L1-L12】
- Docker runtime retains root privileges, lacking OS hardening, resource limits, or read-only filesystem, which weakens container security posture.【F:backend/Dockerfile†L46-L76】
- CI builds backend image but does not run Symfony migrations or integration tests in a service network, risking undetected DB schema issues until deploy.【F:.github/workflows/ci.yml†L33-L44】【F:backend/Dockerfile†L25-L44】
