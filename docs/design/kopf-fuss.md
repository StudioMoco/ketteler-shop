# Kopf und Fuß (Shop, später gemeinsam mit der Bibliothek)

Stand 13.10.2026. Umsetzung im Shop: `custom/plugins/KettelerTheme` (Vorlagen unter
`Resources/views/storefront/layout/`, Stile in `Resources/app/storefront/src/scss/base.scss`, Abschnitte „Servicezeile und
Kopf“, „Fuß“). Vorlage: `docs/design/startseite-entwurf.dc.html`. Die Bibliothek übernimmt diese Struktur später; dort
ist noch nichts geändert.

## Werte an einer Stelle

Erweiterungen > Ketteler Theme > Konfigurieren (`KettelerTheme.config.*`), im Template über `kt_service()`:

| Feld | Vorgabe | Wo |
|---|---|---|
| `telefon`, `telefonLink` | 09972 9414-51, +499972941451 | Servicezeile, Fuß |
| `servicezeiten` | Mo, Di, Do 8 bis 13 Uhr, Mi und Fr 8 bis 12 Uhr | Servicezeile, Fuß |
| `email` | kontakt@ketteler-verlag.de | Fuß |
| `bibliothekUrl` | leer = `KETTELER_BIBLIOTHEK_URL` (wie „Jetzt lesen“) | „Zur Bibliothek“ |
| `kontaktKategorie` | Kategorie „Kontakt“ (Verlag) | „Kontakt“ |
| `adresse` | KETTELER-Verlag GmbH · Schloßhof 1 · 93449 Waldmünchen | Fuß unten links |
| `fussHinweis` | Klimafreundlicher Versand mit GoGreen Plus · Alle Preise inkl. MwSt. | Fuß unten rechts |

## Gestaltung

Farben: Papier #fbf8f3 (Seitengrund), Sand #f3ece2, Linie #e8e0d4, Text #2a2326, gedämpft #4e4447, Hinweis #6b5f63,
Magenta #b7005c. Schrift: IBM Plex Sans (Text), IBM Plex Serif 500 (Überschriften), selbst ausgeliefert. Inhalt 1240 px
breit, 24 px Rand (mobil 16 px). Keine dunklen Flächen, keine Versalien, keine Icon-Reihen.

## Elemente von oben nach unten

1. **Servicezeile** (`.kt-servicezeile`, 13 px, Hinweisfarbe, Linie unten über die ganze Breite, 9 px oben und unten):
   links „Wir sind für Sie da: {Telefon}, {Servicezeiten}“ (Telefon als `tel:`-Link; Zeiten unter 576 px ausgeblendet),
   rechts „Zur Bibliothek“ (Magenta) und „Kontakt“, 22 px Abstand.
2. **Kopfzeile** (26 px oben, 18 px unten): Logo 218 × 57 px; Suche mit 1,5 px Unterstrich statt Kasten, 44 px hoch,
   Platzhalter „Suchen nach Titel, Autor, Stichwort“, Lupe als Knopf; rechts „Anmelden“ (Gäste, Link zur Anmeldung über
   ZITADEL) bzw. „Mein Konto“ (angemeldet, Menü von Shopware) und „Warenkorb“ mit Zähler (22 px Kreis, Magenta, weiße
   Ziffer), 26 px Abstand, 15 px Schrift.
3. **Hauptnavigation** (16 px, 30 px Abstand, Linie unten): Die AVR von A bis Z, Zeitschrift ZMV, Eichstätter Kommentar,
   Praxishandbücher, Neu in der MAV, KAB-Service, Verlag. Gepflegt über die Kategorien (Hauptkategorie
   „KETTELER-Verlag“). Untermenü: weiße Liste mit Linie und leichtem Schatten, erster Eintrag „Alles zu {Kategorie}“ in
   Magenta, dann die Unterkategorien; kein Mega-Menü, keine Bilder.
4. **Fuß** (Sand, 72 px oben, 48 px unten): links „Fragen zu einer Bestellung oder zu Ihrem Abo?“ (Serif 26 px) mit
   Telefon, Zeiten und E-Mail; dann die Spalten aus der Fuß-Kategorie („Fußzeile“): Verlag, Bestellen, Rechtliches
   (Überschrift Sans 600 15 px, Links 9 px Abstand). Darunter Linie #d9cdbd, Adresse links, Hinweis rechts (13 px).

## Zustände

- Links: Text in Textfarbe, beim Darüberfahren Magenta. Textlinks als Aufforderung: 1,5 px Unterstrich in Textfarbe.
- Fokus: überall 2 px Magenta-Rahmen mit 3 px Abstand (auf Magenta weiß).
- Angemeldet: „Mein Konto“ statt „Anmelden“. Warenkorb: Zähler zeigt die Zahl der Positionen (auch 0).
- Mobil (unter 992 px): Hauptnavigation als Menü-Schublade (Shopware-Offcanvas, gleiche Farben); unter 576 px Logo
  links, darunter eine Zeile mit Menü, Suche, Anmelden und Warenkorb.
- Fuß: „Widerruf erklären“ ist der Widerrufsknopf von Shopware (EU-Widerrufsfunktion), „Verträge hier kündigen“ der
  Kündigungsknopf (Beschriftung nach § 312k BGB).
