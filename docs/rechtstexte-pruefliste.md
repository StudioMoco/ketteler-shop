# Prüfliste Rechtstexte für den neuen Shop

Stand 12.10.2026. Grundlage: die Rechtstexte aus dem bisherigen NCE-Shop in `content/rechtstexte/` (abgerufen am
12.10.2026) und die Entscheidungen in `docs/plan.md`.

Die Texte werden als Entwurf in den neuen Shop übernommen und dort mit „von Ketteler zu prüfen“ gekennzeichnet. Diese
Liste nennt die Stellen, die für den neuen Shop vermutlich angepasst werden müssen, und nennt den Grund. Sie enthält
keine neuen Formulierungen und ist keine Rechtsberatung. Ob und wie eine Stelle geändert wird, entscheidet Ketteler,
bei Bedarf mit rechtlicher Beratung.

Zitate sind wörtlich aus den Dateien übernommen (gekürzt mit „…“). Gründe:

- **Online-Zugang/Bibliothek**: Zugang wird nach dem Kauf sofort vergeben (auch auf Rechnung), Lesen in der Bibliothek.
- **neue Anmeldung (ZITADEL)**: ein Konto für Shop und Bibliothek über `login.ketteler-verlag.de`.
- **weclapp-Anbindung**: Kunden- und Bestelldaten gehen über die offizielle Integration an weclapp.
- **PayPal/Zahlarten**: nur Rechnung und PayPal (offizielles Shopware-Plugin).
- **Lieferländer/Versand**: Deutschland, Österreich, Schweiz, Italien, Großbritannien und EU.
- **Steuern**: Online-Zugänge an Privatkunden in anderen EU-Ländern mit der Umsatzsteuer des Kundenlandes.
- **Abos**: ZMV Print und Digital, AVR Digital im Shop bestellbar, Vertrag legt der Verlag in weclapp an.
- **Mehrplatz**: Online-Artikel nur einzeln, mehrere Lizenzen über „Mehrere Lizenzen anfragen“.
- **Widerspruch zwischen Texten**, **veraltet/fehlerhaft**.

## AGB (`agb.md`, Stand 30.10.2024)

