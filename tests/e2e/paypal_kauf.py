"""PayPal-Testkauf von Anfang bis Ende (Sandbox), in zwei Schritten mit einer Freigabe durch einen Menschen dazwischen.

  python3 tests/e2e/paypal_kauf.py start            Testkundin meldet sich an (ZITADEL), Online-Artikel TEST-O-EK in den
                                                    Warenkorb, Zahlart PayPal, bestellen. Gibt die Bestellnummer und den
                                                    Freigabe-Link der PayPal-Sandbox aus.
  (Mensch: Link öffnen, mit dem Sandbox-Käuferkonto anmelden, Zahlung freigeben. Zugangsdaten nie in Dateien oder Chats.)
  python3 tests/e2e/paypal_kauf.py pruefen <nr>     Zahlstatus, Bestellbestätigung, Mails, Lizenz an der Attrappe, PayPal-
                                                    Vorgang direkt bei der Sandbox (auf dem Server abgefragt), Rückerstattung.

Mit KETTELER_ZIEL=staging gegen Staging. Die Freigabe bei PayPal ist eine Anmeldung mit Passwort und bleibt deshalb Handarbeit.
"""

import json
import subprocess
import sys
import urllib.parse

from hilfen import SERVER, SHOP, STAGING, Ergebnis, json_anfrage, sql
from lizenz_test import MAILPIT, angemeldeter_kunde, artikel_id, lizenz_zu, warte

LIVE = "0fa91ce3e96a4bc2be4bd9ce752c3425"


def start():
    b = angemeldeter_kunde()
    b.get(SHOP + "/checkout/cart")
    for f in b.formulare():
        if "/checkout/line-item/delete/" in f["action"]:
            b.post(f["action"], f["felder"], kopf={"X-Requested-With": "XMLHttpRequest"})
    pid = artikel_id("TEST-O-EK")
    b.post(SHOP + "/checkout/line-item/add", {
        "lineItems[%s][id]" % pid: pid, "lineItems[%s][type]" % pid: "product", "lineItems[%s][referencedId]" % pid: pid,
        "lineItems[%s][quantity]" % pid: "1"}, kopf={"X-Requested-With": "XMLHttpRequest"})
    paypal = sql("SELECT LOWER(HEX(id)) FROM payment_method WHERE technical_name='swag_paypal_paypal'")[0][0]
    b.post(SHOP + "/checkout/configure", {"paymentMethodId": paypal})
    b.get(SHOP + "/checkout/confirm")
    f = b.formular("/checkout/order")
    assert f is not None, "Bestellformular fehlt"
    daten = dict(f["felder"])
    daten.update({"tos": "on", "ketteler_widerruf": "1"})
    # Weiterleitungen nur innerhalb des Shops folgen; bei PayPal anhalten
    b.anfrage("POST", urllib.parse.urljoin(b.url, f["action"]), daten=daten, folgen=False)
    for _ in range(10):
        host = urllib.parse.urlparse(b.url).hostname or ""
        if host != urllib.parse.urlparse(SHOP).hostname:
            break
        b.anfrage("GET", b.url, folgen=False)
    host = urllib.parse.urlparse(b.url).hostname or ""
    zeile = sql("SELECT o.order_number FROM `order` o JOIN order_customer oc ON oc.order_id=o.id AND oc.order_version_id=o.version_id "
                "WHERE oc.email='testkunde@ketteler.test' AND o.version_id=UNHEX('%s') ORDER BY o.created_at DESC LIMIT 1" % LIVE)
    print("Bestellnummer:", zeile[0][0] if zeile else "?")
    print("Weiter zu:", host)
    if host != "www.sandbox.paypal.com":
        print("ACHTUNG: nicht die PayPal-Sandbox, nichts freigeben!")
        return 1
    print("Freigabe-Link (Sandbox):", b.url)
    return 0


def sandbox_vorgang(paypal_order_id):
    """PayPal-Vorgang direkt bei der Sandbox abfragen (auf dem Server, mit dessen Zugangsdaten; nichts davon wird ausgegeben)."""
    skript = r'''
import base64, json, sys, urllib.request
w = {}
for z in open("/opt/ketteler-shop/.env"):
    if "=" in z and not z.startswith("#"):
        k, v = z.rstrip("\n").split("=", 1); w[k] = v
basis = "https://api-m.sandbox.paypal.com"
auth = base64.b64encode((w["PAYPAL_SANDBOX_CLIENT_ID"] + ":" + w["PAYPAL_SANDBOX_CLIENT_SECRET"]).encode()).decode()
t = json.load(urllib.request.urlopen(urllib.request.Request(basis + "/v1/oauth2/token", data=b"grant_type=client_credentials",
    headers={"Authorization": "Basic " + auth})))["access_token"]
o = json.load(urllib.request.urlopen(urllib.request.Request(basis + "/v2/checkout/orders/" + sys.argv[1], headers={"Authorization": "Bearer " + t})))
cap = (o.get("purchase_units") or [{}])[0].get("payments", {})
print(json.dumps({"basis": basis, "status": o.get("status"), "betrag": (o.get("purchase_units") or [{}])[0].get("amount"),
                  "captures": [{"status": c.get("status"), "betrag": c.get("amount")} for c in cap.get("captures", [])],
                  "refunds": [{"status": r.get("status"), "betrag": r.get("amount")} for r in cap.get("refunds", [])]}))
'''
    if STAGING:
        aufruf = ["ssh", SERVER, "python3 - %s" % paypal_order_id]
    else:
        return None
    aus = subprocess.run(aufruf, input=skript, capture_output=True, text=True)
    return json.loads(aus.stdout) if aus.returncode == 0 and aus.stdout.strip() else {"fehler": aus.stderr.strip()[-300:]}


