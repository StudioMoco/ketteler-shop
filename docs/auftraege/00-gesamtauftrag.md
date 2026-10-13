# Ketteler-Shop: Gesamtauftrag Seiten und Testbetrieb

Atilla ist für einige Stunden weg. Arbeite diesen Auftrag in einem Durchgang ab, ohne auf Rückfragen zu warten. Wenn Atilla zurückkommt, will er auf Staging einen Shop sehen, dessen Seiten fertig gebaut und als Erlebniswelten in der Verwaltung pflegbar sind. Alle Einstellungen sollen so gesetzt sein, dass er sofort durchtesten kann.

## Wo die Dateien liegen

Atilla hat die Dateien direkt in dein Arbeitsverzeichnis gelegt. Sie sind noch nicht eingecheckt. Prüfe sie mit `git status` und committe sie als ersten Schritt, unverändert.

| Pfad | Inhalt |
|---|---|
| `docs/auftraege/00-gesamtauftrag.md` | dieser Auftrag |
| `docs/auftraege/01-startseite-korrektur.md` | Korrektur Startseite, den Auftrag kennst du schon |
| `docs/auftraege/02-werkseiten.md` | Werkseiten AVR, ZMV, Eichstätter |
| `docs/design/startseite-entwurf.dc.html` | Startseite, gültige Fassung (ist schon im Repo, unverändert) |
| `docs/design/werkseite-avr-entwurf.dc.html` | Werkseite AVR |
| `docs/design/werkseite-zmv-entwurf.dc.html` | Werkseite ZMV |
| `docs/design/werkseite-eichstaetter-entwurf.dc.html` | Werkseite Eichstätter |
| `docs/design/story-mav-einstieg-entwurf.dc.html` | MAV Einstieg, Übersicht |
| `docs/design/story-mav-literatur-entwurf.dc.html` | MAV Einstieg, Unterseite Grundwissen/Basisausstattung |
| `docs/design/story-fachtagung-entwurf.dc.html` | ZMV Fachtagung |
| `docs/design/story-interviews-entwurf.dc.html` | Interviews Autoren & Schriftleiter |
| `docs/design/logo/ketteler-logo-fachverlag.png` | Logo, 480 × 94 (doppelte Auflösung für 240 × 47), transparent. Falls du schon eine Logodatei aus dem Chat eingebaut hast, ist das dasselbe Logo. Nimm die Datei, die schärfer ist. |

Wo die Teilaufträge von „Anhängen“ sprechen, sind diese Pfade gemeint. In den Vorlagen steht das Logo als `/_blob/0809b0bc…`. Gemeint ist `ketteler-logo-fachverlag.png`. Die Vorlagen laden IBM Plex über Google Fonts. Das dient nur der Vorschau, im Shop bleiben die Schriften selbst ausgeliefert.

## Grundsätze für alles

1. **Erlebniswelten statt fester Seiten.** Jede Seite wird als Erlebniswelt (Shopware CMS) aus Blöcken gebaut. Texte, Bilder, Links und Produkte pflegt Ketteler in der Verwaltung. In Twig-Vorlagen stehen keine Inhaltstexte, nur Aufbau und Beschriftungen von Bedienelementen. Werkseiten nutzen ein gemeinsames Kategorie-Layout mit kategoriebezogenen Inhalten. Storytelling-Seiten nutzen eigene Layouts je Seitentyp. Neue Blöcke heißen in der Verwaltung auf Deutsch und haben eine Vorschau im Editor. Die Pflege ist in `docs/design/erlebniswelten.md` beschrieben, mit einem Abschnitt je Block.
2. **Texte wörtlich aus der Vorlage.** Die Vorlagen nehmen ihre Texte aus `content/`. Nichts umformulieren, nichts ergänzen, nichts erfinden. Fehlt ein Text, bleibt die Stelle leer und kommt als Frage in `docs/fragen-ketteler.md`. Für Seiten ohne Vorlage gilt `content/` wörtlich.
3. **`ketteler:einrichten`** legt alle Seiten, Layouts und Testdaten an. Inhalte werden nur angelegt, wenn es sie noch nicht gibt, damit Änderungen in der Verwaltung erhalten bleiben. Für Staging gibt es einen ausdrücklichen Schalter zum Überschreiben (`--neu-anlegen` oder ähnlich). Den verwendest du in diesem Durchgang einmal, damit die neuen Texte ankommen.
4. **Leitplanken wie bisher.**
   - Live nicht anfassen.
   - Die Redaktion bleibt unverändert, die Caddy-Prüfsumme ohne Shop-Datei bleibt gleich.
   - Keine Anfragen an fremde Server im Browser.
   - Keine Gedankenstriche in Kundentexten, Sie-Form.
   - Alle bestehenden Tests bleiben grün.
   - Keine Passwörter oder Schlüssel in Chat, Commits oder Berichten.