| Nr. | Stelle | Zitat | Grund | Zu prüfen |
|---|---|---|---|---|
| A1 | 2. (3) | „innerhalb von 5 Werktagen nach Eingang anzunehmen“ | Online-Zugang/Bibliothek | Passt die Annahmefrist zu Online-Zugängen, die mit der Bestellung sofort freigeschaltet werden? |
| A2 | 2. (4) | „bei digitalen Produkten durch Freischaltung des Zugangs bzw. der Einlösung eines Gutscheins“ | Online-Zugang/Bibliothek | Ist der Zeitpunkt des Vertragsschlusses für die automatische Freischaltung (einige Sekunden nach der Bestellung) noch richtig beschrieben, und spielt Gutschein oder Code noch eine Rolle? |
| A3 | 2. (5) | „Mit der Bestellbestätigung erhält der Kunde die Allgemeinen Geschäftsbedingungen mit der Widerrufsbelehrung“ | veraltet/fehlerhaft | Hängt die Bestellbestätigung des neuen Shops AGB und Widerrufsbelehrung tatsächlich an, oder nur einen Link? |
| A4 | 2. (5) | „wird der Vertragstext einschließlich der Bestellung beim Verlag gespeichert“ | weclapp-Anbindung | Wo ist der Vertragstext künftig gespeichert (Shopware, weclapp) und ist er für den Kunden im Kundenkonto abrufbar? |
| A5 | 3. (1) | „Bereitstellung der digitalen Daten bzw. Ausgaben sowie Berechnung erfolgt ab aktueller Ausgabe bzw. nach Vereinbarung“ | Abos | Wann kommt ein im Shop bestelltes Abo zustande, wenn der Verlag den Vertrag erst danach in weclapp anlegt? |
| A6 | 3. (4) | „Ein Anspruch auf Lieferung besteht in jedem Fall erst nach Eingang der Zahlung.“ | Abos | Gilt das noch für AVR Digital, wenn der Online-Zugang sofort und auch auf Rechnung vergeben wird? |
| A7 | 3. (5) | „bedarf der Schriftform (Brief, Telefax, E-Mail)“ | Abos | Passt die Formvorgabe zur Kündigungsseite „Verträge kündigen“ im neuen Shop? |
| A8 | 3. (11) und 6. (16) | „gilt grundsätzlich nur bis zum Erscheinen einer neuen Auflage“ | Online-Zugang/Bibliothek | Wie lange läuft eine im Shop gekaufte Eichstätter-Lizenz in der Lizenzverwaltung, und stimmt das mit dieser Klausel überein? |
| A9 | 5. (1) | „Endpreise in Euro inklusive der gesetzlichen Umsatzsteuer“ | Steuern | Sind die Angaben richtig, wenn Privatkunden in anderen EU-Ländern die Umsatzsteuer ihres Landes zahlen und Lieferungen in die Schweiz und nach Großbritannien anders besteuert werden? |
| A10 | 5. (2) | „Staffelpreisen bei Mehrfachlizenzen im Bereich eines digitalen Abonnement-Vertrages“ | Mehrplatz | Wie verhält sich die Staffelpreis-Regel zum Weg über „Mehrere Lizenzen anfragen“? |
| A11 | 5. (4) | „sind unter [„Versand & Lieferung“](https://www.ketteler-verlag.de/shipping) auf der Website des Verlags aufgeführt“ | Lieferländer/Versand | Zeigt der Link im neuen Shop auf die richtige Seite, und bildet Shopware die Versandkosten wie beschrieben nach Gewicht ab? |
| A12 | 5. (8) | „willigt ein, dass der Verlag für seine Sendung eine E-Mail mit Informationen zur Sendungsverfolgung“ | weclapp-Anbindung | Wer verschickt die Sendungsverfolgung künftig (Shop oder weclapp), und ist eine Einwilligung in den AGB dafür der richtige Ort? |
| A13 | 6. (1) | „Rechnung, PayPal, Bankeinzug, Nachnahme.“ | PayPal/Zahlarten | Werden Bankeinzug und Nachnahme noch angeboten, und welche Zahlarten des PayPal-Plugins (z. B. Kreditkarte, Später bezahlen) werden freigeschaltet? |
| A14 | 6. (2) | „erfolgt die Bezahlung nur per PayPal“ | PayPal/Zahlarten | Werden Downloads von Einzelartikeln im neuen Shop verkauft, und gilt dafür weiterhin nur PayPal? |
| A15 | 6. (5) | „über dem Basiszinssatz der Europäischen Zentralbank“ | veraltet/fehlerhaft | Ist der Bezug auf die Europäische Zentralbank richtig, oder ist der Basiszinssatz nach § 247 BGB gemeint? |
| A16 | 6. (10) | „Das Passwort vergibt der Kunde und ist nur diesem bekannt.“ | neue Anmeldung (ZITADEL) | Ist die Beschreibung von Anmeldung und Registrierung noch richtig, wenn das Konto bei `login.ketteler-verlag.de` liegt und für Shop und Bibliothek gilt? |
| A17 | 6. (10) und (13) | „Die Freischaltung von Einzel- oder Mehrfach-Lizenzen erfolgt durch den Verlag.“ | Online-Zugang/Bibliothek | Passt das zur automatischen Freischaltung von Einzellizenzen beim Kauf im Shop? |
| A18 | 6. (12) | „erfolgt durch Eingabe eines Codes unter www.ketteler-verlag.de“ | Online-Zugang/Bibliothek | Gilt der Code-Weg nur noch für Bundles und Buchhandel, während ein Kauf im Shop die Lizenz direkt vergibt? |
| A19 | 6. (14) | „über eine statische IP-Adresse oder IP-Bereiche verfügen“ | Mehrplatz | Bietet die neue Bibliothek IP-Zugänge in dieser Form an? |
| A20 | 7. (2) | „im Rahmen seiner gewerblichen, selbständigen oder wissenschaftlichen Tätigkeit“ | Online-Zugang/Bibliothek | Passt die Einschränkung des Nutzungsrechts, wenn auch Privatkunden Online-Zugänge kaufen? |
| A21 | 7. (5) | „eine Sammelnutzung z. B. durch verschiedene Mitarbeiter eines Kunden ist ausdrücklich ausgeschlossen“ | Mehrplatz | Was gilt, wenn jemand für eine Einrichtung bestellt und die Lizenz an die E-Mail des bestellenden Kontos vergeben wird? |
| A22 | 15. Anwendbares Recht | „Es gilt ausschließlich das Recht der Bundesrepublik Deutschland.“ | Lieferländer/Versand | Ist die Rechtswahl gegenüber Verbrauchern in Österreich, Italien und anderen EU-Ländern so haltbar? |
| A23 | 16. | „Die Plattform ist unter“ (Satz endet ohne Verb nach dem Link) | veraltet/fehlerhaft | Ist der Hinweis auf die OS-Plattform nach deren Einstellung im Jahr 2025 noch nötig, und ist der unvollständige Satz zu korrigieren? |
| A24 | 18. (1) | „Widerrufsrecht bei körperlicher Ware (Print-Produkte, CD, DVD, sonstige Gegenstände)“ | Online-Zugang/Bibliothek | Fehlt eine Belehrung für Online-Zugänge und digitale Abos, die im Shop verkauft werden? |
| A25 | 18. (3) | „wenn der Preis des Abonnements bis zur ersten Kündigungsmöglichkeit weniger als 200 Euro beträgt“ | Abos | Welche der im Shop bestellbaren Abos (ZMV Print, Digital, Kombi, AVR Digital) fallen nach aktuellen Preisen unter diese Grenze? |
| A26 | 18. (3) | „digitale Produkte oder Online-Zugänge nach Freischaltung des Zugangs“ | Online-Zugang/Bibliothek | Reicht diese Regel neben der Checkbox im Checkout, oder müssen Zustimmung und Bestätigung auf dauerhaftem Datenträger erwähnt werden? |
| A27 | 18. (3) | „sofern die Folie des Printwerkes beschädigt oder entfernt wurde“ | Online-Zugang/Bibliothek | Gilt das noch für Bundles, deren Online-Zugang im Shop sofort und ohne Code vergeben wird? |

## Widerrufsbelehrung (`widerruf.md`)

Die Stellen zu AGB Nr. 18 (A24 bis A27) gelten für diesen Text genauso, ebenso der Link auf das PDF-Formular; zur doppelten Fassung siehe X9.

| Nr. | Stelle | Zitat | Grund | Zu prüfen |
|---|---|---|---|---|
| W1 | Muster-Widerrufsformular | „Kundennummer:“ und „Rechnungsnummer:“ | weclapp-Anbindung | Welche Nummern findet der Kunde künftig (Shop SK/SW oder weclapp), und entspricht das Formular dem gesetzlichen Muster (z. B. „bestellt am / erhalten am“)? |

## Versand & Lieferung (`versand-lieferung.md`)

Zahlarten, Verzugszinsen, Versanddienstleister und Nichtverfügbarkeit stehen unter X2, X3, X5 und X7.

| Nr. | Stelle | Zitat | Grund | Zu prüfen |
|---|---|---|---|---|
| V1 | Versandkosten (2) | „Bei Lieferungen innerhalb der Bundesrepublik Deutschland fallen folgende Versandkosten an“ | weclapp-Anbindung | Stimmen die Gewichtsstufen mit dem Versandkosten-Schema überein, das in weclapp bereinigt und im Shop abgebildet wird? |
| V2 | Versandkosten (5) | „Wir liefern nach Österreich, Schweiz und Italien.“ | Lieferländer/Versand | Fehlen Großbritannien und die übrigen EU-Länder, die der neue Shop beliefert, samt Versandkosten? |
| V3 | Schluss | „Bei Lieferwünschen in andere als die vorstehend genannten Länder bitten wir um vorherige Kontaktaufnahme.“ | Lieferländer/Versand | Passt das, wenn der Shop alle EU-Länder direkt anbietet? |

## Preise ZMV (`preise-zmv.md`, Preise ab 01.01.2026)

Zur Kündigungsfrist siehe X4, zu den Bruttopreisen im EU-Ausland A9.

| Nr. | Stelle | Zitat | Grund | Zu prüfen |
|---|---|---|---|---|
| P1 | Einleitung | „Inkl. Volltext-Archiv ab Jahrgang 2000.“ | Online-Zugang/Bibliothek | Wo liest der Kunde die ZMV Digital nach einer Bestellung im neuen Shop, und ist das Archiv dort enthalten? |
| P2 | Kündigungsfrist | „falls das Abonnement nicht rechtzeitig schriftlich gekündigt wird“ | Abos | Passt „schriftlich“ zur Kündigungsseite im Shop? |
| P3 | Tabelle | „Abonnement Digital zusätzlich zum bestehenden Abonnement Print“ | Abos | Wie wird im Shop geprüft, dass ein Print-Abo besteht, wenn die Abo-Verträge in weclapp liegen? |
| P4 | Tabelle | „(Ab 2. Lizenz: € 59,90)“ | Mehrplatz | Wie wird der Preis ab der 2. Lizenz angeboten, wenn mehrere Lizenzen nur über das Anfrageformular gehen? |

## Verträge kündigen (`vertraege-kuendigen.md`)

| Nr. | Stelle | Zitat | Grund | Zu prüfen |
|---|---|---|---|---|
| K1 | ganze Seite | „Hier können Sie Ihre Verträge kündigen.“ | Abos | Wird die Kündigungsseite im neuen Shop nachgebaut, und an wen geht die Kündigung (Verwaltung, weclapp, Lizenzverwaltung)? |
| K2 | Angaben zum Vertrag | „Abo-Nummer oder letzte Rechnungsnummer“ | weclapp-Anbindung | Welche Nummern hat der Kunde künftig (weclapp-Abo, Shop-Bestellung SW…)? |
| K3 | Absendung | „Captcha-Bild (Zahlen) und Eingabefeld“ | veraltet/fehlerhaft | Ist ein Captcha vor dem Kündigungsknopf zulässig, und wie kommt die Eingangsbestätigung per E-Mail zustande? |

## Impressum (`impressum.md`)

Zur OS-Plattform siehe A23, zum Nutzungsrecht X12, zur Anschrift X11.

| Nr. | Stelle | Zitat | Grund | Zu prüfen |
|---|---|---|---|---|
| I1 | Kontakt | „Internet: [www.ketteler-verlag.de](https://www.ketteler-verlag.de/www.ketteler-verlag.de)“ | veraltet/fehlerhaft | Der Link ist fehlerhaft gesetzt. |
| I2 | Haftung für Inhalte | „gemäß § 7 Abs. 1 TMG für eigene Inhalte“ | veraltet/fehlerhaft | Sind die Verweise auf das TMG (§§ 7 bis 10) nach dessen Ablösung durch das DDG noch richtig? |
| I3 | ganzer Text | „Sitz des Verlages“ | neue Anmeldung (ZITADEL) | Gilt das Impressum auch für `login.ketteler-verlag.de` und die Bibliothek, und ist es dort verlinkt? |

## Datenschutzerklärung (`datenschutz.md`, Stand 01.01.2024)

| Nr. | Stelle | Zitat | Grund | Zu prüfen |
|---|---|---|---|---|
| D1 | 2. | „Diese Information werten wir statistisch aus“ | veraltet/fehlerhaft | Welche Auswertung und welche Protokolle gibt es im neuen Shop (Hosting bei Hetzner), und stimmt die Frist von 14 Monaten? |
| D2 | 2. Cookies | „Wir verwenden auf unserer Website Session Cookies.“ | neue Anmeldung (ZITADEL) | Sind die Cookies von Shopware, PayPal und der ZITADEL-Sitzung auf `login.ketteler-verlag.de` erfasst? |
| D3 | 3. 2) | „PayPal bekommt dabei von uns keine personenbezogenen Daten.“ | PayPal/Zahlarten | Stimmt das noch, wenn das offizielle Plugin Bestell- und Adressdaten an PayPal übergibt? |
| D4 | 3. 4) | „Beteiligte Auftragsverarbeiter sind z.B. Webhoster und Druckereien.“ | weclapp-Anbindung | Sind weclapp, Hetzner und der künftige Mail-Anbieter als Empfänger genannt? |
| D5 | 4. Newsletter | „dann verwenden wir diese zum Versenden eines Newsletters“ | veraltet/fehlerhaft | Zeigt der neue Checkout beim Erheben der E-Mail den nötigen Hinweis und die Widerspruchsmöglichkeit? |
| D6 | 4. Newsletter | „E-Mail-Adressen für den Mitgliederbereich“ | Online-Zugang/Bibliothek | Ist mit „Mitgliederbereich“ die Bibliothek gemeint, und ist sie beschrieben? |
| D7 | 6. YouTube | „Wir nutzen einen Youtube-Kanal des Betreibers Google Ireland Limited“ | veraltet/fehlerhaft | Werden im neuen Shop Videos eingebettet, und ist das (nicht nur der Kanal) beschrieben? |
| D8 | Facebook, Einleitung | „In dieser Vereinbarung ist geregelt, dass Facebook die“ | veraltet/fehlerhaft | Der Satz bricht ab; wie lautet er vollständig? |

