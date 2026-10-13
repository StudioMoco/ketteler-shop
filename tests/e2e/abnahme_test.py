"""Abnahme-Ablauf Ende zu Ende (Gesamtauftrag, Abschnitt E): Startseite, Werkseite über den Link der Startseite, Kaufen
aus der Angebotskarte, Kasse auf Rechnung, Bestellbestätigung in Mailpit. Vier Fälle, je eine Bestellung (höchstens
fünf Testbestellungen insgesamt):
  1. Gast, Print (AVR Print)                       Deutschland, 7 %, mit Versand
  2. Privatkundin DE, digital (AVR Digital, Abo)    7 %, ohne Versand, Lizenz angelegt
  3. Privatkunde AT, digital (Eichstätter Online)   Steuer für Österreich nach dem OSS-Schalter (aus: deutsche 7 %)
  4. Einrichtung AT mit USt-IdNr. (ZMV Abo Print)   steuerfrei (Steuerschuldnerschaft des Empfängers)
Die Bestellnummern stehen am Ende der Ausgabe (für den Bericht). Aufruf: python3 tests/e2e/abnahme_test.py
(KETTELER_ZIEL=staging für Staging).
"""

import re
import sys
import time
import urllib.parse

from hilfen import SHOP, Browser, Ergebnis, sql, zitadel_anmelden
from lizenz_test import MAILPIT, PASSWORT, artikel_id, stand, warte
from shop_test import mail_text, mails

VERSION = "0fa91ce3e96a4bc2be4bd9ce752c3425"


def zur_werkseite(e, b, name, pfad):
    """Von der Startseite über den Link im Block „Werke“ (bzw. Menü) auf die Werkseite."""
    b.get(SHOP + "/")
    links = re.findall(r'href="([^"]*%s)"' % re.escape(pfad), b.html)
    e.pruefe(bool(links), name + ": Startseite verlinkt die Werkseite " + pfad)
    b.get(urllib.parse.urljoin(SHOP + "/", links[0] if links else pfad))
    e.pruefe(b.status == 200 and 'data-kt="angebote"' in b.html, name + ": Werkseite mit Angeboten")


def aus_karte_kaufen(e, b, name, nummer):
    """Formular „In den Warenkorb“ genau der Karte mit dieser Nummer abschicken (wie der Knopf, ohne Seitenwechsel)."""
    karte = re.search(r'data-kt="angebot" data-nummer="%s".*?</form>' % re.escape(nummer), b.html, re.S)
    e.pruefe(karte is not None and artikel_id(nummer) in karte.group(0), name + ": Karte mit " + nummer + " und Knopf")
    felder = dict(re.findall(r'name="([^"]+)" value="([^"]*)"', karte.group(0))) if karte else {}
    b.post(SHOP + "/checkout/line-item/add", felder, kopf={"X-Requested-With": "XMLHttpRequest"})
    b.get(SHOP + "/checkout/cart")
    e.pruefe(nummer in b.html, name + ": " + nummer + " im Warenkorb")


def warenkorb_leeren(b):
    b.get(SHOP + "/checkout/cart")
    for f in b.formulare():
        if "/checkout/line-item/delete/" in f["action"]:
            b.post(f["action"], f["felder"], kopf={"X-Requested-With": "XMLHttpRequest"})


def bestellen(e, b, name, felder):
    b.get(SHOP + "/checkout/confirm")
    e.pruefe(b.formular("/checkout/order") is not None and "Rechnung" in b.html, name + ": Kasse mit Zahlart Rechnung")
    b.absenden("/checkout/order", {"tos": "on", **felder})
    e.pruefe("/checkout/finish" in b.url, name + ": Bestellung abgeschlossen", b.url)
    if "orderId=" not in b.url:
        return None, None
    oid = b.url.split("orderId=")[1].split("&")[0]
    zeile = sql("SELECT o.order_number, o.amount_total, o.amount_net, JSON_EXTRACT(o.price, '$.taxStatus'), pm.technical_name, "
                "(SELECT JSON_EXTRACT(d.shipping_costs, '$.totalPrice') FROM order_delivery d WHERE d.order_id=o.id AND d.version_id=o.version_id LIMIT 1) "
                "FROM `order` o JOIN order_transaction t ON t.order_id=o.id AND t.version_id=o.version_id JOIN payment_method pm ON pm.id=t.payment_method_id "
                "WHERE o.id=UNHEX('%s') AND o.version_id=UNHEX('%s')" % (oid, VERSION))[0]
    e.pruefe(zeile[4] == "payment_invoicepayment", name + ": auf Rechnung")
    return oid, zeile


def bestaetigung(e, name, email, nummer, enthaelt=None):
    gefunden = warte(lambda: mails('to:%s subject:"%s"' % (email, nummer)), 40)
    e.pruefe(bool(gefunden), name + ": Bestellbestätigung in Mailpit")
    if gefunden:
        text, html = mail_text(gefunden[0])
        e.pruefe(nummer in text and "Guten Tag" in text and "data:image/png;base64," in html, name + ": Bestätigung mit Nummer, Sie-Form, neuem Kopf")
        if enthaelt:
            e.pruefe(enthaelt in text, name + ": Bestätigung nennt „" + enthaelt + "“")


