# Ketteler-Shop: Werkseiten AVR, ZMV, Eichstätter umsetzen

Atilla hat die drei Werkseiten freigegeben. Setz sie nach der Startseite um, auf denselben Grundlagen (Theme-Werte, Kopf, Fuß, Erlebniswelt-Blöcke). Erst lokal, dann auf Staging.

## Vorlagen

Die drei Vorlagen liegen bereits unter `docs/design/`:
- `werkseite-avr-entwurf.dc.html`
- `werkseite-zmv-entwurf.dc.html`
- `werkseite-eichstaetter-entwurf.dc.html`

Wie bei der Startseite gilt: Aufbau, Abstände, Farben und Schriften sind verbindlich, die farbigen Bücher sind Platzhalter für echte Cover. **Die Texte stammen wörtlich aus dem bisherigen Shop** (content/) und werden unverändert übernommen. Nichts umformulieren, nichts ergänzen. Fehlt etwas, als Frage notieren.

## Leitplanken

Wie im Startseiten-Prompt: erst lokal, dann Staging, Live nicht anfassen. Keine externen Dienste im Browser. Ketteler pflegt Inhalte selbst. Keine Gedankenstriche in Kundentexten, Sie-Form. Alle bestehenden Tests bleiben grün.

## Entscheidungen von Atilla (bitte in docs/plan.md eintragen)

1. **Mehrere Lizenzen gibt es nur über die Mehrplatz-Anfrage.** Jede Leselizenz braucht in der Bibliothek einen eigenen Zugang, mehrere Plätze laufen über IP-Bereiche und werden von Ketteler als Anfrage bearbeitet. Daraus folgt:
   - Online-Artikel sind nur einzeln kaufbar (bereits so gebaut, prüfen).
   - **Keine Mengenpreise** bei Online-Artikeln. Die Staffel des alten Shops beim Eichstätter (3 Stück 71,90 € bis 10 Stück 44,90 €) wird nicht übernommen. Falls weclapp Staffelpreise überträgt, für Online-Artikel ausblenden bzw. ignorieren und im Bericht nennen.
   - Der Artikel „Die AVR von A bis Z - Zusätzliche digitale Lizenzen“ (05029-M, 99,80 €) wird im Shop nicht verkauft.
   - Der ZMV-Preis „Abonnement Digital ab 2. Lizenz 59,90 €“ wird im Shop nicht angeboten.
2. **AVR:** online nur noch als Abonnement (05029-D, 198,90 €). Die Printausgabe (05029, 125,50 €) bleibt im Shop, solange Lagerbestand besteht. Bei Bestand 0 nicht mehr bestellbar.
3. **ZMV:** Abos Print, Digital, Kombi, dazu Digital zusätzlich zum Print-Abo. Einzelhefte bleiben kaufbar, mit Archiv.
4. **Eichstätter:** nur Online-Zugang (608082, 89,90 €), zurzeit keine Printausgabe.
5. **Gutscheine** gibt es nur noch als Aktionscode im Checkout. Keine eigene Seite „Gutschein-Code einlösen“. Die alte Adresse `/gutschein` leitet per 301 auf die Eichstätter-Werkseite weiter.

## Umsetzung

**Werkseiten als Kategorie-Layout:** Die Kategorien „Zeitschrift ZMV“, „Eichstätter Kommentar“ und „Kompendium AVR Caritas“ bekommen ein gemeinsames Erlebniswelt-Layout „Werkseite“. Je Kategorie werden die Inhalte eigens gepflegt (Shopware 6.6 erlaubt kategoriebezogene Inhalte in denselben Layout-Plätzen). Wo eine Werkseite einen Bereich nicht braucht (z. B. Einzelhefte nur bei ZMV), bleibt er leer und wird nicht angezeigt.

**Wiederverwendet von der Startseite:** Kopf, Fuß, Hinweis mit Zeichen („Ihr gutes Recht“), Cover-Baustein, Produktreihe.

