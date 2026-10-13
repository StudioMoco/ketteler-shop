# Eigene Entscheidungen bei der Umsetzung (mit Begründung)

Entscheidungen, die der Auftrag offen ließ und die ich ohne Rückfrage getroffen habe (Gesamtauftrag
`docs/auftraege/00-gesamtauftrag.md`: „nicht warten, Entscheidung mit Begründung notieren“). Jede lässt sich ändern.

## Allgemein

1. **Kopf und Fuß aller Seiten nach der Startseiten-Vorlage.** Die Werk- und Storytelling-Vorlagen zeigen denselben Kopf
   und Fuß; maßgeblich bleibt eine Umsetzung (`docs/design/kopf-fuss.md`), damit Pflege und Tests an einer Stelle liegen.
2. **Fuß zusätzlich mit „Widerruf erklären“ und „Verträge hier kündigen“.** Die Vorlagen kennen diese Einträge nicht; beide
   sind gesetzlich nötig (Widerrufsfunktion, Kündigungsknopf). Frage 18 an Ketteler.
3. **Kontaktlinks im Fuß dauerhaft unterstrichen.** Sonst wären sie nur an der Farbe erkennbar (Barrierefreiheit). Beim
   Überfahren ändert sich weiterhin nur die Farbe.
4. **Logo `ketteler-logo-fachverlag.png` (480 × 94, doppelte Auflösung).** Das Logo aus dem Chat kam nicht an; die Datei
   in `docs/design/logo/` ist dieselbe Marke. SVG erbeten (Frage 19).
5. **weclapp bleibt getrennt (Phase 3 nicht begonnen).** Testbestellungen bleiben deshalb auf Staging und gehen nirgends hin.
6. **Textvergleich nur im Hauptbereich.** Kopf und Fuß prüft der Startseiten-Test; je Werkseite wird der Bereich zwischen
   Brotkrumen und Fuß der Vorlage verglichen, mit Preisen.

## Startseite (Abschnitt A)

7. **„Fachliteratur katholisch/evangelisch“ führen vorläufig auf „Aktuell & interessant“.** Die Word-Vorlagen liegen nur im
   alten Shop (Frage 20). Gilt auch auf den Werkseiten.
8. **Bildschirmfoto des Dropdowns unter „Zeitschrift ZMV“.** „Praxishandbücher“ hat keine Unterkategorien.

## Werkseiten (Abschnitt B)

9. **Jahrgang der Einzelhefte aus der Artikelnummer, Zusatzfeld nur für Ausnahmen.** weclapp liefert die Nummer ohnehin
   (`Z5-2026`), Ketteler muss nichts zusätzlich pflegen, und der Shop kann ohne Datenpflege sortieren. Erkannt werden die
   vierstellige Jahreszahl und die Heftnummer daneben (auch `ZMV-2026-5` und „5/2026“ im Namen). Die Zusatzfelder
   `ketteler_jahrgang` und `ketteler_heft` gehen vor (Sonderhefte). Frage 25.
10. **Jahrgangsreiter aus den vorhandenen Heften.** Die Reiter entstehen aus den Jahrgängen der Kategorie „ZMV Print“
    (neuester zuerst, Anzahl einstellbar, Standard 5). Ein Jahr ohne Hefte bekommt keinen leeren Reiter.
11. **Testartikel behalten ihre TEST-Nummern.** Die Karten zeigen deshalb „Bestell-Nr. TEST-O-AVR“ statt „05029-D“. Echte
    Nummern kämen mit weclapp; gleiche Nummern jetzt würden beim Abgleich kollidieren. Der Test rechnet die Nummern um.
12. **AVR Digital ist `TEST-O-AVR` als Abo; `TEST-ABO-AVR-D` entfällt.** Zwei Artikel für dasselbe Angebot hätten der
    Entscheidung „online nur als Abo“ widersprochen.
13. **AVR Print als Abverkauf (Shopware „Abverkauf“).** Bei Bestand 0 nicht bestellbar, Karte zeigt „Zurzeit nicht
    lieferbar“. Zum Testen gibt es `TEST-P-AVR` (Bestand 3) und `TEST-P-AVR-0` (Bestand 0).
14. **`TEST-B-EK` (Eichstätter Print und Online) ohne Kategorie.** Der Eichstätter Kommentar wird zurzeit nur online
    verkauft; der Artikel bleibt nur für die Tests der Lizenzlogik (Paket). Frage 26.
15. **Mehrplatz-Anfrage zeigt das Werk als Auswahlfeld.** Vorher stand es unsichtbar im Formular; so sieht die Kundin,
    worauf sich die Anfrage bezieht, und kann es ändern.