def steuersaetze(oid):
    return sorted({float(z[0]) for z in sql("SELECT JSON_EXTRACT(price, '$.calculatedTaxes[0].taxRate') FROM order_line_item WHERE order_id=UNHEX('%s') "
                                              "AND version_id=UNHEX('%s') AND type='product'" % (oid, VERSION)) if z[0] not in (None, "NULL")})


def angemeldet(email):
    b = Browser()
    b.get(SHOP + "/account/oidc/anmelden")
    zitadel_anmelden(b, email, PASSWORT)
    return b


def main():
    e = Ergebnis()
    nummern = []

    name = "1 Gast Print"
    print(name)
    for (kid,) in sql("SELECT LOWER(HEX(id)) FROM customer WHERE email='abnahme-gast@ketteler.test'"):
        pass  # Gastkonten bleiben; jede Bestellung legt ein neues Gastkonto an
    b = Browser()
    zur_werkseite(e, b, name, "/Kompendium-AVR-Caritas/")
    aus_karte_kaufen(e, b, name, "TEST-P-AVR")
    land = sql("SELECT LOWER(HEX(id)) FROM country WHERE iso='DE'")[0][0]
    anrede = sql("SELECT LOWER(HEX(id)) FROM salutation WHERE salutation_key='not_specified'")[0][0]
    b.get(SHOP + "/checkout/register")
    b.absenden("/account/register", {"salutationId": anrede, "firstName": "Gerda", "lastName": "Gast", "email": "abnahme-gast@ketteler.test",
                                     "guest": "1", "billingAddress[street]": "Teststraße 5", "billingAddress[zipcode]": "50670", "billingAddress[city]": "Köln",
                                     "billingAddress[countryId]": land, "accountType": "private"})
    oid, z = bestellen(e, b, name, {})
    if oid:
        nummern.append((name, z[0]))
        e.pruefe(steuersaetze(oid) == [7.0] and float(z[5] or 0) > 0, name + ": 7 % und Versandkosten", str((steuersaetze(oid), z[5])))
        bestaetigung(e, name, "abnahme-gast@ketteler.test", z[0])

    name = "2 Privat DE digital"
    print(name)
    b = angemeldet("privat-de@ketteler.test")
    warenkorb_leeren(b)
    zur_werkseite(e, b, name, "/Kompendium-AVR-Caritas/")
    aus_karte_kaufen(e, b, name, "TEST-O-AVR")
    oid, z = bestellen(e, b, name, {"ketteler_widerruf": "1"})
    if oid:
        nummern.append((name, z[0]))
        e.pruefe(steuersaetze(oid) == [7.0] and float(z[5] or 0) == 0, name + ": 7 %, ohne Versandkosten", str((steuersaetze(oid), z[5])))
        bestaetigung(e, name, "privat-de@ketteler.test", z[0])
        lizenz = warte(lambda: (stand(oid).get("TEST-O-AVR") or {}).get("status") == "angelegt" and stand(oid), 90)
        e.pruefe(bool(lizenz), name + ": Lizenz über die Attrappe angelegt (sichtbar an der Bestellung)", str(stand(oid))[:200])

    name = "3 Privat AT digital"
    print(name)
    oss = sql("SELECT configuration_value FROM system_config WHERE configuration_key='KettelerSteuern.config.ossAktiv'")
    oss_an = bool(oss) and "true" in oss[0][0]
    b = angemeldet("privat-at@ketteler.test")
    warenkorb_leeren(b)
    zur_werkseite(e, b, name, "/Eichstaetter-Kommentar/")
    aus_karte_kaufen(e, b, name, "TEST-O-EK")
    oid, z = bestellen(e, b, name, {"ketteler_widerruf": "1"})
    if oid:
        nummern.append((name, z[0]))
        erwartet = [10.0] if oss_an else [7.0]
        e.pruefe(steuersaetze(oid) == erwartet, name + ": Steuersatz %s (OSS %s)" % (erwartet, "an" if oss_an else "aus, deutsche Umsatzsteuer"), str(steuersaetze(oid)))
        bestaetigung(e, name, "privat-at@ketteler.test", z[0])

    name = "4 Einrichtung AT mit USt-IdNr."
    print(name)
    b = angemeldet("einrichtung-at@ketteler.test")
    warenkorb_leeren(b)
    zur_werkseite(e, b, name, "/Zeitschrift-ZMV/")
    aus_karte_kaufen(e, b, name, "TEST-ABO-ZMV-P")
    oid, z = bestellen(e, b, name, {})
    if oid:
        nummern.append((name, z[0]))
        e.pruefe('"tax-free"' in (z[3] or "") and abs(float(z[1]) - float(z[2])) < 0.01, name + ": steuerfrei (Steuerschuldnerschaft des Empfängers)", str(z))
        bestaetigung(e, name, "einrichtung-at@ketteler.test", z[0], "Steuerschuldnerschaft des Empfängers")

    print("\nTestbestellungen dieses Laufs: " + ", ".join("%s (%s)" % (n, f) for f, n in nummern))
    return e.ende()


if __name__ == "__main__":
    sys.exit(main())
