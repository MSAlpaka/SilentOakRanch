# SilentOakRanch

## Project overview & tech stack
- Symfony 7.3 backend running on PHP 8.3 FPM.
- React with Redux Toolkit on Node.js 20, built via Vite, Vitest, ESLint, and the i18n tooling.
- Docker Compose orchestrates the PHP backend, PostgreSQL 15, the static frontend, and the nginx-proxy/Let's Encrypt companions used in production.

## Dependencies
Composer packages are pinned to stable versions for reproducible builds. Notable constraints include `endroid/qr-code-bundle` (^6.0) and `stripe/stripe-php` (^14.0).

## Setup
After cloning, align your local tooling with the containers and CI pipeline (PHP 8.3 and Node.js 20) before installing dependencies:

```bash
cd backend
composer install --ignore-platform-req=ext-sodium
cd ../frontend
npm ci
npm run build
```

If you want Composer to run inside the PHP 8.3 container instead of installing PHP locally, execute:

```bash
docker compose run --rm backend composer install --ignore-platform-req=ext-sodium
```

## Environment configuration

Create a project-wide `.env` file from the template before starting Docker Compose or running Symfony commands:

```bash
cp .env.example .env
```

Populate every mandatory entry from `.env.example`:

- `POSTGRES_USER`, `POSTGRES_PASSWORD`, `POSTGRES_DB`, `DATABASE_URL` – configure the PostgreSQL 15 service that the backend reaches via the internal host `db:5432`.
- `APP_ENV`, `APP_SECRET` – choose the Symfony environment (`dev` for local, `prod` for deployments) and generate a 64-character secret, e.g. `php -r 'echo bin2hex(random_bytes(32));'`.
- `DOMAIN`, `LETSENCRYPT_EMAIL`, `TRUSTED_PROXIES`, `TRUSTED_HOSTS` – define the public hostname and proxy settings consumed by the nginx-proxy and Let's Encrypt companion containers.
- `STRIPE_SECRET_KEY` – supply the live Stripe API key used during checkout.
- `JWT_SECRET_KEY`, `JWT_PUBLIC_KEY`, `JWT_PASSPHRASE` – point to the LexikJWT key pair and provide the matching passphrase (generate the keys with `docker compose run --rm backend php bin/console lexik:jwt:generate-keypair --overwrite`).
- `MESSENGER_TRANSPORT_DSN`, `WHATSAPP_DSN`, `SMS_DSN` – configure the messenger transports for asynchronous processing and notifications.
- `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD` – configure outbound mail delivery.

Optional development helpers such as `VAR_DUMPER_SERVER` can stay commented out or be set as needed. Docker Compose loads `.env` via `env_file` so every container receives the same credentials.

## Backend setup
```bash
cd backend && composer install --ignore-platform-req=ext-sodium
```

## Frontend setup
```bash
cd frontend && npm ci
```

### Frontend notes
- React Router is configured with `future` flags (`v7_startTransition` and `v7_relativeSplatPath`) preparing the project for React Router v7.
- The frontend `package.json` uses `"type": "module"` so tooling like PostCSS runs in ES module mode.

## Dev commands
```bash
composer install
vendor/bin/phpunit
npm run lint
npm test
npm run build
```

## Testing
The backend uses PHPUnit 12. To run the test suite without deprecations or skipped tests:

```bash
cd backend
./bin/phpunit --testdox
```

Tests that depend on external services should be tagged with `@group external` and are excluded from the core suite by default.

