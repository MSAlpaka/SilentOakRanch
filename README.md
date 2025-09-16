# SilentOakRanch

## Project overview & tech stack
Symfony 7.3, PHP 8.2, React, Redux Toolkit, Vite, Vitest, ESLint, i18n.

## Dependencies
Composer packages are pinned to stable versions for reproducible builds. Notable constraints include `endroid/qr-code-bundle` (^6.0) and `stripe/stripe-php` (^14.0).

## Setup
After cloning, run:

```bash
composer install --ignore-platform-req=ext-sodium
npm ci
npm run build
```

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
Static analysis is handled by [PHPStan](https://phpstan.org/):

```bash
vendor/bin/phpstan analyse --configuration=.phpstan.neon.dist
```

The default memory limit is `--memory-limit=512M`. Override it locally if needed:

```bash
vendor/bin/phpstan analyse --configuration=.phpstan.neon.dist --memory-limit=1G
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

To deploy with Docker Compose:

1. Copy `.env.example` to `.env` and set the domain and email address used for certificates:

   ```
   DOMAIN=app.silent-oak-ranch.de
   LETSENCRYPT_EMAIL=info@silent-oak-ranch.de
   ```

2. Start the stack:

   ```bash
   docker compose up -d --build
   ```

- All Composer and NPM dependencies are installed automatically during the image build.
- The frontend is served as an Nginx static server.

The included `nginx-proxy` and `acme-companion` automatically request and renew TLS certificates via Let's Encrypt. The proxy routes requests based on the path:

- https://app.silent-oak-ranch.de → Frontend (Port 80)
- https://app.silent-oak-ranch.de/api → Backend (Port 8000)

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
