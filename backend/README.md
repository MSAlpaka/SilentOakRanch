# Backend

Symfony-API für Stallverwaltung, Buchungen, Rechnungen und Authentifizierung.

## Datenbank

Standardmäßig wird eine SQLite-Datenbank verwendet. Die Verbindungszeichenfolge
ist in `.env` hinterlegt und lautet:

```text
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
```

Die Datei `var/data.db` befindet sich im Projektverzeichnis und wird
automatisch angelegt, falls sie nicht existiert.
