"""End-to-End-Test der EU-Widerrufsfunktion (Shopware ab 6.6.10.14, ergänzt durch KettelerAnmeldung).

Prüft: Knopf im Fuß, Formular für angemeldete Kunden (mit Positionen) und Gäste, Eingangsbestätigung an den Kunden,
Mail an den Empfänger aus KETTELER_WIDERRUF_EMAIL mit Zuordnung, Speicherung an der Bestellung (Zeitpunkt, Positionen,
Kennzeichen), Hinweis bei Online-Zugang mit Zustimmung, keine automatische Lizenzbeendigung, falsche E-Mail.
"""

import json
import re
import sys
import time
import urllib.parse

from hilfen import ENV, SHOP, Browser, Ergebnis, interne_vermerke, json_anfrage, sql
from lizenz_test import MAILPIT, angemeldeter_kunde, attrappe, bestellen, lizenz_zu, warte
from anmeldung_test import gast_registrieren, in_den_warenkorb

GEDANKENSTRICH = re.compile("[–—]| - ")
LIVE = "0fa91ce3e96a4bc2be4bd9ce752c3425"


def mails(suche):
    _, antwort = json_anfrage("GET", MAILPIT + "/api/v1/search?query=" + urllib.parse.quote(suche))
    return antwort.get("messages", []) if isinstance(antwort, dict) else []


def text_von(nachricht):
    _, m = json_anfrage("GET", MAILPIT + "/api/v1/message/" + nachricht["ID"])
    return m.get("Text", "")


def formular_senden(b, seite_url, felder):
    b.get(seite_url)
    f = b.formular("/form/revocation/request")
    assert f is not None, "Widerrufsformular fehlt auf " + seite_url
    daten = dict(f["felder"])
    daten.update(felder)
    positionen = daten.pop("kettelerPositionen[]", None)
    werte = list(daten.items()) + [("kettelerPositionen[]", p) for p in (positionen or [])]
    b.anfrage("POST", urllib.parse.urljoin(seite_url, f["action"]), daten=werte, kopf={"X-Requested-With": "XMLHttpRequest"})
    return b.html


def berlin_stunde():
    from datetime import datetime
    from zoneinfo import ZoneInfo
    return datetime.now(ZoneInfo("Europe/Berlin")).hour


def pruefe_bestaetigung(e, name, nachricht, erwartet):
    """Eingangsbestätigung: Inhalt der Erklärung, Datum und Uhrzeit (deutsche Zeit), keine internen Vermerke."""
    t = text_von(nachricht)
    _, voll = json_anfrage("GET", MAILPIT + "/api/v1/message/" + nachricht["ID"])
    zeit = re.search(r"Eingegangen am: \d{1,2}\. \w+ \d{4} um (\d{1,2}):(\d{2})", t)
    e.pruefe(all(w in t for w in erwartet), name + ": Inhalt der Erklärung vollständig", ", ".join(w for w in erwartet if w not in t))
    e.pruefe(zeit is not None and int(zeit.group(1)) in (berlin_stunde(), (berlin_stunde() - 1) % 24), name + ": Datum und Uhrzeit (deutsche Zeit)", zeit.group(0) if zeit else t[:200])
    e.pruefe(not interne_vermerke(t + voll.get("HTML", "") + nachricht.get("Subject", "")) and not GEDANKENSTRICH.search(t), name + ": keine internen Vermerke, ohne Gedankenstrich")


def widerrufe(order_id):
    zeile = sql("SELECT IFNULL(JSON_EXTRACT(custom_fields,'$.ketteler_widerrufe'),'null'), "
                "IFNULL(JSON_UNQUOTE(JSON_EXTRACT(custom_fields,'$.ketteler_widerruf_eingang')),'') FROM `order` "
                "WHERE id=UNHEX('%s') AND version_id=UNHEX('%s')" % (order_id, LIVE))[0]
    return json.loads(zeile[0]) or [], zeile[1]


def hat_kennzeichen(order_id):
    return sql("SELECT COUNT(*) FROM order_tag ot JOIN tag t ON t.id=ot.tag_id WHERE ot.order_id=UNHEX('%s') "
               "AND ot.order_version_id=UNHEX('%s') AND t.name='Widerruf eingegangen'" % (order_id, LIVE))[0][0] == "1"