## AGB für Anzeigen und Beilagen (`agb-anzeigen.md`, Stand 06.02.2017)

Zu den Verzugszinsen siehe X3 und A15.

| Nr. | Stelle | Zitat | Grund | Zu prüfen |
|---|---|---|---|---|
| N1 | ganzer Text | „Stand: 06.02.2017“ | veraltet/fehlerhaft | Soll der Text überhaupt im Shop stehen, da Anzeigen nicht über den Shop gebucht werden? |

## Widersprüche zwischen den Texten

| Nr. | Thema | Stellen | Zu prüfen |
|---|---|---|---|
| X1 | Lieferzeit | AGB 6. (7) „innerhalb von 5 Werktagen nach Annahme“; Versand & Lieferung (1) „innerhalb von 3 Werktagen nach Annahme“; dazu AGB 2. (3) Annahme binnen 5 Werktagen | Welche Frist gilt? |
| X2 | Zahlarten | AGB 6. (1) „Rechnung, PayPal, Bankeinzug, Nachnahme.“ mit Zusatz „nur per Rechnung oder PayPal“; Versand & Lieferung (5) „Rechnung, Bankeinzug, Nachnahme.“; Plan: nur Rechnung und PayPal | Welche Zahlarten nennen die Texte künftig? |
| X3 | Verzugszinsen Unternehmer | AGB 6. (5) „9 v.H. bei Unternehmern“; Versand & Lieferung (7) „8 v.H. bei Unternehmern“; AGB Anzeigen 6. (3) „8 v. H.“ | Welcher Satz gilt? |
| X4 | Kündigungsfrist Abos | AGB 3. (6) und (7) (Verbraucher ab 1. März 2022: ein Monat nach Mindestlaufzeit); Preise ZMV „6 Wochen zum Ende eines Kalenderjahres“ ohne Unterscheidung | Gilt die Preisseite nur für Unternehmer und Altverträge? |
| X5 | Versanddienstleister | Versand & Lieferung (1) Deutsche Post/DPD; dieselbe Seite, Tabellen: DHL Paket International; Datenschutz 3. 5) Deutsche Post/DPD | Welche Dienstleister werden genutzt? |
| X6 | Lieferländer | Versand & Lieferung (5) Österreich, Schweiz, Italien; dieselbe Seite DHL Zone 2 mit Großbritannien; Plan: zusätzlich Großbritannien und EU | Welche Länder nennen die Texte? |
| X7 | Nichtverfügbarkeit | AGB 4. ohne Einschränkung; Versand & Lieferung, Absatz nach (8), mit „nicht zu vertreten haben“ | Welche Fassung gilt? |
| X8 | Versandkosten Abos | AGB 5. (6) mit Ausnahme für Fortsetzungswerke; Versand & Lieferung Versandkosten (4) ohne Ausnahme | Gilt die Ausnahme? |
| X9 | Widerrufsbelehrung doppelt | AGB 18. und `widerruf.md`; Wortlaut weicht ab, Muster-Formular steht nur in `widerruf.md` | Welche Fassung ist maßgeblich? |
| X10 | Doppelte Klauseln in den AGB | AGB 3. (10)/(11) und 6. (15)/(16); Titel einmal „Eichstätter Kommentar“, einmal „Eichstätter Kommentar MAVO-KAGO“ | Reicht eine Stelle? |
| X11 | Anschrift des Verlags | Impressum „Sitz des Verlages“ Köln; AGB Kopf, Widerruf und Datenschutz 1. nur Waldmünchen; AGB 14. Gerichtsstand Cham/Oberpfalz | Ist die Anschrift des Verantwortlichen und des Vertragspartners überall richtig? |
| X12 | Nutzungsrecht | Impressum „nur für den privaten und nicht kommerziellen Gebrauch“; AGB 7. (2) Nutzung im Rahmen gewerblicher Tätigkeit | Welche Regel gilt für gekaufte Inhalte? |

