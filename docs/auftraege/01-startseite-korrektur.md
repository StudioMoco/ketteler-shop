# Ketteler-Shop: Korrektur Startseite (Texte, Logo, Preis, Hover, Dropdown, Produktkarten)

Danke für den Bericht. Technik, Blöcke, Tests und Lighthouse passen. Ich habe die Startseite auf Staging im Browser geprüft. Gestaltung und Abstände treffen die Vorlage gut. Das Problem: Du hast nach der ersten Fassung von `startseite-entwurf.dc.html` gebaut. Diese Fassung hatte Platzhaltertexte, die nicht aus dem bisherigen Shop stammen. Die gültige Fassung liegt jetzt unter `docs/design/startseite-entwurf.dc.html` und ersetzt die alte.

Grundsatz für diese und alle weiteren Seiten: Texte kommen wörtlich aus der Vorlage, und die Vorlage nimmt sie aus `content/` im Repo. Nichts umformulieren, nichts ergänzen. Wo die Vorlage keinen Text hat, bleibt die Stelle leer oder kommt als Frage in `docs/fragen-ketteler.md`.

## Dateien

- `docs/design/startseite-entwurf.dc.html` (gültige Fassung)
- `docs/design/logo/ketteler-logo-fachverlag.png` (480 × 94, transparent, Anzeige 240 × 47)

## Aufgabe 1: Vorlage und Logo ersetzen

1. Vorlage ist bereits ersetzt, nichts zu tun.
2. Logo im Theme durch `ketteler-logo-fachverlag.png` ersetzen, Anzeige 240 × 47 px im Kopf, im Fuß passend verkleinert, mobil maximal 180 px breit. Die Zeile „Serviceunternehmen der KAB“ fällt weg. Alt-Text: „KETTELER-Verlag“. Logo ist vorläufig, bis Ketteler eine SVG liefert. Das bitte in `docs/fragen-ketteler.md` vermerken.

## Aufgabe 2: Texte übernehmen

Alle Texte aus der neuen Vorlage wörtlich übernehmen. Auf Staging habe ich diese Abweichungen gesehen, sie sind die Mindestliste, nicht die vollständige:

| Stelle | Jetzt auf Staging | Richtig laut Vorlage |
|---|---|---|
| Servicezeile | „Wir sind für Sie da: …“ | „Servicezeiten: Montag, Dienstag, Donnerstag von 8.00 bis 13.00 Uhr, Mittwoch und Freitag von 8.00 bis 12.00 Uhr · Tel. 09972-9414-51“ |
| Navigation | Die AVR von A bis Z, Zeitschrift ZMV, Eichstätter Kommentar, Praxishandbücher, Neu in der MAV, KAB-Service, Verlag | Zeitschrift ZMV, Eichstätter Kommentar, Kompendium AVR Caritas, Praxishandbücher, MAV Einstieg, KAB Service, Verlag |
| Schaufenster | „Die neue AVR 2027 kommt. Was ändert sich …“, „Folge 1 ansehen“, „Alle Folgen“ | Dachzeile „AVR 2027 nachgefragt: Praxiswissen kompakt“, h1 „Folge 1: Wie ist die Struktur der neuen AVR 2027?“, Text „Mit Rechtsanwalt Marvin Milleschewski, Schriftleiter von „Die AVR von A bis Z“.“, Knöpfe „Video ansehen“ und „Zum Kompendium“ |
| Nebenkachel ZMV | „Soeben erschienen … Rechtsprechung, Gastkommentar und Leserfragen“ | „ZMV aktuell“, „Die neueste Ausgabe: ZMV 5/2026“, Themen der Ausgabe, „Zur Ausgabe“ |
| Nebenkachel MAV | „Für die MAV als Sachmittel vorgesehen“ | „MAV Einstieg“, „Grundausstattung/Basiswissen für die MAV“ und Text aus der Vorlage. Die Sachmittel-Aussage gilt nur für ZMV und Eichstätter Kommentar, nicht allgemein und nicht für die AVR. |
| Kategorien | Unterzeilen wie „Reihe zum kirchlichen Arbeitsrecht“ | Titel laut Vorlage: Praxishandbücher, ZMV Print, Eichstätter Schriften, KAB Bücher / Magazine, KAB Service. Keine erfundenen Unterzeilen. |
| Werke | „Drei Werke, die jede MAV kennt“, eigene Texte | „Für die Grundausstattung der Mitarbeitervertretungen“ mit den Porträttexten der Vorlage |
| Werk Eichstätter | „Print mit Online-Zugang · nur Online“ | „Zur Zeit nur als Online-Version verfügbar“ |
| Werk AVR | | Formatzeile laut Vorlage („gibt es zukünftig ausschließlich online“) |
| Hinweis § | alter Text mit „Wir machen Ihnen ein Angebot“ | „ZMV und Eichstätter Kommentar: Muss als erforderliches Sachmittel vom Dienstgeber gestellt werden.“ und die Muster-Formulare katholisch und evangelisch |
| Drei Beiträge | „Aus dem Verlag“ mit eigenen Texten | „Nachrichten und Aktuelles“: Fachtagung 1./2. März 2027 Eichstätt, Interview Simone Küster, Verlagsprogramm 2026 |
| Nummerierte Liste | „Neu gewählt in die MAV?“ mit eigener Einleitung | Begrüßungstext und die fünf Einträge aus der Vorlage |
| Produktreihe | „Für den Schreibtisch“ | „Weitere Empfehlungen“ |
| Fuß | „Fragen zu einer Bestellung oder zu Ihrem Abo?“, Spalten Verlag/Bestellen/Rechtliches | Spalten Kontakt, KETTELER-Verlag GmbH, Rechtliches & Informationen, Servicezeiten |