16. **Preis in der Zeile „Digital zusätzlich“ aus dem Produkt.** Der Text enthält `{preis}`, der Shop setzt den Preis des
    gewählten Produkts ein; so stimmt er auch nach einer Preisänderung.
17. **Weiterleitungen im Theme statt in Caddy.** Ein kleiner Dienst (`Weiterleitung/AlteAdressen.php`, Liste in
    `Resources/config/weiterleitungen.json`) leitet vor dem Routing mit 301 auf die SEO-Adresse der Kategorie. So wirken sie
    lokal, auf Staging und Live gleich und lassen sich testen; Caddy (gemeinsam mit der Redaktion) bleibt unberührt.
    `/zmvaktuell` zeigt vorläufig auf die Aktuelle Ausgabe der ZMV-Seite (`#probeheft`), `/stob` auf die AGB.
18. **Platzhalter-PDFs statt leerer Links.** Jeder Leseprobe-Link führt auf ein einseitiges PDF mit dem Titel; Ketteler ersetzt
    die Datei in der Medienverwaltung, der Link bleibt. Liste: `docs/dateien-ketteler.md`.
19. **„Online lesen“ und „Online-Ausgabe“ führen in die KETTELER-Bibliothek** (Link-Ziel `bibliothek`, Adresse aus der
    Theme-Einstellung). „Probeheft Print anfordern“ führt auf den Testartikel „ZMV-Probeheft Print, kostenlos“ (0,00 €), wie
    heute Artikel 031.
20. **Shopwares eigene Brotkrumen entfallen auf Werkseiten.** Der Werk-Kopf bringt sie nach der Vorlage mit; doppelt wäre
    unruhig.
21. **Testhefte 2022 und 2023 ergänzt.** Die Vorlage zeigt Reiter bis 2022; ohne Hefte gäbe es diese Reiter nicht. 2022 zum
    Archivpreis 17,50 € (Zeile „Einzelhefte 2020 bis 2022“), 2023 zu 24,50 €.
22. **Überschreib-Schalter auf Staging bei jeder Auslieferung dieses Durchgangs.** Der Auftrag sieht den Schalter für
    diesen Durchgang vor; die Testdaten ändern sich aber je Abschnitt (B: Testartikel und Abo, D: Zahlarten, Mails), und
    „Tests auf Staging grün“ gilt je Abschnitt. Auf Staging gibt es keine von Ketteler gepflegten Inhalte; Erlebniswelten
    und die Inhalte der Werkseiten bleiben auch beim Überschreiben unberührt (dafür gibt es nur `--erlebniswelten-neu`).

## Storytelling-Seiten (Abschnitt C)

23. **MAV Einstieg und „Aktuell & interessant“ sind dieselbe Seite.** Die Vorlage markiert auf der Übersicht den Reiter
    „Aktuell & interessant“; die Übersicht enthält genau dessen Inhalte. Beide Kategorien zeigen dieselbe Erlebniswelt,
    gepflegt wird an einer Stelle.
24. **Reiterbeschriftung über das Zusatzfeld „Kurzname“.** Die Kategorien heißen im Menü wie auf der Startseite
    („Grundausstattung/Basiswissen für die MAV“), die Reiter und Brotkrumen wie in der Vorlage („Grundwissen/
    Basisausstattung“). Ohne Kurzname gilt der Name.
25. **Shopware-Brotkrumen auf Seiten mit eigenen Brotkrumen ausgeblendet** (Werk-Kopf, Unternavigation, Seitenkopf,
    Veranstaltung), sonst stünden sie doppelt.
26. **Brotkrumen folgen dem Kategoriebaum.** Die Vorlagen zeigen „Zeitschrift ZMV / ZMV Fachtagung“ und „Praxishandbücher /
    Interviews Autoren“; im Menü der Startseite stehen beide unter „Verlag“. Der Baum bleibt wie freigegeben, die
    Brotkrumen zeigen „Verlag / …“.
27. **Übernommene Texte wörtlich, auch mit Gedankenstrichen und Tippfehlern.** Grundsatz 2 („wörtlich“) geht bei fremden
    Texten (Artikeltitel, Verlagstext) vor; eigene Texte des Shops haben keine Gedankenstriche. Fragen 29 und 30.
28. **„ZMV aktuell“ als eigene Kategorie unter Zeitschrift ZMV** mit dem Block „Aktuelle Ausgabe“ wie auf der ZMV-Seite
    (Texte der freigegebenen ZMV-Vorlage statt der alten Sammelzeile). `/zmvaktuell` leitet jetzt dorthin.
