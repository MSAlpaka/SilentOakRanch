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
                                            └── optional `?signed=1` → SignatureClient (AdobeSign/DocuSign)
```

* Jeder Vertrag besitzt eine UUID (v7) und einen revisionssicheren Audit-Trail.
* Der Download-Link ist mit einem HMAC (`WP_BRIDGE_SECRET`) signiert und 15 Minuten gültig.
* Signierte Versionen werden als `<uuid>-signed.pdf` abgelegt und separat gehasht.

## Speicherort & Struktur

* PDFs werden in `shared/agreements/` persistiert.
* Die `Contract`-Entität hält Pfade, Hashes, Status und Audit-Trail (JSON).
* Signaturen werden nur erstellt, wenn `ENABLE_SIGNATURES=true` konfiguriert ist.

## Relevante ENV-Variablen

| Variable | Beschreibung |
| --- | --- |
| `WP_BRIDGE_SECRET` | HMAC-Schlüssel für Download-Links (wird auch für den WP-Bridge genutzt). |
| `ENABLE_SIGNATURES` | `true/false` – aktiviert den externen Signaturdienst. |
| `SIGNATURE_API_BASE_URL` | Basis-URL zum Signaturanbieter (AdobeSign/DocuSign REST Endpoint). |
| `SIGNATURE_API_TOKEN` | Bearer-Token / API-Key für den Signaturanbieter. |

## DSGVO & Audit

* Es werden keine personenbezogenen Daten in Dateinamen oder Hashes gespeichert.
* Audit-Einträge enthalten Aktion (`generated`/`signed`), Hash, Zeitstempel (ISO 8601) und Vertrags-UUID.
* Downloads erfolgen ausschließlich über kurzlebige, signierte Links.

## Staging-Checkliste

1. `shared/agreements` im Shared Volume anlegen und Schreibrechte prüfen.
2. `ENABLE_SIGNATURES=true` nur in Staging/Prod setzen, wenn Sign-Service erreichbar ist.
3. Bei aktivierter Signatur die ENV `SIGNATURE_API_*` setzen und Firewall für den externen Dienst freischalten.
4. Über `/api/wp/contracts/{uuid}` einen Testabruf starten und Hash mit lokal generiertem SHA-256 vergleichen.
5. Im WordPress-Admin (Menü „Verträge“) sicherstellen, dass Download & Signatur-Workflow funktionieren.
