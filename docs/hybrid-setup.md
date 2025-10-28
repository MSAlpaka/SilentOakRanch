# Hybrid-Setup

## Infrastruktur
- **Docker-Compose-Stack**: `docker-compose.yml` bündelt Backend (`backend`), PostgreSQL (`db`), React-Frontend (`frontend`), WordPress (`wordpress`), MariaDB (`wp-db`) sowie die Reverse-Proxy-Schicht (`proxy`, `letsencrypt`). Die WordPress- und Symfony-Dienste teilen sich das interne Netzwerk `symfony-wp`, während der Proxy den TLS-Endpunkt auf Ports 80/443 exponiert.
- **Gemeinsame Volumes**: Bind-Mounts für JWT-Keys (`./shared/jwt/backend`), generierte Dokumente (`./shared/backend/var`) und Signatur-Zertifikate (`./shared/agreements/signing`) halten Zustände zwischen Deployments persistent. WordPress Assets liegen im `wp-content`-Volume, Datenbanken in `db-data` bzw. `wp-db-data`.
- **Plugin-Kopplung**: Das WordPress-Plugin „Silent Oak Ranch Booking“ liefert die Administrationsoberfläche (Preise, PayPal, QR-Geheimnis, Backend-Sync) und erstellt HMAC-signierte Requests an das Symfony-Backend.

## ENV & Secrets
- **Zentraler `.env`**: Das Wurzel-Template stellt Postgres-, TLS- und WordPress-Werte bereit (inkl. MariaDB-Creds und WP Salts). Ergänze zusätzlich `WP_BRIDGE_KEY`, `WP_BRIDGE_SECRET` sowie das `WORDPRESS_WEBHOOK_TOKEN`, die das Backend für HMAC validiert.
- **Generierung**: Nutze `openssl rand -hex 32` für Bridge-Keys/Salt-Werte, `php -r 'echo bin2hex(random_bytes(32));'` für `APP_SECRET` und `JWT_PASSPHRASE`. JWT-Schlüsselpaare erzeugst du via `docker compose run --rm backend php bin/console lexik:jwt:generate-keypair --overwrite`.
- **Plugin-Konfiguration**: Im WordPress-Admin unter „Ranch Buchungen → Einstellungen“ die API-Sektion mit `api_base_url` (HTTPS-Endpunkt des Symfony-Backends), `api_key` (entspricht `WP_BRIDGE_KEY`) und `api_secret` (`WP_BRIDGE_SECRET`) füllen. Ohne Werte zeigt das Plugin Warnungen an und deaktiviert HMAC-Sync.

## Deployment auf Hetzner
1. **Vorbereitung**: Secrets auf dem Zielhost aktualisieren (`/srv/stallapp/.env`) und die persistierten Volumes (`shared/`, `proxy/`, `wp-content/`) bereitstellen.
2. **Deploy-Script**: Über GitHub Actions oder manuell `./deploy.sh` ausführen. Der Prozess baut Images, führt `docker compose up -d --build` sowie `docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction` aus.
3. **HMAC-Smoke-Test**: Nach dem Rollout das Skript `scripts/smoke/wp_bridge.sh` starten (`ENV_FILE=/srv/stallapp/backend/.env.prod docker compose run --rm backend bash scripts/smoke/wp_bridge.sh`). Es erzeugt eine Testbuchung und bestätigt Zahlungssignaturen mit `X-SOR-*`-Headern.

## Sicherheitsmaßnahmen (TLS, Non-Root-DB, Secret-Rotation)
- **TLS**: `nginx-proxy` + `acme-companion` terminieren HTTPS automatisch; Zertifikate liegen in `proxy/certs`. Über `docker compose logs proxy letsencrypt` lassen sich Erneuerungen prüfen.
- **Non-Root-Datenbanken**: Setze dedizierte Nutzer (`POSTGRES_USER`, `WORDPRESS_DB_USER`) in `.env` und erlaube dem Backend ausschließlich den Zugriff über die internen Hosts `db` bzw. `wp-db`. Die MariaDB-Instanz läuft isoliert im Compose-Netzwerk.
- **Secret-Rotation & HMAC**: Bridge-Schlüssel regelmäßig erneuern (`openssl rand -hex 32`) und sowohl in `.env` als auch im Plugin aktualisieren. Anschließend `docker compose restart backend wordpress`. Die HMAC-Validierung kontrolliert `backend/src/RanchBooking/EventSubscriber/HmacRequestSubscriber.php`; mit `scripts/smoke/wp_bridge.sh` lässt sich die Signaturprüfung automatisiert gegen den produktiven Endpunkt verifizieren.
