# SilentOakRanch

Digitale Infrastruktur für einen modernen Pferdebetrieb.  
Inklusive Kundenportal, Buchungssystem, Zusatzleistungen, Rechnungsmodul und Aufgabenverwaltung.

## Struktur
- `backend/`: Symfony (API Platform)
- `frontend/`: React (Vite oder Next.js)
- `shared/`: gemeinsame Typdefinitionen

## Setup

### Composer
Composer requires `ext-sodium`; since the CI environment lacks this extension, run
`composer install --ignore-platform-req=ext-sodium`.

## Release-Checkliste
- Linting und Tests laufen ohne Fehler
- Versionsnummern und Abhängigkeiten aktualisieren
- Changelog und Dokumentation aktualisieren
- Git-Tag und Release erstellen

## Lizenz
Die Software steht unter der [MIT-Lizenz](LICENSE).

> Generiert und verwaltet mit OpenAI Codex.
