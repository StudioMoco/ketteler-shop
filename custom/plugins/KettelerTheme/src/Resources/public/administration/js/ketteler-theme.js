/*
 * Erlebniswelten: eigene Blöcke des KETTELER-Verlags (Schaufenster, Kategorien mit Cover-Stapel, Werke, Hinweis mit
 * Zeichen, Drei Beiträge, Nummerierte Liste, Produktreihe; für die Werkseiten Werk-Kopf, Angebote, Vorteile / Liste,
 * Leseproben, Video mit Person, Einzelhefte mit Jahrgangsauswahl, Aktuelle Ausgabe, Fakten, Rückmeldung; für die
 * Storytelling-Seiten Unternavigation, Seitenkopf, Begrüßungsbrief, Bereichskacheln, Artikelliste, Titelkarten,
 * Veranstaltung, Interviewliste). Bewusst ohne Bauschritt geschrieben (kein Webpack),
 * Shopware lädt diese Datei direkt. Jeder Block hat genau ein Element gleichen Namens; dessen Einstellungen beschreibt
 * ein Feldschema (FELDER), aus dem das Einstellungsfenster gebaut wird. Darstellung im Shop:
 * Resources/views/storefront/element/cms-element-ketteler-*.html.twig, Daten lädt Cms/KettelerElementResolver.php.
 *
 * Links speichern „kategorie:<id>“, „produkt:<id>“ oder eine Adresse (/pfad, https://…, mailto:, tel:).
 */
