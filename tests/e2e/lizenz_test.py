"""End-to-End-Test Lizenzen über die lokale Attrappe der Lizenz-Schnittstelle. Aufruf: make test-e2e.

Dauert etwa zwei Minuten, weil die erste Wiederholung nach einem Ausfall planmäßig nach einer Minute kommt.
"""

import hashlib
import hmac
import json
import subprocess
import sys
import time

from hilfen import ATTRAPPE, ENV, SHOP, WURZEL, AdminApi, Browser, Ergebnis, json_anfrage, sql, zitadel_anmelden

PASSWORT = ENV["KETTELER_TESTKUNDE_PASSWORT"]
MAILPIT = "http://localhost:" + ENV.get("MAILPIT_PORT", "8025")


def artikel_id(nummer):
    return hashlib.md5(("ketteler.artikel." + nummer).encode()).hexdigest()


def attrappe(pfad, daten=None):
    return json_anfrage("POST" if daten is not None else "GET", ATTRAPPE + pfad, daten)[1]


def angemeldeter_kunde():
    b = Browser()
    b.get(SHOP + "/account/oidc/anmelden")
    zitadel_anmelden(b, "testkunde@ketteler.test", PASSWORT)
    if "/account/oidc/vervollstaendigen" in b.url:
        b.absenden("/account/oidc/vervollstaendigen", {"kontotyp": "privat", "strasse": "Musterweg 2", "plz": "50670", "ort": "Köln"})
    assert b.url.rstrip("/").endswith("/account"), b.url
    return b


def bestellen(b, *nummern, widerruf=True):
    """Warenkorb leeren, Artikel hinzufügen, auf Rechnung bestellen. Gibt (orderId, Bestellnummer, Seite) zurück.
    widerruf: Häkchen „sofortiger Beginn“ setzen (Pflicht für Privatkunden mit Online-Artikel)."""
    b.get(SHOP + "/checkout/cart")
    for f in b.formulare():
        if "/checkout/line-item/delete/" in f["action"]:
            b.post(f["action"], f["felder"], kopf={"X-Requested-With": "XMLHttpRequest"})
    for n in nummern:
        pid = artikel_id(n)
        b.post(SHOP + "/checkout/line-item/add", {
            "lineItems[%s][id]" % pid: pid, "lineItems[%s][type]" % pid: "product", "lineItems[%s][referencedId]" % pid: pid,
            "lineItems[%s][quantity]" % pid: "1", "lineItems[%s][stackable]" % pid: "1", "lineItems[%s][removable]" % pid: "1"},
            kopf={"X-Requested-With": "XMLHttpRequest"})
    b.get(SHOP + "/checkout/confirm")
    b.absenden("/checkout/order", {"tos": "on", **({"ketteler_widerruf": "1"} if widerruf else {})})
    assert "/checkout/finish" in b.url, "Bestellung nicht abgeschlossen: " + b.url
    order_id = b.url.split("orderId=")[1].split("&")[0]
    nummer = sql("SELECT order_number FROM `order` WHERE id=UNHEX('%s') AND version_id=UNHEX('0fa91ce3e96a4bc2be4bd9ce752c3425')" % order_id)[0][0]
    return order_id, nummer, b


def stand(order_id):
    """Zusatzfeld ketteler_lizenz je Position: {Artikelnummer: dict}"""
    zeilen = sql("SELECT JSON_UNQUOTE(JSON_EXTRACT(payload,'$.productNumber')), IFNULL(JSON_EXTRACT(custom_fields,'$.ketteler_lizenz'),'null') "
                 "FROM order_line_item WHERE order_id=UNHEX('%s') AND version_id=UNHEX('0fa91ce3e96a4bc2be4bd9ce752c3425')" % order_id)
    return {n: json.loads(j) for n, j in zeilen}


def warte(bedingung, sekunden=30):
    ende = time.time() + sekunden
    while time.time() < ende:
        wert = bedingung()
        if wert:
            return wert
        time.sleep(1)
    return bedingung()


def lizenz_zu(bestellnummer):
    return [l for l in attrappe("/_zustand")["lizenzen"] if l["bestellnummer"] == bestellnummer]


def anfragen_zu(bestellnummer):
    return [e for e in attrappe("/_zustand")["ereignisse"].values() if e["daten"].get("bestellnummer") == bestellnummer]


def mails_an(adresse):
    _, antwort = json_anfrage("GET", MAILPIT + "/api/v1/search?query=" + "to:" + adresse)
    return antwort.get("messages", []) if isinstance(antwort, dict) else []