5. **Kein Warten.** Wenn etwas hakt, entscheide nach diesen Grundsätzen und schreib die Entscheidung mit Begründung in `docs/entscheidungen-code.md`. Dann arbeite weiter. Anhalten nur bei Schritten, die sich nicht rückgängig machen lassen oder Live betreffen. Die schiebst du ans Ende und listest sie im Bericht.
6. **Nach jedem Abschnitt:** Tests lokal grün, Commit und Push, Auslieferung auf Staging, Tests auf Staging grün. Atilla soll den Fortschritt im Repo sehen können, auch wenn du noch nicht fertig bist.

## Abschnitt A: Startseite korrigieren

Den Korrekturauftrag (`docs/auftraege/01-startseite-korrektur.md`) hast du schon bekommen und zum Teil umgesetzt (Commits 6d5eed8 und 20b953d). Schließe ihn vollständig ab, mit allen Prüfungen und der Auslieferung auf Staging. Den Bericht dazu schreibst du nicht einzeln, er geht in den Bericht am Ende. Hover-Regel, Dropdown und Produktkarte gelten für den ganzen Shop und damit auch für alle folgenden Abschnitte.

## Abschnitt B: Werkseiten

`docs/auftraege/02-werkseiten.md` vollständig umsetzen. Ergänzend:
- Produktreihen auf den Werkseiten nutzen die neue Produktkarte aus Abschnitt A.
- Kategorie-Navigation: „Kompendium AVR Caritas“, „Zeitschrift ZMV“ und „Eichstätter Kommentar“ öffnen ihre Werkseite.
- Die Kategorien „ZMV Print“ und das Archiv sind normale Produktlisten im neuen Stil.

## Abschnitt C: Storytelling-Seiten

Vier Vorlagen liegen unter `docs/design/`. Weitere Seiten baust du aus denselben Blöcken mit den Texten aus `content/`.

| Seite | Vorlage | Inhalt aus |
|---|---|---|
| MAV Einstieg (Übersicht) | `story-mav-einstieg-entwurf.dc.html` | `content/storytelling/mav-einstieg-*.md`, `content/start/startseite.md` |
| Grundwissen/Basisausstattung | `story-mav-literatur-entwurf.dc.html` | `mav-einstieg-grundwissen-basisausstattung.md` |
| Praktische Arbeitshilfen | wie Grundwissen | `mav-einstieg-praktische-arbeitshilfen.md` |
| Themenbezogene Literatur | wie Grundwissen | `mav-einstieg-themenbezogene-literatur.md` |
| Aktuell & interessant | wie Grundwissen bzw. Artikelliste der Übersicht | `mav-einstieg-aktuell-interessant.md` |
| Hilfreiche Links | Linkliste im Stil der Übersicht | `mav-einstieg-hilfreiche-links.md` |
| ZMV Fachtagung | `story-fachtagung-entwurf.dc.html` | `zmv-fachtagung.md` |
| Interviews Autoren & Schriftleiter | `story-interviews-entwurf.dc.html` | `interviews-autoren.md` |
| ZMV aktuell | Blöcke der ZMV-Werkseite (Aktuelle Ausgabe) | `zmv-aktuell.md` |
| Verlag, Links | Textseite im neuen Stil | `content/verlag/verlag.md`, `links.md` |

Umsetzung:
- **MAV Einstieg** ist eine Kategorie mit Unterkategorien für die fünf Unterseiten. Die Unternavigation aus der Vorlage (MAV-Kopf) erscheint auf allen sechs Seiten und markiert die aktive Seite. Die Einträge kommen aus dem Kategoriebaum, nicht aus festem Text.
- **Blöcke:** Schau dir die Vorlagen an und nutze vorhandene Blöcke, wo sie passen. Neu werden voraussichtlich gebraucht:
  - Unternavigation
  - Begrüßungsbrief (lange Textspalte mit Gruß und Unterschrift)
  - Bereichskacheln
  - Artikelliste (Titel, Heft, Link oder PDF)
  - Titelkarten mit großem Anfangsbuchstaben und Produkt
  - Veranstaltung (Titel, Datum, Ort, Programm, Rückblick)
  - Interviewliste (Foto, Name, Funktion, Text, Link)

  Jeder Block ist gestaltbar wie in den Vorlagen und in `erlebniswelten.md` beschrieben.
