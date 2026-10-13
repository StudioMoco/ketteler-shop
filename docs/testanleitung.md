# Testanleitung Ketteler-Shop (Staging)

Stand 13.10.2026. Für Atilla und Ketteler: was sich auf Staging testen lässt, wie, und was noch nicht geht.

## Adressen

| Was | Adresse |
|---|---|
| Shop | https://ketteler-shop.mocodev.de |
| Verwaltung (Shopware) | https://ketteler-shop.mocodev.de/admin |
| Anmeldung (Test-ZITADEL) | https://ketteler-shop-login.mocodev.de |
| Mailpit (alle Mails des Shops landen hier, nichts geht nach außen) | https://ketteler-shop-mail.mocodev.de |
| KETTELER-Bibliothek (Ziel von „Jetzt lesen“, Staging der Redaktion) | https://staging-redaktion.mocodev.de/bibliothek |

Alle Adressen liegen hinter einem Passwortschutz (Browser fragt nach Benutzer und Passwort).

## Zugangsdaten

Alle Zugänge (Passwortschutz, Verwaltung, ZITADEL-Verwaltung, vier Testkonten) stehen nur auf dem Server in
`/opt/ketteler-shop/testzugaenge.md` (nur für den Benutzer `deploy` lesbar). Anzeigen:

```bash
ssh ketteler 'cat /opt/ketteler-shop/testzugaenge.md'
```

Neu schreiben (legt dabei die Testkonten wieder an): `ssh ketteler 'bash /opt/ketteler-shop/repo/ops/staging/testzugaenge.sh'`.

Testkonten (Anmeldung im Shop über „Anmelden“, alle mit demselben Passwort aus der Datei):

| Konto | Wofür |
|---|---|
| privat-de@ketteler.test | Privatkundin Deutschland |
| privat-at@ketteler.test | Privatkunde Österreich |
| firma-de@ketteler.test | Firma Deutschland mit USt-IdNr. (Inland, mit Umsatzsteuer) |
| einrichtung-at@ketteler.test | Einrichtung Österreich mit geprüfter USt-IdNr. (ohne Umsatzsteuer) |

Bezahlt wird nur auf Rechnung. Es fließt kein Geld, weclapp ist nicht angebunden.

## Testfälle

