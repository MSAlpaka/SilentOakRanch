# Vertrags-Service Silent Oak Ranch

## Ablaufübersicht

```
Booking (Status "paid")
        │
        ▼
Messenger Message `ContractQueued`
        │
        ▼
ContractGenerateHandler ──► ContractGenerator (Twig + Dompdf)
        │                                   │
        │                                   └── speichert PDF unter `shared/agreements/<uuid>.pdf`
        │
        ▼
API `GET /api/wp/contracts/{bookingUuid}` ──► liefert Hash, Audit-Log und Download-Link
                                            │
                                            ├── optional `?signed=1` → SignatureClient (AdobeSign/DocuSign)
                                            └── AuditLogger protokolliert `CONTRACT_GENERATED` / `CONTRACT_SIGNED`
Audit-API `GET /api/contracts/{uuid}/verify` ──► SignatureValidator (lokal + extern)
Audit-API `GET /api/audit/{entityType}/{entityId}` ──► WORM Audit Trail
```

* Jeder Vertrag besitzt eine UUID (v7) und einen revisionssicheren Audit-Trail.
* Der Download-Link ist mit einem HMAC (`WP_BRIDGE_SECRET`) signiert und 15 Minuten gültig.
* Signierte Versionen werden als `<uuid>-signed.pdf` abgelegt und separat gehasht.

## Speicherort & Struktur

* PDFs werden in `shared/agreements/` persistiert.
* Die `Contract`-Entität hält Pfade, Hashes, Status und ein legacy Audit-Trail (JSON).
* Revisionssichere Einträge liegen zusätzlich in `AuditLog` + JSON-Replikat `shared/audit/<datum>-audit.log`.
* Signaturen werden nur erstellt, wenn `ENABLE_SIGNATURES=true` konfiguriert ist.

## Relevante ENV-Variablen

| Variable | Beschreibung |
| --- | --- |
| `WP_BRIDGE_SECRET` | HMAC-Schlüssel für Download-Links (wird auch für den WP-Bridge genutzt). |
| `ENABLE_SIGNATURES` | `true/false` – aktiviert den externen Signaturdienst. |
| `SIGNATURE_API_BASE_URL` | Basis-URL zum Signaturanbieter (AdobeSign/DocuSign REST Endpoint). |
| `SIGNATURE_API_TOKEN` | Bearer-Token / API-Key für den Signaturanbieter. |
| `SIGNATURE_API_URL` | Optionales Prüfdienst-Endpoint für SignatureValidator (PKCS7-Validierung). |
| `SIGNATURE_TTL_DAYS` | Maximale Signaturgültigkeit in Tagen (Default: 365). |

## DSGVO & Audit

* Es werden keine personenbezogenen Daten in Dateinamen oder Hashes gespeichert.
* Audit-Einträge enthalten Aktion (`CONTRACT_*`), Hash, Zeitstempel (ISO 8601), Vertrags-UUID und optionale Metadaten.
* Zugriff auf `/api/contracts/{uuid}/verify` und `/api/audit/*` nur für JWT-User mit `ROLE_ADMIN` oder `ROLE_AUDITOR`.
* Jede Prüfung erzeugt einen Audit-Eintrag `CONTRACT_VERIFIED`.
* Downloads erfolgen ausschließlich über kurzlebige, signierte Links.

## Staging-Checkliste

1. `shared/agreements` im Shared Volume anlegen und Schreibrechte prüfen.
2. `ENABLE_SIGNATURES=true` nur in Staging/Prod setzen, wenn Sign-Service erreichbar ist.
3. Bei aktivierter Signatur die ENV `SIGNATURE_API_*` setzen und Firewall für den externen Dienst freischalten.
4. Über `/api/wp/contracts/{uuid}` einen Testabruf starten und Hash mit lokal generiertem SHA-256 vergleichen.
5. `/api/contracts/{uuid}/verify` mit Admin/Auditor-JWT abrufen und Status `VALID` erwarten.
6. `/api/audit/CONTRACT/{uuid}` prüfen und Speicherung der Ereignisse verifizieren.
7. Im WordPress-Admin (Menü „Verträge“) sicherstellen, dass Download & Signatur-Workflow funktionieren.
