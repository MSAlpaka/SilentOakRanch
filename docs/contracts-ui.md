# Vertrags-Dashboard (Silent Oak Ranch Admin)

Die vierte Phase integriert eine vollständige Vertragsübersicht in das WordPress-Plugin `sor-booking`. Administrator:innen erhalten damit einen direkten Blick auf Signatur- und Prüfstatus, Audit-Trail sowie Download-Optionen ohne das Backend wechseln zu müssen.

## UI-Überblick

* Neuer Menüeintrag **Silent Oak Ranch → Verträge** im WP-Admin.
* Tabelle mit Buchungs-ID, Pferdenamen, Vertragsstatus, Signatur-Flag und letzter Prüfung.
* Aktionsleiste pro Zeile: Prüfen (AJAX), Download, Vorschau (Inline-PDF), Audit-Modal.
* Status-Badges visualisieren Vertragsstatus (`Entwurf`, `Bereitgestellt`, `Signiert`) sowie Prüfergebnisse (`VALID`, `TAMPERED`, `UNSIGNED`, `EXPIRED`).
* Inline-Viewer (iframe) ermöglicht Vorschau ohne lokalen Download.

> Hinweis: Ein aktualisierter Screenshot sollte nach dem ersten End-to-End-Run ergänzt werden.

## JavaScript-Flow

1. `admin/js/contracts.js` initialisiert Event-Listener auf Tabellen-Buttons.
2. Prüfungen erfolgen via `wp_ajax_sor_booking_contract_verify` → `/api/contracts/{uuid}/verify`; Ergebnis aktualisiert Badge + Metadaten.
3. Audit-Button lädt `/api/audit/contract/{uuid}` und rendert JSON als Tabelle im Modal.
4. Vorschau-Button öffnet das signierte PDF im Inline-Viewer (Token-basierter Download-Link).
5. Erfolgs- und Fehlerzustände werden als Admin-Notices im Live-Bereich (`aria-live`) eingeblendet; ESC-Taste schließt Viewer/Modal.

## API-Mapping

| Aktion | WordPress AJAX | Symfony Endpoint | HMAC/Nonce |
| --- | --- | --- | --- |
| Vertragsliste laden | Initialer Seitenaufruf | `GET /api/wp/contracts` | HMAC Header |
| Einzelvertrag anzeigen | (Legacy) | `GET /api/wp/contracts/{bookingUuid}` | HMAC Header |
| Signatur prüfen | `sor_booking_contract_verify` | `GET /api/contracts/{uuid}/verify` | HMAC + `wp_create_nonce('sor-booking-contracts')` |
| Audit laden | `sor_booking_contract_audit` | `GET /api/audit/contract/{uuid}` | HMAC + `wp_create_nonce('sor-booking-contracts')` |

## Sicherheit

* Alle HTTP-Calls verwenden die HMAC-Header aus Phase 1 (`X-SOR-Key`, `X-SOR-Date`, `X-SOR-Signature`).
* WordPress AJAX-Routen prüfen `manage_options` und das Contracts-Nonce.
* Download-Links bleiben tokenisiert (signierter Query-Parameter + Ablauf 15 Minuten).
* Audit-Daten werden read-only übertragen; Schreiboperationen bleiben Backend-intern.

## Tests & CI

* **PHPUnit** – `backend/tests/Controller/Api/ContractApiTest::testContractEndpointReturnsDownloadLink` deckt Verify-URL & Audit-Summary sowie den neuen Index-Endpunkt ab.
* **Geplanter WP-E2E-Flow** (Playwright/Cypress): Admin-Login → Verträge-Tabelle → Prüfen-Button → Badge `VALID`. Umsetzung folgt im Job `wp-contracts-ui-tests`.
* Neuer CI-Job `wp-contracts-ui-tests` (GitHub Actions) reserviert Kapazität für künftige Browser-Tests.

## Release-Checklist

- [ ] Live-Screenshot des Vertrags-Dashboards anhängen.
- [x] Backend `/api/wp/contracts` liefert `verify_url` & `audit_summary`.
- [x] Admin-JS lokalisiert `ajaxUrl`, Status-Labels, Sicherheits-Strings.
- [x] PHPUnit & bestehende CI-Pipelines erfolgreich durchlaufen.
