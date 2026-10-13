# Kopf und Fuß (Shop, später gemeinsam mit der Bibliothek)

Stand 13.10.2026. Umsetzung im Shop: `custom/plugins/KettelerTheme` (Vorlagen unter
`Resources/views/storefront/layout/`, Stile in `Resources/app/storefront/src/scss/base.scss`, Abschnitte „Servicezeile und
Kopf“, „Fuß“). Vorlage: `docs/design/startseite-entwurf.dc.html`. Die Bibliothek übernimmt diese Struktur später; dort
ist noch nichts geändert.

## Werte an einer Stelle

Erweiterungen > Ketteler Theme > Konfigurieren (`KettelerTheme.config.*`), im Template über `kt_service()`:

| Feld | Vorgabe (aus der Vorlage) | Wo |
|---|---|---|
| `firma` | KETTELER-Verlag GmbH | Fuß (Kontakt, Copyright) |
| `anschrift` | Schloßhof 1, 93449 Waldmünchen | Fuß (Kontakt) |
| `telefonNummer`, `telefonWahl` | 09972-9414-51, tel:+499972941451 | Servicezeile, Fuß |
| `fax` | 09972-9414-55 | Fuß |
| `email` | kontakt@ketteler-verlag.de | Fuß |
| `zeitenTage1`, `zeitenUhr1`, `zeitenTage2`, `zeitenUhr2` | Montag, Dienstag, Donnerstag / von 8.00 bis 13.00 Uhr / Mittwoch und Freitag / von 8.00 bis 12.00 Uhr | Servicezeile (in einer Zeile), Fuß (vier Zeilen) |
| `fussZeile` | Klimafreundlicher Versand · GoGreen Plus | Fuß unten rechts |
| `bibliothekUrl` | leer = `KETTELER_BIBLIOTHEK_URL` (wie „Jetzt lesen“) | „Zur Bibliothek“ |
| `kontaktKategorie` | Kategorie „Kontakt“ (Verlag) | „Kontakt“ |

Logo: `Resources/public/logo/ketteler-logo-fachverlag.png` (1064 × 208, transparent; Anzeige 240 × 47 im Kopf, mobil
höchstens 180 px breit, im Fuß 168 px), Alt-Text „KETTELER-Verlag“. Vorläufig, bis Ketteler eine SVG liefert. Fehlt die
Datei, zeigt der Shop das bisherige Logo.

## Gestaltung

Farben: Papier #fbf8f3 (Seitengrund), Sand #f3ece2, Linie #e8e0d4, Text #2a2326, gedämpft #4e4447, Hinweis #6b5f63,
Magenta #b7005c. Schrift: IBM Plex Sans (Text), IBM Plex Serif 500 (Überschriften), selbst ausgeliefert. Inhalt 1240 px
breit (ab 1200 px), 24 px Rand (mobil 16 px). Keine dunklen Flächen, keine Versalien, keine Icon-Reihen.
Beim Überfahren nie unterstreichen, Rückmeldung nur über die Farbe (Magenta); dauerhaft unterstrichene Textlinks
(1,5 px Linie, Telefon, Links im Fließtext) bleiben unterstrichen. Knöpfe ändern beim Überfahren nur den Hintergrund,
Karten Rahmen und Schatten (Titel Magenta). Fokus mit der Tastatur: 2 px Magenta-Rahmen.

## Elemente von oben nach unten

1. **Servicezeile** (13 px, Hinweisfarbe, Linie unten über die ganze Breite): links „Servicezeiten: {Tage 1} {Uhr 1},
   {Tage 2} {Uhr 2} · Tel. {Telefon}“ (Telefon unterstrichen und anrufbar; unter 576 px nur „Tel. …“), rechts
   „Zur Bibliothek“ (Magenta) und „Kontakt“.
2. **Kopfzeile**: Logo 240 × 47; Suche mit 1,5 px Unterstrich, Platzhalter „Suchen“; rechts „Anmelden“ bzw.
   „Mein Konto“ (Menü von Shopware), „Merkliste“, „Warenkorb“ mit Zähler (22 px Kreis, Magenta).
3. **Hauptnavigation** (16 px, 30 px Abstand, Linie unten): Zeitschrift ZMV, Eichstätter Kommentar, Kompendium AVR
   Caritas, Praxishandbücher, MAV Einstieg, KAB Service, Verlag (Kategorien unter „KETTELER-Verlag“).
   **Dropdown** bei Punkten mit Unterkategorien (kleiner Pfeil): weiße Fläche, Rahmen 1 px #e8e0d4, Radius 14 px,
   leichter Schatten, Innenabstand 12 px, 240 bis 360 px breit, Einträge 15 px; Überfahren: Hintergrund Sand, Text
   Magenta. Direkt unter dem Menüpunkt, linke Kante bündig, am rechten Rand nach links verschoben. Öffnet beim
   Überfahren (120 ms) und beim Tastaturfokus; Pfeil nach unten öffnet und springt in die Liste, Pfeiltasten wandern,
   Escape schließt (Fokus zurück), Klick daneben schließt; `aria-expanded` am Menüpunkt. Kein Schließen-Knopf, kein
   Titel, kein Bild.
4. **Fuß** (Sand): Spalten Kontakt (Logo, Firma, Anschrift, Tel., Fax, E-Mail), KETTELER-Verlag GmbH, Rechtliches &
   Informationen (beide aus der Fuß-Kategorie „Fußzeile“), Servicezeiten. Darunter Linie #d9cdbd, „© {Jahr} {Firma}“
   links, Hinweis rechts (13 px).

## Zustände

- Angemeldet: „Mein Konto“ statt „Anmelden“. Warenkorb: Zähler zeigt die Zahl der Positionen (auch 0).
- Mobil (unter 992 px): Hauptnavigation als Menü-Schublade (Shopware-Offcanvas); Punkte mit Unterkategorien klappen dort
  auf (erster Unterpunkt „Übersicht“). Unter 576 px Logo links, darunter Menü, Suche, Anmelden, Merkliste, Warenkorb.
- Fuß: „Widerruf erklären“ ist der Widerrufsknopf von Shopware (EU-Widerrufsfunktion), „Verträge hier kündigen“ der
  Kündigungsknopf (§ 312k BGB); beide stehen zusätzlich zur Vorlage in „Rechtliches & Informationen“.

## Produktkarte (alle Produktlisten)

Weiß, Rahmen 1 px #e8e0d4, Radius 14 px, gleich hohe Karten im Raster `repeat(auto-fill, minmax(210px, 1fr))` mit
24 px Abstand (mobil auf der Startseite waagerecht scrollbar mit Einrasten). Oben Sandfläche 250 px mit Cover, darunter
Kennzeichen (Online-Zugang, Abo; nur wenn zutreffend), Autor/Herausgeber (14 px), Titel (17 px, 500), unten bündig
Linie, Preis (Serif 21 px) und Pille „In den Warenkorb“ (mit Warenkorb-Symbol, ohne Seitenwechsel) bzw. „Zum Angebot“
(Abos, Online-Zugänge, Print und Online: zur Werkseite), darunter „inkl. MwSt., zzgl. Versand“ (Versand verlinkt;
versandfreie Online-Zugänge nur „inkl. MwSt.“). Vorlage: `component/product/card/box-standard.html.twig`.