Seitennamen und Kategorienamen gehören dazu. „Neu in der MAV“ heißt „MAV Einstieg“, „KAB-Service“ heißt „KAB Service“. Kategorie- und Produktlinks bleiben nach deinem Aufbau ohnehin gültig.

Da `ketteler:einrichten` die Startseite nur einmal anlegt: Lege die Startseite auf Staging bitte neu an (bestehende Erlebniswelt ersetzen), damit die Texte aus der neuen Vorlage ankommen. Lokal genauso. Für Live gilt danach weiter „nur anlegen“.

Rechtliche Beschriftungen bleiben so, wie du sie gesetzt hast: „Verträge hier kündigen“ (§ 312k BGB) und die Frage zu „Widerruf erklären“ an Ketteler. Die anrufbare, unterstrichene Telefonnummer in der Servicezeile ist richtig.

## Aufgabe 3: „ab“-Preis ZMV

Bei der ZMV steht „ab 9,80 €“. Richtig ist „ab 129,80 €“ (Abo Print). Der Testartikel „ZMV Heft 5/2026 (Test)“ zieht den Preis nach unten. Einzelhefte dürfen beim Werk nicht in den „ab“-Preis eingehen. Bitte so lösen, dass der Werke-Block den Preis aus dem Produkt nimmt, das im Block gewählt ist, und nicht das günstigste Produkt der Kategorie. Prüfe bitte auch, ob der Testartikel für das Einzelheft mit 9,80 € dem Preis aus `content/preise-zmv.md` entspricht. Laut Vorlage beginnen Einzelhefte ab 24,50 €. Falls der Testpreis falsch ist, korrigieren.

Soll-Werte im Werke-Block: ZMV ab 129,80 €, Eichstätter Kommentar 89,90 €, Die AVR von A bis Z 198,90 €.

## Aufgabe 4: Kategorien in der Reihe

Die Vorlage hat vier Karten plus eine breite fünfte. Fünf gleich breite Karten in einer Reihe sind in Ordnung, wenn alle Titel ohne Umbruch und ohne Abschneiden passen. Bei 1440 px und 1280 px prüfen.

## Aufgabe 5: Unterstreichen beim Überfahren abschalten

Auf Staging wird beim Überfahren mit der Maus fast alles unterstrichen: Navigation, Karten, Knöpfe, Kategorien, Produkttitel, Fuß. Das ist das Standardverhalten aus Bootstrap/Shopware und soll weg.