29. **Seiten ohne Vorlage im Layout der nächsten Vorlage:** Praktische Arbeitshilfen und Themenbezogene Literatur wie
    Grundwissen (Titelkarten), Hilfreiche Links als Artikelliste über die ganze Breite, Verlag und Links als Fließtext
    mit Seitenkopf. Die Unterzeilen „2. Praktische Arbeitshilfen“ und „3. Themenbezogene Literatur“ stammen aus der
    Übersicht in `content/`.
30. **Platzhalterbilder statt leerer Kreise.** Personenfotos sind Medien „Platzhalter: Name“ (Sand) mit dem Namen als
    Alt-Text; Ketteler ersetzt die Datei, Alt-Text und Verweise bleiben.
31. **Weiterleitungen unterscheiden Groß- und Kleinschreibung.** Die alten Adressen sind klein (`/verlag`), die neuen
    beginnen groß (`/Verlag/`); ohne Unterscheidung leitete die neue Seite auf sich selbst um.
32. **Auf Staging einmal `--erlebniswelten-neu`** (mit der Auslieferung von Abschnitt C), damit Startseite, Werkseiten und
    die neuen Seiten genau dem Stand der Daten entsprechen (u. a. Foto-Platzhalter auf der AVR-Seite). Auf Staging pflegt
    noch niemand Inhalte.

## Shop testbereit (Abschnitt D)

33. **PayPal nur aus dem Verkaufskanal genommen.** Zahlart und Plugin bleiben aktiv und im Testmodus eingerichtet; zum
    Wiedereinschalten genügt die Zuordnung im Verkaufskanal. `paypal_test.py` überspringt sich, solange PayPal nicht im
    Kanal ist.
34. **Absender aller Mails `kontakt@ketteler-verlag.de`** (Impressum). Auf Staging landen alle Mails in Mailpit.
35. **Logo in den Mails als eingebettetes Bild.** Hinter dem Passwortschutz von Staging lädt ein verlinktes Bild nicht.
    Vor Live prüfen: Gmail zeigt eingebettete Bilder nicht; dann auf die Adresse des Logos im Shop umstellen.
36. **„Verträge hier kündigen“ als eigenes Formular** (Plugin KettelerMehrplatz, das schon die Mehrplatz-Anfrage
    trägt): Felder aus `content/rechtstexte/vertraege-kuendigen.md` ohne Anrede, Titel und Land (für die Zuordnung nicht
    nötig) und ohne Bild-Captcha (Shopware-Schutz gegen Bots wie bei der Mehrplatz-Anfrage). Bestätigungsseite zum
    Speichern oder Drucken, Eingangsbestätigung per Mail mit Datum und Uhrzeit, Mail an den Kundenservice.
37. **Eingangsbestätigungen über Mailvorlagen von Shopware** (neue Mailarten „Mehrplatz-Anfrage: Eingangsbestätigung“
    und „Kündigung: Eingangsbestätigung“), damit Kopf, Fuß und Pflege wie bei allen anderen Mails sind.
38. **Testkonten:** Firma DE mit der öffentlichen USt-IdNr. des KETTELER-Verlags (Inland, VIES bestätigt sie), Einrichtung
    AT mit der öffentlichen OMV-UID aus der .env-Notiz. Alle vier mit dem Passwort der automatischen Testkunden
    (`KETTELER_TESTKUNDE_PASSWORT`), es entsteht kein neuer Schlüssel. Die automatischen Tests räumen sie nicht weg.
39. **Cookie-Hinweis nur „Technisch erforderlich“.** Der Shop setzt keine anderen Cookies; die Gruppen „Komfort“ und
    „Statistik“ von Shopware entfallen. Folge: Gäste können die Merkliste nicht im Browser speichern, angemeldete
    Kundinnen schon.
40. **Suche mit Verlagsnummer.** Solange die Testartikel TEST-Nummern tragen, steht die alte Bestell-Nr. (05029-D usw.)
    als Herstellernummer am Artikel; die Suche durchsucht Titel, Bestell-Nr., Herstellernummer und Autor/Herausgeber.
41. **Kontaktformular: Telefon freiwillig**, wie bei der Mehrplatz-Anfrage.
42. **ZITADEL im Stil des Verlags** über die API: Farben, Logo, deutsche Mailtexte in Sie-Form (E-Mail bestätigen,
    Passwort vergessen, Passwort geändert, Konto einrichten). Schrift: Standard von ZITADEL (eigene Schrift wäre ein
    Upload in ZITADEL, Frage an Atilla bei Bedarf).
43. **Firmendaten auf den Belegen** aus dem Impressum; Bankverbindung und Steuernummer fehlen (Frage 32). Rechnungen
    schreibt später weclapp.