## Static Analysis
Static analysis is handled by [PHPStan](https://phpstan.org/). Run it from the backend directory so the bundled configuration is picked up automatically:

```bash
cd backend
vendor/bin/phpstan analyse
```

The default memory limit is `--memory-limit=512M`. Override it locally if needed:

```bash
cd backend
vendor/bin/phpstan analyse --memory-limit=1G
```

- PHPStan-Level 8 prüft den MeController auf korrekte Typen; Fehler durch falsche Signatur wurden korrigiert.

## Doctrine Schema Fix
The Horse ↔ StallUnit relationship now stores a nullable `stall_unit_id` foreign key on the `horse` table. Horses reference stall units through a **ManyToOne** association, and each stall unit provides the inverse side with **OneToMany** or **OneToOne** mapping depending on configuration. Removing a stall unit automatically sets `horse.stall_unit_id` to `NULL` instead of deleting the horse. After pulling these changes, regenerate and apply migrations:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Once migrations are applied, validate the ORM mapping and run the test suite to check for regressions:

```bash
php bin/console doctrine:schema:validate
./bin/phpunit --testdox
```

## Security
All npm vulnerabilities have been resolved.
Keep dependencies current and check for new issues regularly:

```bash
# PHP dependencies
composer update
composer audit

# JavaScript dependencies
npm update
npm audit
npm audit fix
```

## Workaround note for `ext-sodium`
Some environments lack the `ext-sodium` PHP extension. Use
`composer install --ignore-platform-req=ext-sodium` to bypass the requirement during installation.

## Pferdewaage
The Pferdewaage service guides horse owners from reservation to result:

1. **Booking** - reserve a timeslot online.
2. **Confirmation** - receive a confirmation containing a QR code.
3. **Payment** - complete payment to secure the slot.
4. **Weighing** - on site, scan the QR code for check-in and automatic weight capture.

Flow: booking → confirmation → payment → weighing.

## Release & tagging
git remote add origin <REMOTE_URL>
git push -u origin main
git tag v1.0.0
git push origin v1.0.0

## Deployment

GitHub Actions automates the Docker workflows:

- `.github/workflows/ci.yml` builds the PHP 8.3 backend image, runs PHPStan and PHPUnit inside it, and lints/tests the frontend with Node.js 20.
- `.github/workflows/deploy.yml` uses Docker Buildx to build the backend and frontend images, exports them as artifacts, and synchronises them to the target host before running `docker compose up -d`.

To deploy with Docker Compose manually:

1. Prepare `.env` as described in [Environment configuration](#environment-configuration). For production ensure PostgreSQL credentials remain pointed at the internal `db` service, replace the secret/API placeholders (Stripe, SMTP, messenger DSNs, domain settings), and generate the LexikJWT key pair inside the backend container:

   ```bash
   docker compose run --rm backend php bin/console lexik:jwt:generate-keypair --overwrite
   ```

   The keys are written to `/var/www/backend/config/jwt/private.pem` and `/var/www/backend/config/jwt/public.pem`; keep the corresponding paths and `JWT_PASSPHRASE` in sync inside `.env`.

2. Start the stack:

   ```bash
   docker compose up -d --build
   ```

3. Verify that Symfony can connect to PostgreSQL from inside the backend container:

   ```bash
   docker compose run --rm backend php bin/console doctrine:query:sql 'SELECT 1'
   ```

   The command executes a lightweight SQL query via Doctrine and confirms that the configured credentials work end to end.

- All Composer and NPM dependencies are installed automatically during the image build.
- The frontend is served as an Nginx static server.

The included `nginx-proxy` and `acme-companion` automatically request and renew TLS certificates via Let's Encrypt. The proxy routes requests based on the path:

- https://app.silent-oak-ranch.de → Frontend (Port 80)
- https://app.silent-oak-ranch.de/api → Backend (Port 8080)

## JWT key management

The backend expects the LexikJWT keys in `config/jwt`. To keep them outside of the Docker image the directory is bind-mounted from the host (`./shared/jwt/backend` ↔ `/var/www/backend/config/jwt`). The mount point is ignored by Git so generated keys never end up in the repository.

### Local development

- Ensure that `.env` contains a `JWT_PASSPHRASE`. The default value is only meant for local testing – change it before exposing the stack.
- Start the containers with `docker compose up -d --build`. The runtime entrypoint checks for `config/jwt/private.pem` and `config/jwt/public.pem`. If either file is missing, `php bin/console lexik:jwt:generate-keypair --overwrite --no-interaction` is executed automatically and the freshly generated pair is stored on the mounted volume.
- To rotate keys manually run `docker compose run --rm backend php bin/console lexik:jwt:generate-keypair --overwrite --no-interaction` and restart the backend container.

### Production

- Keep the `shared/jwt/backend` mount (or a similar host path) persistent so that redeployments reuse the existing key pair. Adjust ownership/permissions on the host to restrict access to the files.
- Alternatively, load the keys via container secrets. For example:

  ```bash
  docker secret create backend_jwt_private config/jwt/private.pem
  docker secret create backend_jwt_public config/jwt/public.pem
  ```

  Mount the secrets into the container and point `JWT_SECRET_KEY`/`JWT_PUBLIC_KEY` to the secret paths before starting the stack.
- Whenever the passphrase or keys change, restart the backend service so Symfony reloads the credentials.

## Deployment-Skript
CI builds artifacts for each successful workflow run. After verifying the build in CI, deploy the artifact manually:

```bash
scripts/deploy.sh <build-id> <target-dir>
```

Use a dry run to test the commands without making changes:

```bash
bash scripts/deploy.sh --dry-run 123 /tmp/deploy
```

In dry-run mode the script logs each command instead of executing it. It also creates dummy artifact and target directories, making it safe for testing. Real deployments run the commands and apply the downloaded artifacts to the target directory. Full automation with Docker or Kubernetes may be added later.

## Rechnungen

Der Rechnungsprozess umfasst folgende Schritte:

1. **Payment** – Nach erfolgreicher Zahlung wird eine Rechnung erzeugt.
2. **PDF-Erstellung** – Das System erstellt eine gebrandete PDF-Datei mit ausgewiesener Mehrwertsteuer.
3. **E-Mail-Versand** – Die Rechnung wird an die hinterlegte Adresse versendet.
4. **Portal-Anzeige** – Im Kundenportal steht die Rechnung zusätzlich zum Download bereit.

Jede Rechnung muss konsistentes Branding tragen und die gesetzliche Umsatzsteuer klar anzeigen.

## Reko-Dokumentation

Die Reko-Dokumentation unterteilt sich in drei Pakete:

- **BASIS** – einfache Erstellung und Verwaltung von Reko-Einträgen.
- **STANDARD** – alle BASIS-Funktionen plus Kategorisierung und Filter.
- **PREMIUM** – kompletter Funktionsumfang inklusive Export.

### Einträge anlegen

1. Im Bereich *Reko-Dokumentation* „Neuer Eintrag“ wählen.
2. Pflichtfelder wie Datum, Kategorie und Beschreibung ausfüllen.
3. Speichern.

### Export (nur PREMIUM)

Premium-Nutzende können ihre Reko-Einträge als CSV oder Excel exportieren.

## Terminverwaltung

Der Terminprozess begleitet Nutzer*innen von der Anfrage bis zur optionalen Rechnungsstellung:

```
Anfrage -> Bestätigung -> Erinnerungen -> Durchführung -> (Rechnung)
```

Erinnerungen werden zwingend per E-Mail versendet. Optional können zusätzliche Hinweise per WhatsApp oder SMS erfolgen.

## Termin-Erinnerungen

Bestätigungs-, Erinnerungs- und Absage-E-Mails werden mit deutschen und englischen Texten versendet.

## Verträge

Der Vertragsbereich unterstützt die komplette Verwaltung von Vertragsdokumenten:

1. **Anlage** – Neue Verträge werden im System erfasst.
2. **Verwaltung** – Bestehende Verträge lassen sich prüfen, bearbeiten und archivieren.
3. **Signatur** – Optional können Verträge digital signiert werden.