def pruefen(nr):
    e = Ergebnis()
    o = sql("SELECT LOWER(HEX(id)) FROM `order` WHERE order_number='%s' AND version_id=UNHEX('%s')" % (nr, LIVE))
    assert o, "Bestellung %s nicht gefunden" % nr
    oid = o[0][0]
    t = sql("SELECT s.technical_name, IFNULL(JSON_EXTRACT(t.custom_fields,'$.swag_paypal_order_id'),'null'), pm.technical_name "
            "FROM order_transaction t JOIN state_machine_state s ON s.id=t.state_id JOIN payment_method pm ON pm.id=t.payment_method_id "
            "WHERE t.order_id=UNHEX('%s') AND t.version_id=UNHEX('%s') ORDER BY t.created_at DESC LIMIT 1" % (oid, LIVE))[0]
    zustand, pp_id, zahlart = t[0], json.loads(t[1]), t[2]
    print("Zahlart %s, Zahlstatus %s, PayPal-Vorgang %s" % (zahlart, zustand, pp_id))
    e.pruefe(zahlart == "swag_paypal_paypal", "Zahlart PayPal")
    e.pruefe(zustand in ("paid", "refunded", "refunded_partially"), "Zahlstatus bezahlt (bzw. danach erstattet)", zustand)

    b = angemeldeter_kunde()
    b.get(SHOP + "/checkout/finish?orderId=" + oid)
    e.pruefe(b.status == 200 and nr in b.html, "Bestellbestätigung im Shop mit Bestellnummer", "%s %s" % (b.status, b.url))
    b.get(SHOP + "/account/order")
    e.pruefe(nr in b.html, "Bestellung unter „Meine Bestellungen“")

    _, treffer = json_anfrage("GET", MAILPIT + "/api/v1/search?query=" + urllib.parse.quote('subject:"Ihre Bestellung %s"' % nr))
    mails = treffer.get("messages", []) if isinstance(treffer, dict) else []
    e.pruefe(len(mails) >= 1, "Bestellmail an die Kundin (Mailpit)", str(len(mails)))
    if mails:
        _, m = json_anfrage("GET", MAILPIT + "/api/v1/message/" + mails[0]["ID"])
        text = m.get("Text", "")
        e.pruefe("PayPal" in text and "89,90" in text, "Bestellmail nennt PayPal und den Betrag")

    lizenzen = warte(lambda: lizenz_zu(nr), 20) or []
    e.pruefe(len(lizenzen) == 1, "Lizenz an der Attrappe angelegt", str(lizenzen)[:200])
    if lizenzen:
        print("Lizenz: Status %s, Produkt %s, E-Mail %s" % (lizenzen[0].get("status"), lizenzen[0].get("produkt"), lizenzen[0].get("email")))

    if pp_id:
        v = sandbox_vorgang(pp_id)
        print("Sandbox:", json.dumps(v, ensure_ascii=False))
        if v is not None:
            e.pruefe(v.get("basis") == "https://api-m.sandbox.paypal.com" and v.get("status") == "COMPLETED",
                     "Vorgang existiert in der PayPal-Sandbox (api-m.sandbox.paypal.com), abgeschlossen")
            if zustand.startswith("refunded"):
                e.pruefe(any(r["status"] == "COMPLETED" for r in v.get("refunds", [])), "Rückerstattung in der Sandbox abgeschlossen")
            else:
                e.ueberspringe("Rückerstattung", "noch nicht ausgelöst (Verwaltung > Bestellung > Reiter PayPal)")
    return e.ende()


if __name__ == "__main__":
    if len(sys.argv) >= 2 and sys.argv[1] == "start":
        sys.exit(start())
    if len(sys.argv) >= 3 and sys.argv[1] == "pruefen":
        sys.exit(pruefen(sys.argv[2]))
    print(__doc__)
    sys.exit(2)