- Grundregel im Theme: `a:hover { text-decoration: none; }`. Rückmeldung beim Überfahren nur über die Farbe (Magenta #b7005c), wie in der Vorlage (`a:hover{color:#b7005c}`).
- Ausnahme: Textlinks, die in der Vorlage schon dauerhaft unterstrichen sind (1,5 px Linie, zum Beispiel „Praxishandbücher im Überblick“ und die Telefonnummer in der Servicezeile). Die bleiben unterstrichen, beim Überfahren ändert sich nur die Farbe.
- Knöpfe (Pillen) ändern beim Überfahren nur die Hintergrundfarbe leicht, nie mit Unterstrich.
- Karten (Kategorien, Werke, Beiträge, Produkte): kein Unterstrich in der Karte. Rückmeldung über Rahmenfarbe oder einen leichten Schatten an der ganzen Karte, Titel wird Magenta.
- Fokus mit der Tastatur bleibt sichtbar (Fokusrahmen), das ist für die Barrierefreiheit nötig und wird nicht abgeschaltet.
- Gilt für den ganzen Shop, auch Kasse, Konto, Warenkorb und Suche.

## Aufgabe 6: Menü-Dropdown

Auf Staging hat das Dropdown in der Hauptnavigation ein „x“ zum Schließen und erscheint immer links am Seitenrand statt unter dem Menüpunkt. Das ist das Shopware-Flyout in voller Breite.

- Flyout von Shopware durch ein schlichtes Dropdown ersetzen, oder das Flyout entsprechend umbauen.
- Position: direkt unter dem Menüpunkt, linke Kante bündig mit dem Menüpunkt. Kurz vor dem rechten Rand nach links verschieben, damit nichts aus dem Bild läuft.
- Kein „x“, kein „Alle anzeigen“-Kopf mit großem Titel, kein Bild im Dropdown.
- Aussehen: weiße Fläche, Rahmen 1 px #e8e0d4, Radius 14 px, leichter Schatten wie bei den Karten, Innenabstand 12 px, Breite nach Inhalt (mindestens 240 px, höchstens 360 px). Einträge 15 px, Zeilenhöhe großzügig, beim Überfahren Hintergrund Sand #f3ece2 und Text Magenta, kein Unterstrich.
- Verhalten: öffnet beim Überfahren mit kurzer Verzögerung (etwa 120 ms) und beim Fokus mit der Tastatur. Schließt beim Verlassen, mit Escape (Fokus zurück auf den Menüpunkt) und beim Klick daneben. Pfeil nach unten öffnet, Pfeiltasten wandern durch die Einträge. `aria-expanded` am Menüpunkt richtig gesetzt.
- Menüpunkte ohne Unterkategorien haben kein Dropdown und keinen Pfeil.
- Mobil bleibt die Schublade, dort aufklappbare Unterpunkte statt Dropdown.

## Aufgabe 7: Produktkarten in „Weitere Empfehlungen“

Die Einträge sehen auf Staging leer aus und sind nicht als Produkte erkennbar. Die neue Vorlage hat dafür richtige Produktkarten. Diese Karte gilt künftig für alle Produktlisten im Shop (Kategorieseiten, Suche, Querverweise), nicht nur auf der Startseite.

Aufbau laut Vorlage:
- Karte: weiß, Rahmen 1 px #e8e0d4, Radius 14 px, alle Karten einer Reihe gleich hoch.
- Oben eine Bildfläche in Sand #f3ece2, 250 px hoch, Cover mittig mit Schatten. Ohne Produktbild der Sand-Platzhalter wie bisher.
- Darunter Autor/Herausgeber (14 px, #6b5f63), Produkttitel (17 px, 500), dann unten bündig eine Trennlinie, links der Preis in IBM Plex Serif 21 px, rechts der Knopf „In den Warenkorb“ als magenta Pille mit Warenkorb-Symbol.
- Unter dem Preis „inkl. MwSt., zzgl. Versand“ mit Link auf die Versandseite, so wie Shopware es für die Preisangabe ohnehin braucht.
- Abos und Digitalprodukte bekommen statt „In den Warenkorb“ den Knopf „Zum Angebot“, der auf die Werkseite führt, weil dort die Variante gewählt wird.
- Raster: `repeat(auto-fill, minmax(210px, 1fr))`, Abstand 24 px. Bei 1440 px fünf Karten in einer Reihe, mobil als waagerecht scrollbare Reihe mit Einrasten.
- Überfahren: Rahmen dunkler, leichter Schatten, Titel Magenta, kein Unterstrich.
- Die ganze Karte ist über Bild und Titel klickbar. Der Warenkorb-Knopf bleibt ein eigener Knopf mit `aria-label`, der den Produktnamen enthält.

## Prüfungen

1. Textvergleich automatisch: Ein Test liest alle sichtbaren Texte aus der Vorlage (Überschriften, Absätze, Knöpfe, Links, Navigation, Fuß) und prüft, dass jeder davon auf der Startseite steht. Ausnahmen nur für Preise (kommen aus dem Produkt) und für „(Test)“ in Produktnamen. Ergebnis als Zahl „x von y Texten gefunden“, Ziel y von y. Der Test gehört dauerhaft zu den Browsertests.
2. Negativliste: Diese Wörter dürfen nirgends auf der Startseite stehen: „Serviceunternehmen der KAB“, „Wir sind für Sie da“, „Drei Werke, die jede MAV kennt“, „Aus dem Verlag“, „Neu gewählt in die MAV“, „Für den Schreibtisch“, „als Sachmittel vorgesehen“, „Print mit Online-Zugang“, „Wir machen Ihnen ein Angebot“, „Reihe zum kirchlichen Arbeitsrecht“, „Neu in der MAV“, „ab 9,80“.
3. „ab“-Preise im Werke-Block wie oben, als Test.
4. Logo: Datei ausgeliefert, natürliche Größe 480 × 94, Anzeige 240 × 47 am Desktop, Alt-Text gesetzt, keine Anfrage an fremde Server.
5. Unterstreichen: Ein Browsertest überfährt jeden Link und Knopf auf Startseite, einer Kategorieseite, einer Produktseite und im Warenkorb und prüft `text-decoration-line`. Erlaubt ist ein Unterstrich nur bei den dauerhaft unterstrichenen Textlinks. Ergebnis als Zahl.
6. Dropdown: Test für Position (linke Kante des Dropdowns liegt innerhalb von 2 px an der linken Kante des Menüpunkts, rechter Rand bleibt im Bild bei 1280 und 1440 px), kein Schließen-Knopf im DOM, Öffnen mit Maus und Tastatur, Escape mit Fokus-Rückkehr, `aria-expanded`.
7. Produktkarten: gleiche Höhe in einer Reihe, Preis und Knopf sichtbar, „In den Warenkorb“ legt ohne Seitenwechsel in den Warenkorb, Abo zeigt „Zum Angebot“. Bildschirmfoto der Reihe bei 1440 und 390 px.
8. Alle bisherigen Tests weiter grün, Startseite 26 von 26 plus die neuen Prüfungen, Verwaltung 4 von 4.
9. Lighthouse Staging Desktop und mobil, Werte nennen.
10. Redaktion unverändert, Caddy-Prüfsumme ohne Shop-Datei wie bisher. Live nicht anfassen.

## Bericht

- Staging-Version und Commit
- Ergebnis Textvergleich (x von y) und Negativliste
- Bildschirmfotos neu: `vergleich-staging-1440.png` und `vergleich-staging-390.png` in `docs/design/bildschirmfotos/`, dazu `dropdown-1440.png` (offenes Dropdown unter „Praxishandbücher“) und `produktkarten-1440.png` / `produktkarten-390.png`
- Liste aller Stellen, an denen du von der Vorlage abweichst, mit Grund
- Zusammenfassung für ClickUp im bisherigen Format

## Antworten auf deine offenen Fragen

- Autor/Herausgeber: Das Zusatzfeld bleibt, bis weclapp antwortet. Bei „Praxiswissen Arbeitsrecht 2026 katholisch“ bleibt es leer.
- `ketteler:einrichten` vor dem Livegang auf „nur anlegen“ umstellen: Ja, einverstanden. Bitte gleich mit dieser Korrektur umsetzen, mit einem ausdrücklichen Schalter zum Überschreiben für Staging.
- Die zwei Passwörter aus dem Chat: Ja, erneuern. Die neuen Werte nur in die `.env` (600) auf dem Server schreiben und mir sagen, welche Einträge neu sind, ohne die Werte. Atilla überträgt sie in seinen Passwortmanager.
- PayPal SW100052: zurückgestellt. Der Shop geht zuerst mit Rechnung fertig, PayPal kommt später mit Kettelers echtem Konto. Nichts weiter tun.
- Abschalten der PayPal-Express-Knöpfe und der Werbung: richtig so, bleibt aus.

Danach weiter mit Abschnitt B des Gesamtauftrags.