def main():
    e = Ergebnis()
    api = AdminApi()
    attrappe("/_zuruecksetzen", {})
    json_anfrage("DELETE", MAILPIT + "/api/v1/messages")
    b = angemeldeter_kunde()

    print("Online-Kauf auf Rechnung: Lizenz sofort")
    oid, nr, seite = bestellen(b, "TEST-O-EK")
    e.pruefe(nr.startswith("SW"), "Bestellnummer aus dem Shop-Nummernkreis", nr)
    erwartet = ENV["KETTELER_BIBLIOTHEK_URL"].rstrip("/") + "/eichstaetter"
    e.pruefe('data-kt="jetzt-lesen"' in seite.html and 'href="%s"' % erwartet in seite.html, "„Jetzt lesen“ in der Bestellbestätigung")
    lizenzen = warte(lambda: lizenz_zu(nr))
    e.pruefe(len(lizenzen) == 1, "Attrappe hat eine Lizenz angelegt", str(lizenzen))
    ereignis = (anfragen_zu(nr) or [{"daten": {}}])[0]["daten"]
    e.pruefe(ereignis.get("art") == "lizenz_anlegen" and ereignis.get("typ") == "einzel" and ereignis.get("produkt") == "eichstaetter",
             "Art, Typ und Produkt", json.dumps(ereignis, ensure_ascii=False))
    e.pruefe(ereignis.get("email") == "testkunde@ketteler.test" and ereignis.get("name") == "Tessa Testkundin", "E-Mail und Name des Kontos")
    e.pruefe(ereignis.get("ereignis_id", "").startswith("order-" + oid + "-") and ereignis["ereignis_id"].endswith("-lizenz"), "ereignis_id je Position")
    s = warte(lambda: stand(oid)["TEST-O-EK"].get("status") == "angelegt" and stand(oid))
    e.pruefe(bool(s) and s["TEST-O-EK"].get("lizenz_id") == lizenzen[0]["id"], "Stand „angelegt“ mit Lizenz-ID an der Position")
    b.get(SHOP + "/account")
    e.pruefe('data-kt-werk="eichstaetter"' in b.html and erwartet in b.html, "„Jetzt lesen“ im Kundenkonto")

    print("Print-Kauf: keine Lizenz")
    vorher = attrappe("/_zustand")["anfragen"]
    oid_p, nr_p, _ = bestellen(b, "TEST-P-001")
    time.sleep(3)
    e.pruefe(attrappe("/_zustand")["anfragen"] == vorher and stand(oid_p)["TEST-P-001"] is None, "keine Anfrage, kein Lizenzstand")

    print("Bundle: Lizenz für das Online-Werk")
    oid_b, nr_b, _ = bestellen(b, "TEST-B-EK")
    e.pruefe(len(warte(lambda: lizenz_zu(nr_b))) == 1, "Lizenz zum Bundle angelegt")

    print("Doppelte Zustellung derselben Nachricht")
    subprocess.run(["docker", "compose", "exec", "-T", "web", "bin/console", "ketteler:lizenz:erneut", nr, "--erzwingen"],
                   cwd=WURZEL, check=True, capture_output=True)
    s = warte(lambda: stand(oid)["TEST-O-EK"].get("status") == "angelegt" and stand(oid))
    zustand = attrappe("/_zustand")
    e.pruefe(len(lizenz_zu(nr)) == 1, "weiterhin genau eine Lizenz")
    e.pruefe(bool(s) and s["TEST-O-EK"].get("lizenz_id") == lizenzen[0]["id"], "dieselbe Lizenz-ID")
    e.pruefe(sum(1 for p in zustand["protokoll"] if p["daten"].get("bestellnummer") == nr and "angelegt" in p["aktion"]) == 1,
             "Protokoll: nur eine Anlage")

    print("Schnittstelle nicht erreichbar: Wiederholung")
    attrappe("/_modus", {"modus": "fehler503"})
    oid_a, nr_a, _ = bestellen(b, "TEST-O-AVR")
    oid_s, nr_s, _ = bestellen(b, "TEST-O-EK")
    s = warte(lambda: stand(oid_a)["TEST-O-AVR"].get("status") == "wartet" and stand(oid_a))
    e.pruefe(bool(s) and s["TEST-O-AVR"].get("versuche") == 1 and "503" in s["TEST-O-AVR"].get("fehler", ""), "Stand „wartet“ nach dem ersten Versuch")
    e.pruefe(lizenz_zu(nr_a) == [], "noch keine Lizenz")
    # Storno vor der Lizenzvergabe: darf danach keine Lizenz mehr entstehen
    warte(lambda: stand(oid_s)["TEST-O-EK"].get("status") == "wartet")
    status, _ = api("POST", "/_action/order/%s/state/cancel" % oid_s, {})
    e.pruefe(status == 200, "zweite Bestellung storniert (Verwaltung)", str(status))
    attrappe("/_modus", {"modus": "normal"})
    print("  (warte auf die planmäßige Wiederholung nach etwa einer Minute)")
    s = warte(lambda: stand(oid_a)["TEST-O-AVR"].get("status") == "angelegt" and stand(oid_a), 150)
    e.pruefe(bool(s) and len(lizenz_zu(nr_a)) == 1, "nach der Wiederholung angelegt")
    e.pruefe(warte(lambda: stand(oid_s)["TEST-O-EK"].get("status") == "storniert", 90), "Storno vor der Anlage: Stand „storniert“")
    time.sleep(5)
    e.pruefe(lizenz_zu(nr_s) == [], "Storno vor der Anlage: keine Lizenz entstanden")

    print("Schnittstelle lehnt ab: keine Wiederholung, Meldung an die Verwaltung")
    attrappe("/_modus", {"modus": "ablehnen"})
    oid_f, nr_f, _ = bestellen(b, "TEST-O-AVR")
    s = warte(lambda: stand(oid_f)["TEST-O-AVR"].get("status") == "fehler" and stand(oid_f))
    e.pruefe(bool(s) and "422" in s["TEST-O-AVR"].get("fehler", ""), "Stand „fehler“ mit Grund")
    meldungen = warte(lambda: [m for m in mails_an(ENV["KETTELER_VERWALTUNG_EMAIL"]) if nr_f in m.get("Subject", "")], 20)
    e.pruefe(len(meldungen) == 1, "Mail an die Verwaltung", str(len(meldungen)))
    anfragen = attrappe("/_zustand")["anfragen"]
    time.sleep(5)
    e.pruefe(attrappe("/_zustand")["anfragen"] == anfragen, "kein weiterer Versuch")
    attrappe("/_modus", {"modus": "normal"})

    print("Storno im Shop beendet die Lizenz")
    status, _ = api("POST", "/_action/order/%s/state/cancel" % oid, {})
    e.pruefe(status == 200, "Bestellung storniert", str(status))
    s = warte(lambda: stand(oid)["TEST-O-EK"].get("status") == "storniert" and stand(oid))
    lizenz = (lizenz_zu(nr) or [{}])[0]
    e.pruefe(bool(s) and lizenz.get("status") == "beendet", "Lizenz beendet", json.dumps(lizenz))
    storno = [x["daten"] for x in anfragen_zu(nr) if x["daten"].get("art") == "stornieren"]
    e.pruefe(len(storno) == 1 and storno[0]["ereignis_id"].endswith("-storno") and storno[0]["email"] == "testkunde@ketteler.test",
             "Storno-Ereignis mit eigener ereignis_id")
    e.pruefe(any(p["aktion"] == "Shop-Ereignis: Storno" and p["daten"]["bestellnummer"] == nr for p in attrappe("/_zustand")["protokoll"]),
             "Eintrag im Protokoll")
    b.get(SHOP + "/account")
    e.pruefe('data-kt-werk="eichstaetter"' in b.html, "Bundle-Lizenz (Eichstätter) bleibt im Konto")

    print("Signatur: Attrappe weist Fälschungen ab")
    koerper = json.dumps({"ereignis_id": "x", "art": "lizenz_anlegen", "email": "a@b.test", "produkt": "avr", "bestellnummer": "X"}).encode()
    zeit = str(int(time.time()))
    falsch = "sha256=" + hmac.new(b"falsch", zeit.encode() + b"." + koerper, hashlib.sha256).hexdigest()
    import urllib.request, urllib.error
    try:
        urllib.request.urlopen(urllib.request.Request(ATTRAPPE + "/api/shop/ereignisse", data=koerper, method="POST",
                                                      headers={"X-Ketteler-Zeit": zeit, "X-Ketteler-Signatur": falsch}))
        code = 200
    except urllib.error.HTTPError as fehler:
        code = fehler.code
    e.pruefe(code == 401, "falsche Signatur → 401", str(code))
    return e.ende()


if __name__ == "__main__":
    sys.exit(main())