**Neue Blöcke** (Namen auf Deutsch in der Verwaltung):
1. **Werk-Kopf:** Brotkrumen, großes Cover (leicht gedreht, Kreis dahinter in wählbarer Fläche: Rosé, Sand oder Blaugrau), Dachzeile (Herausgeber), Titel (h1), Untertitel kursiv, Text, Angaben-Zeile, Hauptknopf und Nebenlink (Sprungmarken), Zeile für Bestandskunden mit Link in die Bibliothek.
2. **Angebote:** 1 bis 3 Kaufkarten mit Produkt aus dem Katalog (Bestell-Nr., Name, kurze Zeile, Preis, „In den Warenkorb“ ohne Seitenwechsel). Eine Karte kann als hervorgehoben markiert werden (Rahmen und Knopf in Magenta). Optional ein Text neben einer einzelnen Karte (Eichstätter). Darunter **Hinweiszeilen** als Liste: Text links, Link oder Kaufen rechts, feine Linien, enge Abstände wie in der Vorlage. Die Zeile „Mehrere Lizenzen für Ihre Einrichtung: Mehrplatzlizenz auf Anfrage“ führt auf die vorhandene Mehrplatz-Anfrage, vorausgefüllt mit dem Werk. **Mehrplatz ist nie eine eigene Karte.**
3. **Vorteile / Liste:** Überschrift, Einleitung, Einträge als zweispaltige Liste mit Titel und Text, alternativ mit Anfangsbuchstaben in Kursiv (Eichstätter).
4. **Leseproben:** Überschrift, Text, Liste von PDF-Dateien aus der Medienverwaltung mit Kürzel (z. B. „A 11“) und Titel, optional Kasten (z. B. „Themen in der AVR aktuell“). Fläche wählbar.
5. **Video mit Person:** Video wie im Schaufenster (selbst gehostet, Dialog), daneben Dachzeile, Titel, Text, Person mit Foto, Name und Links.
6. **Einzelhefte mit Jahrgangsauswahl** (ZMV): Produkte aus der Kategorie „ZMV Print“, gefiltert nach Jahrgang. Jahrgang über ein Zusatzfeld oder aus der Artikelnummer (`Z5-2026`). Entscheide sauber und begründe. Auswahl als Reiter, die ohne Neuladen wechseln und per Tastatur bedienbar sind. Darunter der Kasten **Archiv** mit Link auf die Archiv-Kategorie und frei pflegbaren Preiszeilen.
7. **Aktuelle Ausgabe** (ZMV): Dachzeile, Titel, Untertitel, Tabelle Rubrik zu Inhalt, Links (Inhaltsverzeichnis, Print-Ausgabe, Online-Ausgabe), daneben Kasten Probeheft mit zwei Knöpfen.
8. **Fakten:** Überschrift und Liste Begriff zu Wert (ZMV „Fakten, Zahlen, Konditionen“).
9. **Rückmeldung:** Überschrift und Text, Knopf mit E-Mail (AVR).

Für Seiten, die die Vorlagen nicht zeigen (Archiv-Kategorie, Einzelheft-Produktseite, Probeheft), nimm die Shopware-Standardseiten im neuen Stil und zeig sie im Bericht.

**Inhalte:** Anlegen per Einrichtungsbefehl wie bei der Startseite, Texte wörtlich aus der Vorlage. PDFs (Leseproben, Muster-Formulare, Lesemuster, Inhaltsverzeichnis) liegen im alten Shop. Lade sie nicht herunter, sondern lege Platzhalter-Medien mit dem richtigen Titel an und liste im Bericht auf, welche Dateien Ketteler bzw. Atilla liefern müssen (mit der alten Adresse aus content/).

**Weiterleitungen:** Alte Adressen per 301 auf die neuen Werkseiten bzw. Bereiche: `/digibib`, `/zmvaktuell`, `/?listview&link=0205000`, `/?listview&link=0207000`, `/bookreader`, `/eichstaetterkommentar`, `/gutschein`, `/importraet`, `/kompendiumaktuell`, `/kompendiumavrcaritas`, `/neuepreise` (Preise ZMV bleibt als eigene Seite, prüfen). Liste in docs/plan.md.

## Mobil, Barrierefreiheit, Tests, Nachweise

Wie im Startseiten-Prompt, zusätzlich:
- Mobil: Angebotskarten untereinander, hervorgehobene Karte zuerst. Hinweiszeilen umbrechen sauber.
- Ende-zu-Ende-Tests: jede Werkseite lädt, Kaufen aus jeder Karte legt das richtige Produkt in den Warenkorb, Mehrplatz-Link öffnet die Anfrage mit vorausgefülltem Werk, Jahrgangsreiter wechseln, AVR-Print bei Bestand 0 nicht bestellbar, 05029-M und Mengenpreise erscheinen nirgends, Weiterleitungen antworten mit 301.
- Bildschirmfotos aller drei Werkseiten lokal und auf Staging bei 1440 und 390 px neben der Vorlage, Abweichungen begründet.

## Bericht

Kein eigener Bericht. Die Ergebnisse gehen in den Bericht am Ende des Gesamtauftrags. Danach weiter mit Abschnitt C.