(function () {
    'use strict';

    var Criteria = Shopware.Data.Criteria;

    var FLAECHEN = [{ value: 'sand', label: 'Sand' }, { value: 'rose', label: 'Rosé' }];
    var FLAECHEN_WERK = [{ value: 'rose', label: 'Rosé' }, { value: 'sand', label: 'Sand' }, { value: 'blaugrau', label: 'Blaugrau' }];
    var ANKER = { name: 'anker', label: 'Sprungmarke (z. B. „angebote“; Knöpfe springen mit #angebote hierher)', typ: 'text' };
    var HINWEIS_TEXT = 'Im Text: **fett**, *kursiv*, [Linktext](/pfad oder https://…)';

    // Feldschema je Element. typ: text | textarea | link | produkt | produkte | kategorie | medium | medien | auswahl | schalter | liste
    var ELEMENTE = {
        schaufenster: {
            label: 'Schaufenster',
            beschreibung: 'Große Kachel in Magenta mit Produkt und Video, dazu zwei Nebenkacheln',
            felder: [
                { name: 'dachzeile', label: 'Dachzeile', typ: 'text' },
                { name: 'titel', label: 'Überschrift (einzige Hauptüberschrift der Seite)', typ: 'text' },
                { name: 'text', label: 'Text', typ: 'textarea' },
                { name: 'knopfText', label: 'Hauptknopf: Text', typ: 'text' },
                { name: 'knopfLink', label: 'Hauptknopf: Link (ohne Video)', typ: 'link' },
                { name: 'video', label: 'Hauptknopf öffnet Video (MP4 aus den Medien)', typ: 'medium', akzeptiert: 'video/mp4' },
                { name: 'videoPoster', label: 'Video: Vorschaubild', typ: 'medium', akzeptiert: 'image/*' },
                { name: 'videoUntertitel', label: 'Video: Untertitel (WebVTT, .vtt)', typ: 'medium', akzeptiert: 'text/vtt,.vtt' },
                { name: 'nebenLinkText', label: 'Nebenlink: Text', typ: 'text' },
                { name: 'nebenLink', label: 'Nebenlink: Link', typ: 'link' },
                { name: 'produkt', label: 'Produkt (Cover, Name, Preis)', typ: 'produkt' },
                { name: 'preisPraefix', label: 'Vor dem Preis, z. B. „ab“ (leer: nur der Preis)', typ: 'text' },
                {
                    name: 'kacheln', label: 'Nebenkacheln', typ: 'liste', max: 2, eintrag: 'Kachel',
                    felder: [
                        { name: 'dachzeile', label: 'Dachzeile', typ: 'text' },
                        { name: 'titel', label: 'Titel', typ: 'text' },
                        { name: 'text', label: 'Text', typ: 'textarea' },
                        { name: 'linkText', label: 'Linktext', typ: 'text' },
                        { name: 'link', label: 'Link', typ: 'link' },
                        { name: 'flaeche', label: 'Fläche', typ: 'auswahl', optionen: FLAECHEN },
                        { name: 'bild', label: 'Bild (statt Produktcover)', typ: 'medium', akzeptiert: 'image/*' },
                        { name: 'produkte', label: 'Produktcover (eins oder bis zu drei als Stapel)', typ: 'produkte', max: 3 }
                    ]
                }
            ]
        },
        kategorien: {
            label: 'Kategorien mit Cover-Stapel',
            beschreibung: 'Bis zu sechs Kategorien, je mit drei Covern',
            felder: [
                { name: 'ueberschrift', label: 'Überschrift', typ: 'text' },
                { name: 'linkText', label: 'Link rechts: Text', typ: 'text' },
                { name: 'link', label: 'Link rechts: Ziel', typ: 'link' },
                {
                    name: 'eintraege', label: 'Kategorien', typ: 'liste', max: 6, eintrag: 'Kategorie',
                    felder: [
                        { name: 'kategorie', label: 'Kategorie', typ: 'kategorie' },
                        { name: 'titel', label: 'Titel (leer: Name der Kategorie)', typ: 'text' },
                        { name: 'untertitel', label: 'Untertitel', typ: 'text' },
                        { name: 'bilder', label: 'Bilder (leer: die ersten drei Produktbilder)', typ: 'medien', max: 3 }
                    ]
                }
            ]
        },
        werke: {
            label: 'Werke',
            beschreibung: 'Redaktionelle Zeilen mit Cover, Text, Formaten und Preis',
            felder: [
                { name: 'ueberschrift', label: 'Überschrift', typ: 'text' },
                { name: 'text', label: 'Einleitung', typ: 'textarea' },
                {
                    name: 'eintraege', label: 'Werke', typ: 'liste', max: 8, eintrag: 'Werk',
                    felder: [
                        { name: 'produkt', label: 'Produkt (Cover und Preis; der Preis kommt genau aus diesem Produkt)', typ: 'produkt' },
                        { name: 'bild', label: 'Coverbild (statt Produktcover)', typ: 'medium', akzeptiert: 'image/*' },
                        { name: 'oberzeile', label: 'Zeile über dem Titel (z. B. Herausgeber)', typ: 'text' },
                        { name: 'titel', label: 'Titel', typ: 'text' },
                        { name: 'text', label: 'Text', typ: 'textarea' },
                        { name: 'formate', label: 'Formatzeile, z. B. „Online · Print · im Abo“', typ: 'text' },
                        { name: 'preisPraefix', label: 'Vor dem Preis, z. B. „ab“ (leer: nur der Preis)', typ: 'text' },
                        { name: 'linkText', label: 'Linktext', typ: 'text' },
                        { name: 'link', label: 'Link', typ: 'link' }
                    ]
                }
            ]
        },
        hinweis: {
            label: 'Hinweis mit Zeichen',
            beschreibung: 'Großes Zeichen, Dachzeile, Haupttext, Unterzeile, Text und Links',
            felder: [
                { name: 'zeichen', label: 'Zeichen', typ: 'text' },
                { name: 'groesse', label: 'Größe', typ: 'auswahl', optionen: [{ value: 'gross', label: 'Groß (Startseite)' }, { value: 'normal', label: 'Normal (Werkseiten)' }] },
                { name: 'dachzeile', label: 'Dachzeile', typ: 'text' },
                { name: 'haupttext', label: 'Haupttext (*Wörter zwischen Sternchen* werden kursiv)', typ: 'textarea' },
                { name: 'unterzeile', label: 'Unterzeile', typ: 'text' },
                { name: 'text', label: 'Text', typ: 'textarea' },
                { name: 'linksVorspann', label: 'Text vor den Links, z. B. „Muster-Formular für den Dienstgeber:“', typ: 'text' },
                {
                    name: 'links', label: 'Links', typ: 'liste', max: 4, eintrag: 'Link',
                    felder: [
                        { name: 'text', label: 'Text', typ: 'text' },
                        { name: 'link', label: 'Ziel', typ: 'link' },
                        { name: 'datei', label: 'oder Datei aus den Medien (geht vor)', typ: 'medium', akzeptiert: '*/*' }
                    ]
                }
            ]
        },
        beitraege: {
            label: 'Drei Beiträge',
            beschreibung: 'Überschrift und Beiträge auf Sand',
            felder: [
                { name: 'ueberschrift', label: 'Überschrift', typ: 'text' },
                {
                    name: 'eintraege', label: 'Beiträge', typ: 'liste', max: 3, eintrag: 'Beitrag',
                    felder: [
                        { name: 'dachzeile', label: 'Dachzeile', typ: 'text' },
                        { name: 'titel', label: 'Titel', typ: 'text' },
                        { name: 'text', label: 'Text', typ: 'textarea' },
                        { name: 'link', label: 'Link', typ: 'link' }
                    ]
                }
            ]
        },
        liste: {
            label: 'Nummerierte Liste mit Einleitung',
            beschreibung: 'Dachzeile, Überschrift, Text und bis zu acht Einträge',
            felder: [
                { name: 'dachzeile', label: 'Dachzeile', typ: 'text' },
                { name: 'ueberschrift', label: 'Überschrift', typ: 'text' },
                { name: 'text', label: 'Text', typ: 'textarea' },
                {
                    name: 'eintraege', label: 'Einträge', typ: 'liste', max: 8, eintrag: 'Eintrag',
                    felder: [
                        { name: 'text', label: 'Text', typ: 'text' },
                        { name: 'link', label: 'Link', typ: 'link' }
                    ]
                }
            ]
        },
        werkkopf: {
            label: 'Werk-Kopf',
            beschreibung: 'Brotkrumen, großes Cover vor einem Kreis, Dachzeile, Titel, Untertitel, Text, Knöpfe und Zeile für Bestandskunden',
            felder: [
                { name: 'produkt', label: 'Produkt für das Cover', typ: 'produkt' },
                { name: 'bild', label: 'Coverbild (statt Produktcover)', typ: 'medium', akzeptiert: 'image/*' },
                { name: 'flaeche', label: 'Kreis hinter dem Cover', typ: 'auswahl', optionen: FLAECHEN_WERK },
                { name: 'dachzeile', label: 'Dachzeile (z. B. Herausgeber)', typ: 'text' },
                { name: 'titel', label: 'Titel (einzige Hauptüberschrift der Seite)', typ: 'text' },
                { name: 'untertitel', label: 'Untertitel (kursiv)', typ: 'text' },
                { name: 'text', label: 'Text (Absätze durch Leerzeile trennen)', typ: 'textarea' },
                { name: 'zusatz', label: 'Zusatzzeile (' + HINWEIS_TEXT + ')', typ: 'textarea' },
                { name: 'angaben', label: 'Angaben, z. B. Schriftleitung oder Auflage, ISBN', typ: 'text' },
                { name: 'knopfText', label: 'Hauptknopf: Text', typ: 'text' },
                { name: 'knopfLink', label: 'Hauptknopf: Ziel (z. B. #angebote)', typ: 'link' },
                { name: 'nebenLinkText', label: 'Nebenlink: Text', typ: 'text' },
                { name: 'nebenLink', label: 'Nebenlink: Ziel (z. B. #leseproben)', typ: 'link' },
                { name: 'bestandText', label: 'Bestandskunden: Text, z. B. „Sie haben schon einen Zugang?“', typ: 'text' },
                { name: 'bestandLinkText', label: 'Bestandskunden: Linktext', typ: 'text' },
                { name: 'bestandLink', label: 'Bestandskunden: Ziel (leer: KETTELER-Bibliothek)', typ: 'link' }
            ]
        },
        angebote: {
            label: 'Angebote',
            beschreibung: 'Ein bis drei Kaufkarten mit Produkten aus dem Katalog, darunter Hinweiszeilen (Mehrplatz, Zusatzangebote)',
            felder: [
                ANKER,
                { name: 'ueberschrift', label: 'Überschrift', typ: 'text' },
                { name: 'bestellnummer', label: 'Bestell-Nr. auf den Karten zeigen', typ: 'schalter' },
                { name: 'werk', label: 'Werk (für die Mehrplatz-Anfrage)', typ: 'auswahl', optionen: [{ value: '', label: 'keins' }, { value: 'avr', label: 'Die AVR von A bis Z' }, { value: 'zmv', label: 'ZMV' }, { value: 'eichstaetter', label: 'Eichstätter Kommentar' }] },
                {
                    name: 'karten', label: 'Kaufkarten', typ: 'liste', max: 3, eintrag: 'Karte',
                    felder: [
                        { name: 'produkt', label: 'Produkt (Bestell-Nr., Preis, Warenkorb)', typ: 'produkt' },
                        { name: 'titel', label: 'Titel (leer: Name des Produkts)', typ: 'text' },
                        { name: 'zeile', label: 'Kurze Zeile unter dem Titel', typ: 'text' },
                        { name: 'hervorgehoben', label: 'Hervorheben (Rahmen und Knopf in Magenta, mobil zuerst)', typ: 'schalter' }
                    ]
                },
                { name: 'seitentitel', label: 'Text neben einer einzelnen Karte: erste Zeile (groß)', typ: 'text' },
                { name: 'seitentext', label: 'Text neben einer einzelnen Karte: weiterer Text', typ: 'textarea' },
                {
                    name: 'zeilen', label: 'Hinweiszeilen', typ: 'liste', max: 6, eintrag: 'Zeile',
                    felder: [
                        { name: 'text', label: 'Text links (' + HINWEIS_TEXT + '; {preis} = Preis des Produkts)', typ: 'text' },
                        { name: 'zusatz', label: 'Kleiner Zusatz, z. B. „(gilt nicht für …)“', typ: 'text' },
                        { name: 'art', label: 'Rechts', typ: 'auswahl', optionen: [{ value: 'link', label: 'Link' }, { value: 'mehrplatz', label: 'Mehrplatz-Anfrage (mit Werk)' }, { value: 'kaufen', label: 'In den Warenkorb (Produkt)' }] },
                        { name: 'linkText', label: 'Linktext', typ: 'text' },
                        { name: 'link', label: 'Ziel (bei „Link“)', typ: 'link' },
                        { name: 'produkt', label: 'Produkt (bei „In den Warenkorb“)', typ: 'produkt' }
                    ]
                },
                { name: 'fussText', label: 'Kleiner Text darunter (' + HINWEIS_TEXT + ')', typ: 'textarea' }
            ]
        },
        vorteile: {
            label: 'Vorteile / Liste',
            beschreibung: 'Überschrift und Einleitung links, rechts zweispaltige Einträge (Titel und Text, eine Zeile oder kursiver Anfangsbuchstabe), dazu Aufzählungen',
            felder: [
                ANKER,
                { name: 'abstand', label: 'Abstand oben', typ: 'auswahl', optionen: [{ value: 'gross', label: 'Groß' }, { value: 'klein', label: 'Klein (direkt nach einem Hinweis)' }] },
                { name: 'ueberschrift', label: 'Überschrift', typ: 'text' },
                { name: 'einleitung', label: 'Einleitung (Absätze durch Leerzeile trennen)', typ: 'textarea' },
                { name: 'hinweis', label: 'Weiterer Text, kleiner und heller', typ: 'textarea' },
                { name: 'darstellung', label: 'Einträge', typ: 'auswahl', optionen: [{ value: 'titel', label: 'Titel und Text, zwei Spalten' }, { value: 'zeile', label: 'Eine Zeile, zwei Spalten' }, { value: 'buchstabe', label: 'Anfangsbuchstabe kursiv, eine Spalte' }] },
                {
                    name: 'eintraege', label: 'Einträge', typ: 'liste', max: 12, eintrag: 'Eintrag',
                    felder: [
                        { name: 'titel', label: 'Titel (bei „Anfangsbuchstabe“: der Buchstabe)', typ: 'text' },
                        { name: 'text', label: 'Text', typ: 'textarea' }
                    ]
                },
                {
                    name: 'aufzaehlungen', label: 'Aufzählungen darunter', typ: 'liste', max: 3, eintrag: 'Aufzählung',
                    felder: [
                        { name: 'titel', label: 'Titel, z. B. „Plus:“', typ: 'text' },
                        { name: 'punkte', label: 'Punkte (einer je Zeile)', typ: 'textarea' }
                    ]
                }
            ]
        },
        leseproben: {
            label: 'Leseproben',
            beschreibung: 'Überschrift, Text, optional Kasten und Knopf, rechts PDF-Dateien aus den Medien mit Kürzel',
            felder: [
                ANKER,
                { name: 'flaeche', label: 'Fläche', typ: 'auswahl', optionen: FLAECHEN_WERK },
                { name: 'ueberschrift', label: 'Überschrift', typ: 'text' },
                { name: 'text', label: 'Text', typ: 'textarea' },
                { name: 'knopfText', label: 'Knopf: Text (z. B. „Lesemuster (PDF)“)', typ: 'text' },
                { name: 'knopfDatei', label: 'Knopf: Datei', typ: 'medium', akzeptiert: 'application/pdf' },
                { name: 'kastenDachzeile', label: 'Kasten: Dachzeile', typ: 'text' },
                { name: 'kastenTitel', label: 'Kasten: Titel', typ: 'text' },
                { name: 'kastenPunkte', label: 'Kasten: Punkte (einer je Zeile)', typ: 'textarea' },
                {
                    name: 'dateien', label: 'Dateien', typ: 'liste', max: 30, eintrag: 'Datei',
                    felder: [
                        { name: 'kuerzel', label: 'Kürzel, z. B. „A 11“ oder Jahr (optional)', typ: 'text' },
                        { name: 'titel', label: 'Titel', typ: 'text' },
                        { name: 'datei', label: 'PDF aus den Medien', typ: 'medium', akzeptiert: 'application/pdf' },
                        { name: 'link', label: 'oder Link (z. B. Bericht auf einer anderen Seite)', typ: 'link' },
                        { name: 'art', label: 'Angabe rechts (leer: „PDF“, z. B. „extern“)', typ: 'text' }
                    ]
                }
            ]
        },
        video: {
            label: 'Video mit Person',
            beschreibung: 'Selbst gehostetes Video (öffnet im Dialog), daneben Dachzeile, Titel, Text und Person mit Foto und Links',
            felder: [
                { name: 'video', label: 'Video (MP4 aus den Medien)', typ: 'medium', akzeptiert: 'video/mp4' },
                { name: 'videoPoster', label: 'Vorschaubild', typ: 'medium', akzeptiert: 'image/*' },
                { name: 'videoUntertitel', label: 'Untertitel (WebVTT, .vtt)', typ: 'medium', akzeptiert: 'text/vtt,.vtt' },
                { name: 'dachzeile', label: 'Dachzeile', typ: 'text' },
                { name: 'titel', label: 'Titel', typ: 'text' },
                { name: 'text', label: 'Text', typ: 'textarea' },
                { name: 'foto', label: 'Person: Foto', typ: 'medium', akzeptiert: 'image/*' },
                { name: 'personTitel', label: 'Person: erste Zeile', typ: 'text' },
                { name: 'personName', label: 'Person: Name', typ: 'text' },
                {
                    name: 'links', label: 'Person: Links', typ: 'liste', max: 3, eintrag: 'Link',
                    felder: [
                        { name: 'text', label: 'Text', typ: 'text' },
                        { name: 'link', label: 'Ziel', typ: 'link' },
                        { name: 'datei', label: 'oder Datei aus den Medien (geht vor)', typ: 'medium', akzeptiert: '*/*' }
                    ]
                }
            ]
        },
        einzelhefte: {
            label: 'Einzelhefte mit Jahrgangsauswahl',
            beschreibung: 'Hefte einer Kategorie nach Jahrgang (Reiter ohne Neuladen), darunter der Kasten Archiv mit Preiszeilen',
            felder: [
                ANKER,
                { name: 'ueberschrift', label: 'Überschrift', typ: 'text' },
                { name: 'text', label: 'Text', typ: 'textarea' },
                { name: 'heftKategorie', label: 'Kategorie mit den Einzelheften (Jahrgang und Heft aus der Artikelnummer, z. B. Z5-2026, oder aus den Zusatzfeldern)', typ: 'kategorie' },
                { name: 'jahrgaenge', label: 'Anzahl Jahrgänge als Reiter (Standard 5)', typ: 'text' },
                { name: 'onlineText', label: 'Zeile darunter: Text', typ: 'text' },
                { name: 'onlineLinkText', label: 'Zeile darunter: Linktext', typ: 'text' },
                { name: 'onlineLink', label: 'Zeile darunter: Ziel', typ: 'link' },
                { name: 'archivTitel', label: 'Archiv: Titel (leer: kein Kasten)', typ: 'text' },
                { name: 'archivText', label: 'Archiv: Text', typ: 'text' },
                { name: 'archivLinkText', label: 'Archiv: Linktext', typ: 'text' },
                { name: 'archivLink', label: 'Archiv: Ziel', typ: 'link' },
                {
                    name: 'preiszeilen', label: 'Archiv: Preiszeilen', typ: 'liste', max: 10, eintrag: 'Preiszeile',
                    felder: [
                        { name: 'text', label: 'Text', typ: 'text' },
                        { name: 'preis', label: 'Preis (Text, z. B. „je 17,50 €“)', typ: 'text' }
                    ]
                }
            ]
        },
        ausgabe: {
            label: 'Aktuelle Ausgabe',
            beschreibung: 'Dachzeile, Titel, Untertitel, Inhalt als Tabelle, Links, daneben Kasten (z. B. Probeheft) mit zwei Knöpfen',
            felder: [
                ANKER,
                { name: 'flaeche', label: 'Fläche', typ: 'auswahl', optionen: FLAECHEN_WERK },
                { name: 'hauptueberschrift', label: 'Titel ist die Hauptüberschrift der Seite (eigene Seite „ZMV aktuell“)', typ: 'schalter' },
                { name: 'dachzeile', label: 'Dachzeile', typ: 'text' },
                { name: 'titel', label: 'Titel', typ: 'text' },
                { name: 'untertitel', label: 'Untertitel', typ: 'text' },
                {
                    name: 'zeilen', label: 'Inhalt', typ: 'liste', max: 8, eintrag: 'Rubrik',
                    felder: [
                        { name: 'rubrik', label: 'Rubrik', typ: 'text' },
                        { name: 'inhalt', label: 'Inhalt (ein Beitrag je Zeile)', typ: 'textarea' }
                    ]
                },
                {
                    name: 'links', label: 'Links', typ: 'liste', max: 4, eintrag: 'Link',
                    felder: [
                        { name: 'text', label: 'Text', typ: 'text' },
                        { name: 'link', label: 'Ziel', typ: 'link' },
                        { name: 'datei', label: 'oder Datei aus den Medien (geht vor)', typ: 'medium', akzeptiert: '*/*' }
                    ]
                },
                { name: 'kastenTitel', label: 'Kasten: Titel (leer: kein Kasten)', typ: 'text' },
                { name: 'kastenText', label: 'Kasten: Text', typ: 'text' },
                {
                    name: 'knoepfe', label: 'Kasten: Knöpfe (der erste in Magenta)', typ: 'liste', max: 2, eintrag: 'Knopf',
                    felder: [
                        { name: 'text', label: 'Text', typ: 'text' },
                        { name: 'link', label: 'Ziel', typ: 'link' },
                        { name: 'datei', label: 'oder Datei aus den Medien (geht vor)', typ: 'medium', akzeptiert: '*/*' }
                    ]
                },
                { name: 'kastenFussText', label: 'Kasten: Zeile darunter, Text (z. B. „Leseprobe:“)', typ: 'text' },
                { name: 'kastenFussLinkText', label: 'Kasten: Zeile darunter, Linktext', typ: 'text' },
                { name: 'kastenFussDatei', label: 'Kasten: Zeile darunter, Datei', typ: 'medium', akzeptiert: '*/*' }
            ]
        },
        fakten: {
            label: 'Fakten',
            beschreibung: 'Überschrift links, rechts eine Liste Begriff und Wert',
            felder: [
                ANKER,
                { name: 'ueberschrift', label: 'Überschrift', typ: 'text' },
                {
                    name: 'eintraege', label: 'Einträge', typ: 'liste', max: 12, eintrag: 'Eintrag',
                    felder: [
                        { name: 'begriff', label: 'Begriff', typ: 'text' },
                        { name: 'wert', label: 'Wert', typ: 'textarea' }
                    ]
                }
            ]
        },
        rueckmeldung: {
            label: 'Rückmeldung',
            beschreibung: 'Überschrift links, rechts Text und Knopf (z. B. E-Mail)',
            felder: [
                { name: 'ueberschrift', label: 'Überschrift', typ: 'text' },
                { name: 'text', label: 'Text', typ: 'text' },
                { name: 'knopfText', label: 'Knopf: Text', typ: 'text' },
                { name: 'knopfLink', label: 'Knopf: Ziel (z. B. mailto:kontakt@ketteler-verlag.de)', typ: 'link' }
            ]
        },
        unternavigation: {
            label: 'Unternavigation',
            beschreibung: 'Brotkrumen und die Unterseiten eines Bereichs (z. B. MAV Einstieg) als Reiterzeile, aktive Seite markiert',
            felder: [
                { name: 'kategorie', label: 'Bereich (seine Unterkategorien werden gezeigt; Beschriftung: Zusatzfeld „Kurzname“ oder Name)', typ: 'kategorie' },
                { name: 'uebersicht', label: 'Unterseite, die auf der Übersicht des Bereichs markiert ist (steht vorn)', typ: 'kategorie' }
            ]
        },
        seitenkopf: {
            label: 'Seitenkopf',
            beschreibung: 'Optional Brotkrumen, dann Hauptüberschrift und Unterzeile',
            felder: [
                { name: 'brotkrumen', label: 'Brotkrumen zeigen', typ: 'schalter' },
                { name: 'titel', label: 'Titel (einzige Hauptüberschrift der Seite)', typ: 'text' },
                { name: 'unterzeile', label: 'Unterzeile', typ: 'text' }
            ]
        },
        begruessung: {
            label: 'Begrüßungsbrief',
            beschreibung: 'Anrede als Hauptüberschrift, Brieftext und Gruß, daneben ein Kasten mit Knöpfen (z. B. Probeheft)',
            felder: [
                { name: 'titel', label: 'Anrede (einzige Hauptüberschrift der Seite)', typ: 'text' },
                { name: 'text', label: 'Brieftext', typ: 'textarea' },
                { name: 'gruss', label: 'Gruß (kursiv)', typ: 'textarea' },
                { name: 'kastenDachzeile', label: 'Kasten: Dachzeile', typ: 'text' },
                { name: 'kastenTitel', label: 'Kasten: Titel', typ: 'text' },
                {
                    name: 'knoepfe', label: 'Kasten: Knöpfe (der erste in Magenta)', typ: 'liste', max: 2, eintrag: 'Knopf',
                    felder: [
                        { name: 'text', label: 'Text', typ: 'text' },
                        { name: 'link', label: 'Ziel', typ: 'link' },
                        { name: 'datei', label: 'oder Datei aus den Medien (geht vor)', typ: 'medium', akzeptiert: '*/*' }
                    ]
                }
            ]
        },
        bereichskacheln: {
            label: 'Bereichskacheln',
            beschreibung: 'Überschrift und bis zu vier Kacheln mit Nummer, Titel, Text und drei Covern, darunter eine Zeile mit Links',
            felder: [
                { name: 'ueberschrift', label: 'Überschrift', typ: 'text' },
                {
                    name: 'kacheln', label: 'Kacheln', typ: 'liste', max: 4, eintrag: 'Kachel',
                    felder: [
                        { name: 'nummer', label: 'Nummer', typ: 'text' },
                        { name: 'titel', label: 'Titel', typ: 'text' },
                        { name: 'text', label: 'Text (z. B. die Titel des Bereichs)', typ: 'text' },
                        { name: 'link', label: 'Ziel', typ: 'link' },
                        { name: 'produkte', label: 'Cover (bis zu drei Produkte)', typ: 'produkte', max: 3 }
                    ]
                },
                { name: 'zeileText', label: 'Zeile darunter: Text (' + HINWEIS_TEXT + ')', typ: 'text' },
                {
                    name: 'zeileLinks', label: 'Zeile darunter: Links', typ: 'liste', max: 3, eintrag: 'Link',
                    felder: [
                        { name: 'text', label: 'Text', typ: 'text' },
                        { name: 'link', label: 'Ziel', typ: 'link' },
                        { name: 'datei', label: 'oder Datei aus den Medien (geht vor)', typ: 'medium', akzeptiert: '*/*' }
                    ]
                }
            ]
        },
        artikelliste: {
            label: 'Artikelliste',
            beschreibung: 'Überschrift und Text links, rechts eine Liste (Autor, Titel mit Beschreibung, Angabe rechts), z. B. Artikel zum Download oder Links',
            felder: [
                ANKER,
                { name: 'ueberschrift', label: 'Überschrift (leer: Liste über die ganze Breite)', typ: 'text' },
                { name: 'text', label: 'Text', typ: 'textarea' },
                {
                    name: 'eintraege', label: 'Einträge', typ: 'liste', max: 40, eintrag: 'Eintrag',
                    felder: [
                        { name: 'vorspalte', label: 'Vorne, z. B. Autor', typ: 'text' },
                        { name: 'titel', label: 'Titel', typ: 'text' },
                        { name: 'beschreibung', label: 'Beschreibung darunter', typ: 'text' },
                        { name: 'nachspalte', label: 'Rechts, z. B. „ZMV 02/2024“', typ: 'text' },
                        { name: 'link', label: 'Ziel', typ: 'link' },
                        { name: 'datei', label: 'oder Datei aus den Medien (geht vor)', typ: 'medium', akzeptiert: '*/*' }
                    ]
                },
                { name: 'fussLinkText', label: 'Link darunter: Text', typ: 'text' },
                { name: 'fussLink', label: 'Link darunter: Ziel', typ: 'link' }
            ]
        },
        titelkarten: {
            label: 'Titelkarten',
            beschreibung: 'Bis zu drei Karten mit Cover, Herausgeber, Titel, Zeilen mit großem Anfangsbuchstaben, Leseproben, Preis und Link',
            felder: [
                {
                    name: 'karten', label: 'Karten', typ: 'liste', max: 3, eintrag: 'Karte',
                    felder: [
                        { name: 'produkt', label: 'Produkt (Cover und Preis)', typ: 'produkt' },
                        { name: 'flaeche', label: 'Fläche hinter dem Cover', typ: 'auswahl', optionen: FLAECHEN_WERK },
                        { name: 'hrsg', label: 'Herausgeber/Autor', typ: 'text' },
                        { name: 'titel', label: 'Titel', typ: 'text' },
                        { name: 'zeilen', label: 'Zeilen (eine je Zeile; der erste Buchstabe wird groß gesetzt)', typ: 'textarea' },
                        { name: 'leseprobenTitel', label: 'Überschrift der Leseproben (leer: „Leseproben“)', typ: 'text' },
                        {
                            name: 'leseproben', label: 'Leseproben und Links', typ: 'liste', max: 5, eintrag: 'Link',
                            felder: [
                                { name: 'text', label: 'Text', typ: 'text' },
                                { name: 'link', label: 'Ziel', typ: 'link' },
                                { name: 'datei', label: 'oder Datei aus den Medien (geht vor)', typ: 'medium', akzeptiert: '*/*' }
                            ]
                        },
                        { name: 'preisPraefix', label: 'Vor dem Preis, z. B. „ab“', typ: 'text' },
                        { name: 'linkText', label: 'Knopf: Text (z. B. „Zum Werk“)', typ: 'text' },
                        { name: 'link', label: 'Knopf: Ziel', typ: 'link' }
                    ]
                }
            ]
        },
        veranstaltung: {
            label: 'Veranstaltung',
            beschreibung: 'Große Fläche in Magenta mit Dachzeile, Titel, Text, Datum und Ort, darunter Zeilen mit Links und ein Text',
            felder: [
                { name: 'dachzeile', label: 'Dachzeile', typ: 'text' },
                { name: 'titel', label: 'Titel (einzige Hauptüberschrift der Seite)', typ: 'text' },
                { name: 'text', label: 'Text', typ: 'textarea' },
                { name: 'datum', label: 'Datum', typ: 'text' },
                { name: 'ort', label: 'Ort', typ: 'text' },
                {
                    name: 'zeilen', label: 'Zeilen (z. B. Programm, Anmeldung)', typ: 'liste', max: 4, eintrag: 'Zeile',
                    felder: [
                        { name: 'text', label: 'Text (' + HINWEIS_TEXT + ')', typ: 'text' },
                        { name: 'linkText', label: 'Linktext', typ: 'text' },
                        { name: 'link', label: 'Ziel', typ: 'link' }
                    ]
                },
                { name: 'seitentext', label: 'Text neben den Zeilen', typ: 'textarea' }
            ]
        },
        interviewliste: {
            label: 'Interviewliste',
            beschreibung: 'Interviews untereinander: Foto, Ort und Datum, Name, Rolle, Text, Link und Buch aus dem Katalog',
            felder: [
                {
                    name: 'eintraege', label: 'Interviews', typ: 'liste', max: 20, eintrag: 'Interview',
                    felder: [
                        { name: 'foto', label: 'Foto', typ: 'medium', akzeptiert: 'image/*' },
                        { name: 'ort', label: 'Ort und Datum', typ: 'text' },
                        { name: 'name', label: 'Name', typ: 'text' },
                        { name: 'rolle', label: 'Rolle (kursiv)', typ: 'text' },
                        { name: 'text', label: 'Text', typ: 'textarea' },
                        { name: 'linkText', label: 'Link: Text (z. B. „Zum Interview“)', typ: 'text' },
                        { name: 'link', label: 'Link: Ziel', typ: 'link' },
                        { name: 'datei', label: 'oder Datei aus den Medien (geht vor)', typ: 'medium', akzeptiert: '*/*' },
                        { name: 'produkt', label: 'Buch oder Zeitschrift (Cover)', typ: 'produkt' },
                        { name: 'produktText', label: 'Text unter dem Cover (z. B. „Zum Buch“)', typ: 'text' },
                        { name: 'produktLink', label: 'Ziel des Covers (leer: Seite des Produkts)', typ: 'link' }
                    ]
                }
            ]
        },
        produktreihe: {
            label: 'Produktreihe',
            beschreibung: 'Produktkarten mit Cover, Autor, Preis und „In den Warenkorb“ bzw. „Zum Angebot“',
            felder: [
                { name: 'ueberschrift', label: 'Überschrift', typ: 'text' },
                { name: 'linkText', label: 'Link rechts: Text', typ: 'text' },
                { name: 'link', label: 'Link rechts: Ziel', typ: 'link' },
                { name: 'produkte', label: 'Produkte (Reihenfolge wie gewählt)', typ: 'produkte', max: 10 }
            ]
        }
    };

    function leer(feld) {
        if (feld.typ === 'liste' || feld.typ === 'produkte' || feld.typ === 'medien') {
            return [];
        }
        if (feld.typ === 'schalter') {
            return false;
        }
        if (feld.typ === 'auswahl') {
            return feld.optionen[0].value;
        }
        return null;
    }

    function eintragLeer(liste) {
        var e = {};
        liste.felder.forEach(function (f) { e[f.name] = leer(f); });
        return e;
    }

    // Feld für einen Link: Kategorie, Produkt oder Adresse (auch „bibliothek“ für die KETTELER-Bibliothek); als Zeichenkette
    Shopware.Component.register('ketteler-link-feld', {
        template: '' +
            '<div class="ketteler-link-feld">' +
            '  <sw-single-select :label="label" :options="arten" :value="art" @update:value="artWechseln" />' +
            '  <sw-entity-single-select v-if="art === \'kategorie\'" entity="category" :value="id" @update:value="setzen(\'kategorie\', $event)" />' +
            '  <sw-entity-single-select v-else-if="art === \'produkt\'" entity="product" :value="id" @update:value="setzen(\'produkt\', $event)" />' +
            '  <sw-text-field v-else placeholder="/pfad oder https://…" :value="adresse" @update:value="$emit(\'update:value\', $event)" />' +
            '</div>',
        props: { value: { type: String, required: false, default: null }, label: { type: String, required: false, default: '' } },
        emits: ['update:value'],
        data: function () {
            return { gewaehlt: null, arten: [{ value: 'kategorie', label: 'Kategorie' }, { value: 'produkt', label: 'Produkt' }, { value: 'adresse', label: 'Adresse' }] };
        },
        computed: {
            art: function () {
                if (this.gewaehlt) { return this.gewaehlt; }
                var m = /^(kategorie|produkt):/.exec(this.value || '');
                return m ? m[1] : (this.value ? 'adresse' : 'kategorie');
            },
            id: function () {
                var m = /^(?:kategorie|produkt):([0-9a-f]{32})$/.exec(this.value || '');
                return m ? m[1] : null;
            },
            adresse: function () {
                return /^(kategorie|produkt):/.test(this.value || '') ? '' : (this.value || '');
            }
        },
        methods: {
            artWechseln: function (art) {
                this.gewaehlt = art;
                this.$emit('update:value', null);
            },
            setzen: function (art, id) {
                this.$emit('update:value', id ? art + ':' + id : null);
            }
        }
    });

    // Ein Feld nach Schema (rekursiv für Listen)
    Shopware.Component.register('ketteler-schema-feld', {
        template: '' +
            '<div class="ketteler-schema-feld" style="margin-bottom:16px">' +
            '  <sw-text-field v-if="feld.typ === \'text\'" :label="feld.label" :value="wert" @update:value="setzen" />' +
            '  <sw-textarea-field v-else-if="feld.typ === \'textarea\'" :label="feld.label" :value="wert" @update:value="setzen" />' +
            '  <ketteler-link-feld v-else-if="feld.typ === \'link\'" :label="feld.label" :value="wert" @update:value="setzen" />' +
            '  <sw-entity-single-select v-else-if="feld.typ === \'produkt\'" :label="feld.label" entity="product" :value="wert" @update:value="setzen" />' +
            '  <sw-entity-multi-id-select v-else-if="feld.typ === \'produkte\'" :label="feld.label" :repository="produktRepo" :ids="wert || []" @update:ids="setzen" />' +
            '  <sw-entity-single-select v-else-if="feld.typ === \'kategorie\'" :label="feld.label" entity="category" :value="wert" @update:value="setzen" />' +
            '  <sw-single-select v-else-if="feld.typ === \'auswahl\'" :label="feld.label" :options="feld.optionen" :value="wert" @update:value="setzen" />' +
            '  <sw-switch-field v-else-if="feld.typ === \'schalter\'" :label="feld.label" :value="wert" @update:value="setzen" />' +
            '  <div v-else-if="feld.typ === \'medium\'">' +
            '    <sw-media-field :label="feld.label" :value="wert" :fileAccept="feld.akzeptiert || \'*/*\'" @update:value="setzen" />' +
            '  </div>' +
            '  <div v-else-if="feld.typ === \'medien\'">' +
            '    <label class="sw-field__label">{{ feld.label }}</label>' +
            '    <div v-for="i in feld.max" :key="i" style="margin-bottom:8px">' +
            '      <sw-media-field :value="(wert || [])[i - 1] || null" fileAccept="image/*" @update:value="medienSetzen(i - 1, $event)" />' +
            '    </div>' +
            '  </div>' +
            '  <div v-else-if="feld.typ === \'liste\'" class="ketteler-schema-liste">' +
            '    <h4 style="margin:8px 0">{{ feld.label }}</h4>' +
            '    <sw-card v-for="(eintrag, i) in (wert || [])" :key="i" :title="feld.eintrag + \' \' + (i + 1)" position-identifier="ketteler-schema-liste">' +
            '      <ketteler-schema-feld v-for="unter in feld.felder" :key="unter.name" :feld="unter" :wert="eintrag[unter.name]" @update:wert="eintragSetzen(i, unter.name, $event)" />' +
            '      <sw-button size="small" @click="verschieben(i, -1)" :disabled="i === 0">Nach oben</sw-button>' +
            '      <sw-button size="small" @click="entfernen(i)">Entfernen</sw-button>' +
            '    </sw-card>' +
            '    <sw-button v-if="(wert || []).length < feld.max" size="small" variant="primary" @click="hinzufuegen">{{ feld.eintrag }} hinzufügen</sw-button>' +
            '  </div>' +
            '</div>',
        inject: ['repositoryFactory'],
        props: { feld: { type: Object, required: true }, wert: { required: false, default: null } },
        emits: ['update:wert'],
        computed: {
            produktRepo: function () { return this.repositoryFactory.create('product'); }
        },
        methods: {
            setzen: function (v) { this.$emit('update:wert', v === undefined ? null : v); },
            medienSetzen: function (i, id) {
                var liste = (this.wert || []).slice();
                liste[i] = id || null;
                this.$emit('update:wert', liste.filter(function (x) { return !!x; }));
            },
            eintragSetzen: function (i, name, v) {
                var liste = (this.wert || []).map(function (e) { return Object.assign({}, e); });
                liste[i][name] = v;
                this.$emit('update:wert', liste);
            },
            hinzufuegen: function () { this.$emit('update:wert', (this.wert || []).concat([eintragLeer(this.feld)])); },
            entfernen: function (i) { var l = (this.wert || []).slice(); l.splice(i, 1); this.$emit('update:wert', l); },
            verschieben: function (i, d) {
                var l = (this.wert || []).slice(); var e = l.splice(i, 1)[0]; l.splice(i + d, 0, e); this.$emit('update:wert', l);
            }
        }
    });

    Object.keys(ELEMENTE).forEach(function (kurz) {
        var def = ELEMENTE[kurz];
        var name = 'ketteler-' + kurz;
        var defaultConfig = {};
        def.felder.forEach(function (f) { defaultConfig[f.name] = { source: 'static', value: leer(f) }; });

        // Element im Editor: kurze Zusammenfassung statt Nachbau der Gestaltung
        Shopware.Component.register('sw-cms-el-' + name, {
            template: '' +
                '<div class="ketteler-cms-el" style="padding:18px 20px;border:1px dashed #d9cdbd;border-radius:8px;background:#fbf8f3;color:#2a2326">' +
                '  <div style="font-size:12px;color:#6b5f63">' + def.label + '</div>' +
                '  <div style="font-family:Georgia,serif;font-size:20px;margin-top:4px">{{ titel }}</div>' +
                '  <div v-if="anzahl !== null" style="font-size:13px;color:#6b5f63;margin-top:4px">{{ anzahl }}</div>' +
                '</div>',
            mixins: [Shopware.Mixin.getByName('cms-element')],
            computed: {
                titel: function () {
                    var c = this.element.config || {};
                    var t = (c.titel && c.titel.value) || (c.ueberschrift && c.ueberschrift.value) || (c.haupttext && c.haupttext.value) || '';
                    return t || '(noch ohne Text)';
                },
                anzahl: function () {
                    var c = this.element.config || {};
                    var l = ['eintraege', 'kacheln', 'produkte', 'karten', 'dateien', 'zeilen', 'knoepfe'].map(function (f) { return c[f] && c[f].value; }).filter(function (v) { return Array.isArray(v) && v.length; })[0];
                    return Array.isArray(l) ? l.length + ' Einträge' : null;
                }
            },
            created: function () { this.initElementConfig(name); }
        });

        // Einstellungen: Felder nach Schema
        Shopware.Component.register('sw-cms-el-config-' + name, {
            template: '' +
                '<div class="ketteler-cms-el-config">' +
                '  <p style="margin-bottom:16px;color:#6b5f63">' + def.beschreibung + '</p>' +
                '  <ketteler-schema-feld v-for="feld in felder" :key="feld.name" :feld="feld" :wert="element.config[feld.name].value" @update:wert="setzen(feld.name, $event)" />' +
                '</div>',
            mixins: [Shopware.Mixin.getByName('cms-element')],
            data: function () { return { felder: def.felder }; },
            created: function () { this.initElementConfig(name); },
            methods: {
                setzen: function (feld, wert) {
                    this.element.config[feld].value = wert;
                    this.$emit('element-update', this.element);
                }
            }
        });

        Shopware.Component.register('sw-cms-el-preview-' + name, {
            template: '<div style="padding:12px;font-size:13px;color:#2a2326">' + def.label + '</div>'
        });

        Shopware.Service('cmsService').registerCmsElement({
            name: name,
            label: def.label,
            component: 'sw-cms-el-' + name,
            configComponent: 'sw-cms-el-config-' + name,
            previewComponent: 'sw-cms-el-preview-' + name,
            defaultConfig: defaultConfig
        });

        // Block mit genau diesem Element
        Shopware.Component.register('sw-cms-block-' + name, {
            template: '<div class="sw-cms-block-' + name + '"><slot name="inhalt"></slot></div>'
        });
        Shopware.Component.register('sw-cms-preview-' + name, {
            template: '' +
                '<div style="padding:14px 16px;background:#fbf8f3;border:1px solid #e8e0d4;border-radius:6px">' +
                '  <div style="font-family:Georgia,serif;font-size:15px;color:#2a2326">' + def.label + '</div>' +
                '  <div style="font-size:12px;color:#6b5f63;margin-top:2px">' + def.beschreibung + '</div>' +
                '</div>'
        });
        Shopware.Service('cmsService').registerCmsBlock({
            name: name,
            label: 'KETTELER: ' + def.label,
            category: 'text-image',
            component: 'sw-cms-block-' + name,
            previewComponent: 'sw-cms-preview-' + name,
            defaultConfig: { marginBottom: '0', marginTop: '0', marginLeft: '0', marginRight: '0', sizingMode: 'full_width' },
            slots: { inhalt: name }
        });
    });
})();