## Fehlt vermutlich ganz

Zu diesen Themen gibt es in den übernommenen Texten noch nichts. Zu prüfen ist jeweils, ob ein Text nötig ist und wer
ihn liefert.

1. **ZITADEL**: Datenverarbeitung für das gemeinsame Konto von Shop und Bibliothek (`login.ketteler-verlag.de`),
   Sitzungscookies, Einladungs- und Bestätigungsmails, Löschung des Kontos.
2. **Lizenzverwaltung und Bibliothek**: Übermittlung von E-Mail, Name, Produkt und Bestellnummer an das
   Redaktionssystem; Daten, die beim Lesen in der Bibliothek anfallen.
3. **weclapp**: Übermittlung von Kunden- und Bestelldaten, Rolle als Auftragsverarbeiter.
4. **PayPal**: Datenübermittlung durch das offizielle Plugin, mögliche Übermittlung in Drittländer.
5. **Mailversand**: Mails des Shops über einen Mail-Anbieter (noch nicht gewählt).
6. **Hosting**: Hetzner (Deutschland) als Auftragsverarbeiter.
7. **Cookie-Hinweis und Einwilligung**: welche Cookies Shopware, PayPal und ZITADEL setzen und ob ein Einwilligungsbanner
   nötig ist.
8. **Zustimmungstext im Checkout** für Privatkunden: sofortiger Beginn des Online-Zugangs und Erlöschen des
   Widerrufsrechts (§ 356 Abs. 5 BGB); dazu die Frage, ob die Bestellbestätigung diese Zustimmung wiedergeben muss.
