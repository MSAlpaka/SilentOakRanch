# Silent Oak Ranch Audit & Signatur-Compliance

## WORM-Speicherprinzip

* Alle Audit-Ereignisse werden als `AuditLog`-Entity in PostgreSQL persistiert.
* Jede Speicherung erzeugt zusätzlich eine JSON-Zeile im WORM-Replikat (`shared/audit/YYYY-MM-DD-audit.log`).
* Die Replikate enthalten den SHA-256-Digest pro Zeile und werden ausschließlich per Append geschrieben.
* Docker-Volume `audit-log` ist für den Backend-Container read/write gemountet und kann für Backups read-only eingebunden werden.
* Für Langzeitaufbewahrung wird ein wöchentlicher Export (ZIP + SHA-256-Manifest) empfohlen.

## Digitale Signaturen & Hash-Nachweis

* Vertrags-PDFs werden mit SHA-256 gehasht; Hashwerte liegen sowohl in `Contract` als auch im Audit.
* Der `SignatureValidator` prüft:
  * Integrität des signierten Dokuments (Hash-Vergleich).
  * Ggf. externen PKCS7-Validator via `SIGNATURE_API_URL`.
  * Signaturgültigkeit (TTL, Embedded Signature Marker).
* Prüfergebnis liefert Status `VALID`, `TAMPERED`, `UNSIGNED` oder `EXPIRED`.

## Beweisführung & Audit-Export

* API `GET /api/contracts/{uuid}/verify` liefert Hash, Status, Metadaten und löst ein Audit-Event `CONTRACT_VERIFIED` aus.
* API `GET /api/audit/{entityType}/{entityId}` gibt den chronologischen Audit-Trail zurück.
* Export-Pipeline: JSON-Lines → wöchentliches ZIP mit separater SHA-256-Manifestdatei.
* Integritätsprüfung: Hash-Vergleich der ZIP-Inhalte + Vergleich mit WORM-Log.

## Betriebs- und Sicherheits-Hinweise

* Zugriff nur mit Rollen `ROLE_ADMIN` oder `ROLE_AUDITOR` (JWT-gesicherte Endpunkte).
* WORM-Verzeichnis regelmäßig auf readonly-Mounts spiegeln.
* Signaturdienst muss TLS-geschützt erreichbar sein; Ausfall führt zu lokalem Fallback.
* Hashes und Auditdaten enthalten keine personenbezogenen Daten, nur technische Metadaten.
