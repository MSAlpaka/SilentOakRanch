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

Standardmäßig wird eine SQLite-Datenbank verwendet. Die Verbindungszeichenfolge
ist in `.env` hinterlegt und lautet:

```text
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
```

Die Datei `var/data.db` befindet sich im Projektverzeichnis und wird
automatisch angelegt, falls sie nicht existiert.
