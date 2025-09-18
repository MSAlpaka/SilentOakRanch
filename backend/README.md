# Backend

Symfony-API für Stallverwaltung, Buchungen, Rechnungen und Authentifizierung.

## Installation

PHP-Abhängigkeiten werden mit Composer installiert. Da die Erweiterung
`ext-sodium` nicht in allen Umgebungen verfügbar ist, kann sie beim
Installieren ignoriert werden:

```bash
composer install --ignore-platform-req=ext-sodium
```

## Datenbank

Standardmäßig wird eine PostgreSQL-Datenbank verwendet – exakt so, wie es in
`.env.example` vorkonfiguriert ist:

```text
DATABASE_URL="postgresql://your-db-user:your-db-password@db:5432/your-db-name"
```

Zusätzlich lesen die Container die Werte `POSTGRES_USER`, `POSTGRES_PASSWORD`
und `POSTGRES_DB` aus derselben `.env` Datei. Im Docker-Setup erreicht Symfony
die Datenbank über den Host `db` und Port `5432`. Passe die Variablen bei
lokalen Installationen entsprechend an oder verknüpfe sie mit einem externen
PostgreSQL-Server.

Nach dem Start der Container (`docker compose up`) können Doctrine-Migrationen
ausgeführt werden, zum Beispiel:

```bash
docker compose run --rm backend php bin/console doctrine:migrations:migrate --no-interaction
```