| Nr. | Was tun | Erwartet |
|---|---|---|
| 1 | Startseite öffnen, mit der Maus über „Zeitschrift ZMV“ fahren. | Untermenü klappt auf; nichts wird beim Überfahren unterstrichen, nur die Farbe wechselt. |
| 2 | Startseite > „Die AVR von A bis Z“ (Block Werke) > „Angebote ansehen“. | Werkseite AVR wie die Vorlage; Sprung zu „Kompendium bestellen“ mit zwei Karten (Digital hervorgehoben, Print). |
| 3 | Auf der AVR-Seite bei „Die AVR von A bis Z“ (Print) „In den Warenkorb“, ohne Anmeldung zur Kasse, als Gast bestellen. | Warenkorb öffnet seitlich, Bestellung auf Rechnung mit Versandkosten und 7 % MwSt.; Bestellbestätigung in Mailpit im neuen Design (Logo, Kundenservice, Rechtstexte im Fuß). |
| 4 | Als privat-de anmelden, AVR Digital kaufen (Häkchen zum sofortigen Beginn setzen). | Ohne Versandkosten; Bestätigung mit „Jetzt lesen“; im Konto unter „Meine Online-Zugänge“ steht der Zugang. In der Verwaltung an der Bestellung (Bestellungen > Bestellung öffnen > Reiter „Allgemein“) die Karte „Online-Zugänge (Lizenzen)“ mit „Lizenz angelegt“ und Lizenz-ID (Lizenz über die Attrappe, nicht im echten Redaktionssystem). |
| 5 | Als privat-at anmelden, Eichstätter Kommentar Online kaufen. | Steuer nach dem OSS-Schalter (Erweiterungen > Ketteler Steuern; aus: deutsche 7 %). |
| 6 | Als einrichtung-at anmelden, ZMV Abonnement Print kaufen. | Bestellung ohne Umsatzsteuer; Bestätigung nennt „Steuerschuldnerschaft des Empfängers“ mit der USt-IdNr. |
| 7 | ZMV-Seite > „Einzelhefte“: Reiter 2025 anklicken, dann mit der Tastatur (Pfeil rechts, Pos1) wechseln. | Hefte wechseln ohne Neuladen; Archiv-Kasten verlinkt das Archiv. |
| 8 | Auf einer Werkseite „Lizenzen anfragen“, Formular absenden. | Werk ist vorausgefüllt; Bestätigung auf der Seite; Eingangsbestätigung an die angegebene Adresse in Mailpit; Mail an den Kundenservice. |
| 9 | Fuß > „Verträge hier kündigen“: Abo-Nummer, Name, E-Mail eintragen, „Jetzt kündigen“. | Bestätigungsseite mit Datum und Uhrzeit zum Speichern oder Drucken; Eingangsbestätigung und Mail an den Kundenservice in Mailpit. |
| 10 | Fuß > „Widerruf erklären“ nach einer Bestellung (z. B. aus Fall 4). | Eingangsbestätigung an die Kundin, Mail an die Verwaltung mit Zuordnung zur Bestellung. |
| 11 | Verlag > Kontakt: Formular ausfüllen und senden. | „Vielen Dank für Ihre Nachricht. Wir melden uns bei Ihnen.“; Anfrage in Mailpit. |
| 12 | MAV Einstieg öffnen, durch die Reiter (Grundwissen, Praktische Arbeitshilfen, …) klicken; auf dem Handy wischen. | Unternavigation auf allen sechs Seiten, aktive Seite markiert; mobil eine waagerecht scrollbare Zeile. |
| 13 | Suche: „AVR“, „05029“, „Küster“. | Treffer nach Titel, alter Bestell-Nr. und Autorin. |
| 14 | Alte Adressen aufrufen, z. B. …/gutschein, …/digibib, …/kompendiumavrcaritas. | Weiterleitung (301) auf die neue Seite (Eichstätter, ZMV, AVR). |
| 15 | Neues Kundenkonto über „Anmelden“ > „Registrieren“ anlegen (beliebige Adresse @ketteler.test). | Mail von ZITADEL zum Bestätigen in Mailpit (deutsch, Sie-Form, Farben des Verlags); danach Kundenkonto im Shop mit Willkommensmail. |

## Was noch nicht geht

- **weclapp** ist nicht angebunden (Phase 3 wartet auf den weclapp-Support): Bestellungen bleiben im Shop, keine
  Rechnungen, keine echten Artikel, Preise und Bestände. Alle Artikel sind Testartikel „(Test)“.
- **Lizenzen** gehen an eine Attrappe, nicht an die Lizenzverwaltung des Redaktionssystems. „Jetzt lesen“ führt in die
  Staging-Bibliothek, dort gibt es den gekauften Zugang aber nicht.
- **PayPal** ist eingerichtet (Testmodus), aber im Verkaufskanal ausgeschaltet; es gibt nur Rechnung.
- **Dateien und Bilder:** Leseproben, Interviews, Inhaltsverzeichnisse sind Platzhalter-PDFs, Cover und Fotos Platzhalter
  (Liste: `docs/dateien-ketteler.md`). Das Video ist ein Platzhalter.
- **ZMV Digital:** Was ein Käufer digital bekommt, ist offen (Frage 14); die ZMV-Digital-Artikel vergeben noch keine Lizenz.
- **Muster-Formulare** „Fachliteratur katholisch/evangelisch“ fehlen (Frage 20), die Links führen vorläufig auf „Aktuell
  & interessant“.
- **Beschreibungen für Suchmaschinen** fehlen (Frage 17); Staging ist ohnehin für Suchmaschinen gesperrt.
- Offene Fragen an Ketteler: `docs/fragen-ketteler.md`.

## Automatische Tests

Lokal `make test`, `make test-e2e`, `make test-browser`; gegen Staging `make test-staging`. Der Abnahme-Ablauf
(`python3 tests/e2e/abnahme_test.py`) legt je Lauf vier Bestellungen an und läuft deshalb nicht in `make test-staging`.