- **Produkte in Storytelling-Blöcken** (Titelkarten, Literatur) kommen aus dem Katalog. Preis und Warenkorb kommen aus dem Produkt.
- **Fachtagung:** Die Anmeldung läuft vorerst so, wie es in `content/` steht, also als Link oder Kontakt. Ob die Anmeldung über den Shop laufen soll, ist eine offene Frage an Ketteler. Bitte in `docs/fragen-ketteler.md` eintragen.
- **Links auf zmv-online.de** bleiben vorerst so, wie sie in `content/` stehen. Die Einbindung von zmv-online.de wird noch geklärt.
- **Bilder** (Porträts, Veranstaltungsfotos) als Sand-Platzhalter mit richtigem Alt-Text. Fehlende Dateien kommen in die Liste für Ketteler.
- **Mobil** wie bei der Startseite selbst festlegen: Unternavigation als waagerecht scrollbare Reihe, Kacheln untereinander.
- **Weiterleitungen (301)** für die alten Adressen dieser Seiten aus `content/` (Quellenangaben in den Dateien), Liste in `docs/plan.md`.

## Abschnitt D: Shop testbereit einrichten

Alles über `ketteler:einrichten` bzw. die Plugin-Konfiguration, damit es reproduzierbar ist. Nichts nur von Hand in der Verwaltung. Ausnahme sind Werte, die geheim sind. Die stehen in der `.env`.

1. **Stammdaten und Verkaufskanal.**
   - Shopname „KETTELER-Verlag“, Sprache Deutsch, Währung EUR, Domain Staging.
   - Länder wie entschieden: DE, AT, CH, IT, UK, EU.
   - Absenderadresse der Mails und Firmenangaben aus `content/rechtstexte/impressum.md`.
   - Die USt-IdNr. ist ungeklärt (DE 122 792 915 im Shop-Impressum, DE 156 490 098 bei zmv-online). Nimm die aus dem Shop-Impressum und lass die Frage in `docs/fragen-ketteler.md` stehen.
2. **Zahlarten.**
   - Rechnung aktiv und Standard.
   - PayPal im Verkaufskanal deaktivieren. Das Plugin bleibt installiert und konfiguriert. Atilla testet zuerst nur mit Rechnung, PayPal kommt später mit Kettelers echtem Konto.
   - Alle übrigen Shopware-Zahlarten (Vorkasse, Nachnahme, Lastschrift) deaktivieren, sofern sie nicht entschieden sind.
3. **Versand.**
   - Versandarten und Kosten nach Gewicht laut `content/rechtstexte/versand-lieferung.md` und dem Stand in `docs/plan.md`.
   - Jeder Print-Artikel hat ein Gewicht.
   - Reine Online-Bestellungen haben keine Versandkosten und keine Versandart-Auswahl, die Verwirrung stiftet.
   - Lieferzeiten an den Artikeln gesetzt.
4. **Steuern.** Die Einrichtung aus `KettelerSteuern` bleibt, wie sie ist. Prüfe nur, dass jeder Testartikel den richtigen Steuersatz und die richtige Artikelart hat.
5. **Rechtstexte.**
   - AGB, Widerruf, Datenschutz, Impressum, Versand und „Verträge hier kündigen“ sind als Seiten in den Stammdaten zugeordnet.
   - Sie erscheinen in der Kasse, im Fuß und in den Mails.
   - Das Widerrufs-Häkchen bei Online-Artikeln für Privatkunden ist aktiv.
6. **Testartikel vollständig.** Alle mit „(Test)“ im Namen, Preise aus `content/` (`preise-zmv.md`, Werk-Dateien):
   - ZMV: Abo Print, Abo Digital, Abo Kombi, Digital zusätzlich zum Print-Abo, Einzelhefte der Jahrgänge 2024 bis 2026, Archiv-Artikel.
   - AVR: Digital-Abo, Print mit Bestand 3 (damit Atilla „solange der Vorrat reicht“ testen kann) und ein zweiter Print-Testartikel mit Bestand 0.
   - Eichstätter Online.
   - Die Praxishandbücher aus der Startseite.
   - Zwei bis drei Artikel KAB Bücher/Magazine und KAB Service.

   Jeder Artikel ist in der richtigen Kategorie, hat ein Platzhalter-Cover, einen Autor im Zusatzfeld (falls bekannt), ein Gewicht und die Zuordnung zum Online-Werk, falls digital.
7. **Lizenzen.** Eine Bestellung eines Online-Artikels auf Staging erzeugt über den Lizenz-Mock eine Lizenz. Atilla soll das in der Verwaltung am Auftrag sehen können, wie es heute gebaut ist. Wo man es sieht, bitte in die Testanleitung schreiben.
8. **Mails.**
   - Alle Kundenmails im neuen Design (Logo, Farben, Schriften mit Ersatzschrift) und mit Sie-Form: Bestellbestätigung, Registrierung, Passwort, Versand, Storno, Mehrplatz-Anfrage, Kündigung, Widerruf.
   - Alle Mails landen auf Staging in Mailpit.