9. **Widerrufsbelehrung für digitale Inhalte und Abos** (Online-Zugang, ZMV Digital, AVR Digital), einschließlich der
   Frage nach einem passenden Muster.
10. **Elektronische Widerrufsfunktion**: Braucht der Shop seit Juni 2026 eine eigene Funktion, mit der Verbraucher
    online widerrufen können?
11. **Online-Zugang beim Einmalkauf**: Laufzeit, Ende bei Storno oder Widerruf, Sperre bei nicht bezahlter Rechnung
    (so im Plan vorgesehen), Hinweis „Zugang wird eingerichtet“.
12. **Gastbestellung**: Hinweis, dass Online-Artikel ein Konto voraussetzen und Gäste nur Print bestellen können.
13. **Mehrere Lizenzen anfragen**: Ablauf und Vertragsschluss über das Formular, Datenschutz für das Formular.
14. **Abo-Bestellung im Shop**: Vertragsschluss, wenn der Verlag den Vertrag nachträglich in weclapp anlegt.
15. **Steuern im Ausland**: Hinweis auf länderabhängige Umsatzsteuer für Privatkunden in der EU sowie auf Zölle und
    Einfuhrabgaben für Schweiz und Großbritannien.
16. **Lieferzeit für Online-Zugänge** (sofort) auf der Seite Versand & Lieferung.
17. **Barrierefreiheit**: Gilt für den Verlag eine Pflicht nach dem Barrierefreiheitsstärkungsgesetz, und braucht der
    Shop dazu Angaben?
