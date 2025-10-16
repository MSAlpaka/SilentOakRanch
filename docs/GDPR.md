# GDPR Operational Controls

## Speicherfristen

- **Anwendungslogs:** 30 Tage Aufbewahrung zur Fehleranalyse und Sicherstellung der Nachvollziehbarkeit von sicherheitsrelevanten Ereignissen.
- **Audit-Nachweise (z. B. Zugriffsprotokolle auf Administrationsfunktionen):** 180 Tage zur Erfüllung rechtlicher Nachweispflichten.
- **Vertrags- und Zahlungsdokumente:** 10 Jahre entsprechend handels- und steuerrechtlicher Vorgaben.

## Zugriffskontrollen

- Rollenbasierte Zugriffskonzepte stellen sicher, dass nur Administrator:innen und Security-Personal Logdateien einsehen dürfen.
- Alle Log-Speicherorte (z. B. `/var/log/app.log`) werden über das Betriebssystem auf die notwendigen Systemkonten beschränkt.
- Administrative Zugriffe werden ausschließlich über Multi-Faktor-Authentifizierung und gesicherte VPN-Tunnel zugelassen.

## Rotationsstrategie

- Logdateien werden täglich rotiert und nach 7 Versionen automatisiert gelöscht (30 Tage Retention siehe oben).
- Rotierte Logdateien werden vor dem Archivieren komprimiert und auf verschlüsselten Speichermedien abgelegt.
- Integritätsprüfungen (SHA-256 Checksums) werden nach jeder Rotation erstellt und bei der Wiederherstellung verifiziert.