9. **Formulare.** Mehrplatz-Anfrage, Kündigung, Widerruf und Kontakt schicken auf Staging an Mailpit und zeigen eine Bestätigung im neuen Stil.
10. **Kundenkonto und Anmeldung.**
    - Registrierung und Anmeldung über Test-ZITADEL funktionieren.
    - Lege diese Testkonten an:
      - Privat DE
      - Privat AT (für die Steuer des Kundenlandes)
      - Firma DE mit gültiger USt-IdNr.
      - Einrichtung (Firma) in AT mit USt-IdNr. für das Reverse-Charge-Verfahren
    - Zugangsdaten nur in `/opt/ketteler-shop/testzugaenge.md` auf dem Server (Rechte 600), zusammen mit dem Verwaltungszugang. In der Testanleitung steht nur, wo die Datei liegt und mit welchem Befehl man sie anzeigt.
11. **Weitere Einstellungen.**
    - Suche: findet Titel, Bestellnummer und Autor („AVR“, „05029“, „Küster“).
    - Sprechende Adressen (SEO-URLs) neu erzeugt, ohne Kategorie-IDs in den Adressen.
    - Cookie-Leiste nur mit technisch notwendigen Cookies, solange nichts anderes eingebunden ist.
    - Worker und geplante Aufgaben laufen, Cache und Theme frisch.
12. **weclapp.**
    - Die Anbindung bleibt so, wie sie in Phase 2 eingerichtet wurde.
    - Testbestellungen nur mit dem Testkunden „TEST Studio Moco“ bzw. den Testkonten oben.
    - Du darfst zur Prüfung höchstens fünf Testbestellungen auslösen, alle per Rechnung. Liste die Bestellnummern im Bericht, damit Atilla sie in weclapp findet.
    - Kein Storno und keine Erstattung über PayPal.

## Abschnitt E: Gesamtprüfung und Testanleitung

1. **Alle Tests lokal und auf Staging.** Dazu ein neuer Ende-zu-Ende-Durchlauf:
   - Startseite → Werkseite → Kaufen → Kasse mit Rechnung → Bestellbestätigung in Mailpit
   - jeweils als Gast mit Print, als Privatkunde DE mit Digital, als Privatkunde AT mit Digital (Steuersatz AT), als Firma mit USt-IdNr.
2. **Linkprüfung über alle Seiten:** Jeder Link führt auf eine Seite mit Status 200 oder auf eine gewollte Weiterleitung (301). Ergebnis als Zahl.
3. **Textvergleich und Negativliste** aus Abschnitt A, sinngemäß für jede Seite mit Vorlage (Texte der Vorlage auf der Seite vorhanden).
4. **Hover- und Dropdown-Tests** über alle Seitentypen.
5. **Lighthouse** auf Staging für Startseite, eine Werkseite, MAV Einstieg, eine Produktseite und den Warenkorb, Desktop und mobil.
6. **Bildschirmfotos** aller Seiten mit Vorlage bei 1440 und 390 px neben der Vorlage, dazu Kasse, Warenkorb, Konto und eine Bestellbestätigung aus Mailpit. Ablage in `docs/design/bildschirmfotos/`, mit Übersicht in `docs/design/bildschirmfotos/README.md`.
7. **`docs/testanleitung.md` für Atilla**, kurz und als Checkliste:
   - Adressen von Shop, Verwaltung, Mailpit und Login.
   - Wo die Zugangsdaten liegen.
   - Zehn bis fünfzehn Testfälle mit erwartetem Ergebnis: Gast Print, Digital privat DE, Digital privat AT, Firma mit USt-IdNr., ZMV Kombi-Abo, Einzelheft, AVR Print bei Bestand 0, Mehrplatz-Anfrage, Kündigung, Widerruf-Häkchen, Lizenz am Auftrag, Suche, eine Erlebniswelt in der Verwaltung ändern und Ergebnis prüfen.
   - Was bewusst noch nicht geht (PayPal, echte Bilder, echte Videos, weclapp-Artikelübernahme).

## Bericht am Ende

Kurz und in dieser Reihenfolge:
1. Staging-Version und Commit, was in jedem Abschnitt gebaut ist.
2. Neue Blöcke mit einem Satz zur Pflege.
3. Prüfergebnisse als Zahlen (Tests, Textvergleich, Links, Hover, Lighthouse).
4. Bestellnummern der Testbestellungen.
5. Abweichungen von den Vorlagen mit Grund.
6. Eigene Entscheidungen (Verweis auf `docs/entscheidungen-code.md`).
7. Fehlende Dateien und Bilder für Ketteler, offene Fragen (Nummern in `docs/fragen-ketteler.md`).
8. Was liegen geblieben ist und warum.
9. Zusammenfassung für ClickUp im bisherigen Format.

Danach anhalten.
