# SSOT-Entscheidungsübersicht "Silent Oak Ranch"

Diese Datei dient als Single Source of Truth (SSOT) für die Stall-App "Silent Oak Ranch" und fasst alle verbindlichen inhaltlichen, technischen und organisatorischen Entscheidungen zusammen.

## Inhaltlich

1. **Buchungslogik** – Kombination aus freien Zeiträumen und festen Paketen (Urlaub 14 Tage, Reko 1 Monat)
2. **Preisgestaltung** – Tagessätze plus rabattierte Pakete
3. **Leistungen** – Basispaket mit optionalen Add-ons
4. **Dokumentation** – Urlaubspension: Basisprotokolle; Reko: gestaffelt (Basis inkl., Standard & Premium gegen Aufpreis)
5. **Rechte & Nutzergruppen** – Stallteam mit Vollzugriff; Kundenportal nur für eigene Pferde

## Technisch

1. **Hosting & Plattform** – Start als Web-App, später optional Native (Capacitor/Ionic)
2. **Bezahlmethoden** – Überweisung plus Stripe (Kreditkarte, Klarna, Apple Pay)
3. **Benutzer-Accounts** – Selbstregistrierung für Urlaub, Einladungssystem für Reko, Social Login optional
4. **Pferdeverwaltung** – Pflichtfelder: Name, Alter, Geschlecht, Rasse, Besonderheiten, Besitzer; Reko optional mit Krankengeschichte, Medikamente, Dokumente
5. **Kommunikation** – E-Mail und Kundenportal, optional WhatsApp/SMS

## Feinentscheidungen

1. **Priorisierung der Module** – 1. Pferdewaage, 2. Urlaubspension, 3. Reko
2. **Rechnungsstellung** – Halb-automatisch: System erstellt Entwurf, Freigabe durch Stallbetreiber
3. **Datenschutz** – Vertrag mit Checkbox (inkl. Foto-/Video-Einwilligung für Reko)
4. **Mehrsprachigkeit** – Start: Deutsch & Englisch
5. **Design/CI** – Neutral modernes Layout mit Ranch-Branding (Logo, Farbschema)

## Detailentscheidungen

1. **Zahlungs-Provider** – Stripe
2. **Hosting** – Gleicher Server wie Waagenmodul (silent-oak-ranch.de)
3. **Nutzerverwaltung intern** – Zentral beim Stallbetreiber, keine Rollen in V1
4. **Reko-Dokumentations-Uploads** – Fotos/Videos per Mail/WhatsApp, nicht im Portal speichern
5. **Rechnungen** – Mit ausgewiesenem Umsatzsteuerbetrag

## Nachträge (zuletzt entschieden)

1. **QR-Codes** – Jetzt für Buchungsbestätigungen & Waage, später Reko-Dokumentation
2. **Pferdewaagen-Modul** – Komfort-Version mit mehreren Buchungs-/Preisoptionen (Einzel, Paket, Premium, dynamisch)
3. **Urlaub/Reko-Pakete** – Kombination aus festen Hauptpaketen & ausgewählten Add-ons
4. **Reko-Dokumentation** – Gestaffelt: Basis inklusive, Standard & Premium gegen Aufpreis
5. **Rechnungen** – Hybrid: Stripe-Zahlung + App-eigene PDF-Rechnung mit Branding & Steuerangaben
6. **Vertragsmanagement** – Vollwertig online: Checkbox, PDF-Download, Uploads, digitale Signatur
7. **Deployment** – Halb-automatisch: CI/CD erstellt Artefakte, manueller Pull vom Server via Skript

## Nutzungshinweise

- Neue Anforderungen, Features oder Module müssen sich strikt an diese Entscheidungen halten.
- Ergänzungen sind nur für operative Inhalte (Texte, Preise, Branding, AGB) vorgesehen.
- Ergebnisse sind klar gegliedert (Code, Architektur, Dokumentation).