def main():
    e = Ergebnis()
    attrappe("/_zuruecksetzen", {})
    json_anfrage("DELETE", MAILPIT + "/api/v1/messages")
    empfaenger = ENV["KETTELER_WIDERRUF_EMAIL"]

    print("Knopf und Seite")
    b = Browser()
    b.get(SHOP + "/")
    knopf = re.search(r'footer-revocation-button[^<]*<a[^>]*href="([^"]+)"', b.html)
    e.pruefe(knopf is not None, "Knopf „Vertrag widerrufen“ im Fuß")
    seite = knopf.group(1) if knopf else SHOP + "/Rechtliches/Widerruf-erklaeren/"
    b.get(seite)
    e.pruefe(b.formular("/form/revocation/request") is not None and 'data-kt="widerruf-hinweis-gast"' in b.html, "Formular für Gäste erreichbar")

    print("Angemeldete Kundin widerruft eine Position")
    k = angemeldeter_kunde()
    oid, nr, _ = bestellen(k, "TEST-P-001", "TEST-O-EK")
    e.pruefe(len(warte(lambda: lizenz_zu(nr))) == 1, "Lizenz zur Bestellung angelegt")
    k.get(SHOP + "/account/order")
    e.pruefe('data-kt="widerruf-link"' in k.html and ("bestellung=" + nr) in k.html, "„Widerruf erklären“ in „Meine Bestellungen“")
    k.get(seite + "?bestellung=" + nr)
    e.pruefe('data-kt="widerruf-positionen"' in k.html and 'value="%s"' % nr in k.html, "Bestellnummer vorbelegt, Positionen wählbar")
    print_pos = sql("SELECT LOWER(HEX(id)) FROM order_line_item WHERE order_id=UNHEX('%s') AND version_id=UNHEX('%s') "
                    "AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.productNumber'))='TEST-P-001'" % (oid, LIVE))[0][0]
    antwort = formular_senden(k, seite + "?bestellung=" + nr, {
        "firstName": "Tessa", "lastName": "Testkundin", "email": "testkunde@ketteler.test", "contractNumber": nr,
        "comment": "Das Buch brauche ich doch nicht.", "kettelerPositionen[]": [print_pos]})
    e.pruefe("Ihr Widerruf ist bei uns eingegangen" in antwort, "Bestätigung auf der Seite", antwort[:200])
    liste, text = warte(lambda: widerrufe(oid)[0] and widerrufe(oid), 20) or ([], "")
    e.pruefe(len(liste) == 1 and [p["nummer"] for p in liste[0]["positionen"]] == ["TEST-P-001"] and not liste[0]["alle"],
             "an der Bestellung: Zeitpunkt und gewählte Position", json.dumps(liste, ensure_ascii=False)[:300])
    e.pruefe("Uhr: Widerruf für einzelne Positionen: TEST-P-001" in text, "lesbarer Eintrag für die Verwaltung (Zusatzfeld)")
    e.pruefe(hat_kennzeichen(oid), "Kennzeichen „Widerruf eingegangen“ an der Bestellung")
    kunde = warte(lambda: mails('to:testkunde@ketteler.test subject:"Wir haben Ihren Widerruf erhalten"'), 20)
    e.pruefe(len(kunde) == 1, "Eingangsbestätigung an die Kundin")
    if kunde:
        pruefe_bestaetigung(e, "Bestätigung", kunde[0], [nr, "Tessa Testkundin", "testkunde@ketteler.test",
                            "Widerruf für: Musterbuch Arbeitsrecht (Print) (TEST-P-001)", "Das Buch brauche ich doch nicht.", "09972 94 14-51"])
    verwaltung = warte(lambda: mails('to:%s subject:"Widerruf eingegangen: %s"' % (empfaenger, nr)), 20)
    e.pruefe(len(verwaltung) == 1 and "Zugeordnet zu Bestellung " + nr in text_von(verwaltung[0]), "Mail an den Empfänger aus der Einstellung, mit Zuordnung")
    e.pruefe((lizenz_zu(nr) or [{}])[0].get("status") == "aktiv", "Lizenz bleibt bestehen (keine automatische Beendigung)")

    print("Widerruf der Online-Position mit Zustimmung zum sofortigen Beginn")
    json_anfrage("DELETE", MAILPIT + "/api/v1/messages")
    formular_senden(k, seite + "?bestellung=" + nr, {
        "firstName": "Tessa", "lastName": "Testkundin", "email": "testkunde@ketteler.test", "contractNumber": nr, "comment": ""})
    verwaltung = warte(lambda: mails('to:%s subject:"Widerruf eingegangen: %s"' % (empfaenger, nr)), 20)
    e.pruefe(len(verwaltung) == 1 and "Widerrufsrecht kann damit erloschen sein" in text_von(verwaltung[0]),
             "Hinweis für die Verwaltung: Online-Zugang mit Zustimmung")
    liste, _ = widerrufe(oid)
    e.pruefe(len(liste) == 2 and liste[1]["alle"] and liste[1]["online_mit_zustimmung"], "zweiter Eintrag: ganze Bestellung, Online mit Zustimmung")
    e.pruefe((lizenz_zu(nr) or [{}])[0].get("status") == "aktiv", "Lizenz bleibt auch dann bestehen")

    print("Gast mit reinem Print")
    g = Browser()
    in_den_warenkorb(g, "TEST-P-002")
    gast_registrieren(g, "gast-widerruf@ketteler.test")
    g.get(SHOP + "/checkout/confirm")
    g.absenden("/checkout/order", {"tos": "on"})
    e.pruefe("/checkout/finish" in g.url, "Gastbestellung (Print) abgeschlossen", g.url)
    gast_oid = g.url.split("orderId=")[1].split("&")[0] if "orderId=" in g.url else ""
    gast_nr = sql("SELECT order_number FROM `order` WHERE id=UNHEX('%s') AND version_id=UNHEX('%s')" % (gast_oid, LIVE))[0][0] if gast_oid else ""
    g2 = Browser()
    formular_senden(g2, seite, {"firstName": "Gabi", "lastName": "Gast", "email": "gast-widerruf@ketteler.test",
                               "contractNumber": gast_nr, "comment": "Bitte alles zurücknehmen."})
    liste, _ = warte(lambda: widerrufe(gast_oid)[0] and widerrufe(gast_oid), 20) or ([], "")
    e.pruefe(len(liste) == 1 and liste[0]["alle"], "Gast: an der Bestellung gespeichert (ganze Bestellung)")
    e.pruefe(len(warte(lambda: mails('to:gast-widerruf@ketteler.test subject:"Wir haben Ihren Widerruf erhalten"'), 20)) == 1, "Gast: Eingangsbestätigung")

    print("Nicht zuordenbar: falsche E-Mail-Adresse und unbekannte Nummer (Widerruf trotzdem wirksam)")
    for fall, nummer, email in [("falsche E-Mail", gast_nr, "fremd@ketteler.test"), ("unbekannte Nummer", "ABO-4711-TIPPFEHLER", "abo-kunde@ketteler.test")]:
        json_anfrage("DELETE", MAILPIT + "/api/v1/messages")
        antwort = formular_senden(Browser(), seite, {"firstName": "Fritz", "lastName": "Fremd", "email": email,
                                                     "contractNumber": nummer, "comment": "Ich widerrufe meinen Vertrag."})
        e.pruefe("Ihr Widerruf ist bei uns eingegangen" in antwort, "%s: Bestätigung auf der Seite" % fall)
        verwaltung = warte(lambda: mails('to:%s' % empfaenger), 20)
        betreff = verwaltung[0].get("Subject", "") if verwaltung else ""
        text = text_von(verwaltung[0]) if verwaltung else ""
        e.pruefe(len(verwaltung) == 1 and "(nicht zugeordnet, bitte von Hand prüfen)" in betreff, "%s: Verwaltung, Betreff „nicht zugeordnet, bitte von Hand prüfen“" % fall, betreff)
        e.pruefe(all(w in text for w in ["NICHT ZUGEORDNET, BITTE VON HAND PRÜFEN", "Fritz Fremd", email, nummer, "Ich widerrufe meinen Vertrag.", "Eingegangen am"]),
                 "%s: Verwaltung bekommt die Erklärung vollständig" % fall)
        kunde = warte(lambda: mails('to:%s subject:"Wir haben Ihren Widerruf erhalten"' % email), 20)
        e.pruefe(len(kunde) == 1, "%s: Eingangsbestätigung an den Kunden" % fall)
        if kunde:
            pruefe_bestaetigung(e, fall, kunde[0], [nummer, "Fritz Fremd", email, "Ich widerrufe meinen Vertrag.",
                                                   "Widerruf für: die gesamte Bestellung bzw. die in Ihrer Nachricht genannten Artikel"])
            e.pruefe("NICHT ZUGEORDNET" not in text_von(kunde[0]) and "Bestellnummer gibt es" not in text_von(kunde[0]),
                     "%s: Bestätigung verrät nichts über die Zuordnung" % fall)
    e.pruefe(len(widerrufe(gast_oid)[0]) == 1, "fremde Erklärung nicht an der Gastbestellung gespeichert")
    return e.ende()


if __name__ == "__main__":
    sys.exit(main())
