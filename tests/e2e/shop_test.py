"""End-to-End-Test für Kasse und Konto: Länder, Steuern, Versand nach Gewicht, Zahlarten, Abo, Widerruf-Häkchen,
Mehrplatz-Anfrage, Kundenkonto, Mails (Ton, Kontaktdaten) und Bestellungen aus der Shopware-Verwaltung.
Aufruf: make test-e2e (läuft gegen die lokale Umgebung).
"""

import json
import re
import sys
import time
import urllib.parse

from hilfen import ENV, SHOP, AdminApi, Browser, Ergebnis, interne_vermerke, json_anfrage, sql
from lizenz_test import MAILPIT, angemeldeter_kunde, artikel_id, attrappe, bestellen, lizenz_zu, stand, warte

GEDANKENSTRICH = re.compile("[–—]| - ")


def store_api(token, pfad, daten=None, methode="POST"):
    schluessel = sql("SELECT access_key FROM sales_channel WHERE type_id=UNHEX('8A243080F92E4C719546314B577CF82B')")[0][0]
    kopf = {"sw-access-key": schluessel}
    if token:
        kopf["sw-context-token"] = token
    status, antwort = json_anfrage(methode, SHOP + "/store-api/" + pfad, daten or {}, kopf)
    return antwort


def warenkorb(land, *nummern):
    token = "kt-" + str(time.time())
    store_api(token, "context", {"countryId": sql("SELECT LOWER(HEX(id)) FROM country WHERE iso='%s'" % land)[0][0]}, "PATCH")
    korb = None
    for n in nummern:
        korb = store_api(token, "checkout/cart/line-item", {"items": [{"type": "product", "referencedId": artikel_id(n), "quantity": 1}]})
    verfuegbar = [m["name"] for m in store_api(token, "shipping-method", {"onlyAvailable": True})["elements"]]
    return korb, verfuegbar


def mails(suche):
    _, antwort = json_anfrage("GET", MAILPIT + "/api/v1/search?query=" + urllib.parse.quote(suche))
    return antwort.get("messages", []) if isinstance(antwort, dict) else []


def mail_text(nachricht):
    _, m = json_anfrage("GET", MAILPIT + "/api/v1/message/" + nachricht["ID"])
    return m.get("Text", ""), m.get("HTML", "")


def pruefe_mail(e, name, suche):
    gefunden = warte(lambda: mails(suche), 30)
    e.pruefe(len(gefunden) >= 1, name + ": Mail verschickt", suche)
    if not gefunden:
        return "", ""
    text, html = mail_text(gefunden[0])
    e.pruefe("Guten Tag" in text and "Sie " in text and " du " not in text.lower(), name + ": Sie-Form")
    e.pruefe("09972 94 14-51" in text and "kontakt@ketteler-verlag.de" in text, name + ": Kundenservice im Fuß")
    e.pruefe(not GEDANKENSTRICH.search(text + " " + gefunden[0].get("Subject", "")), name + ": ohne Gedankenstrich")
    e.pruefe(not interne_vermerke(text + html + gefunden[0].get("Subject", "")), name + ": keine internen Vermerke", str(interne_vermerke(text + html)))
    return text, html


def main():
    e = Ergebnis()
    api = AdminApi()
    attrappe("/_zuruecksetzen", {})
    json_anfrage("DELETE", MAILPIT + "/api/v1/messages")

    print("Länder, Steuern, Versand nach Gewicht (Werte sind Platzhalter)")
    korb, verfuegbar = warenkorb("DE", "TEST-P-001")
    e.pruefe(korb["deliveries"][0]["shippingCosts"]["totalPrice"] == 2.5, "Deutschland bis 0,5 kg: 2,50 Euro")
    korb, _ = warenkorb("DE", "TEST-P-001", "TEST-B-EK")
    e.pruefe(korb["deliveries"][0]["shippingCosts"]["totalPrice"] == 5.95, "Deutschland bis 2 kg: 5,95 Euro")
    korb, _ = warenkorb("AT", "TEST-P-001")
    e.pruefe(korb["deliveries"][0]["shippingCosts"]["totalPrice"] == 6.5, "Österreich (EU) bis 0,5 kg: 6,50 Euro")
    e.pruefe([t["taxRate"] for t in korb["price"]["calculatedTaxes"]] == [7], "Print nach Österreich: deutsche 7 %")
    korb, verfuegbar = warenkorb("AT", "TEST-O-EK")
    e.pruefe([t["taxRate"] for t in korb["price"]["calculatedTaxes"]] == [7], "Online-Zugang nach Österreich: deutsche 7 % (OSS aus, Matrix in steuern_test.py)")
    e.pruefe(verfuegbar == ["Ohne Versand (Online-Zugang)"], "Online nur: ohne Versand")
    korb, _ = warenkorb("CH", "TEST-P-001")
    e.pruefe(korb["price"]["taxStatus"] == "tax-free" and korb["deliveries"][0]["shippingCosts"]["totalPrice"] > 0, "Schweiz: ohne Umsatzsteuer, mit Versand")
    korb, _ = warenkorb("GB", "TEST-P-002")
    e.pruefe(korb["price"]["taxStatus"] == "tax-free", "Großbritannien: ohne Umsatzsteuer")
    laender = sql("SELECT COUNT(*) FROM sales_channel_country sc JOIN sales_channel s ON s.id=sc.sales_channel_id WHERE s.type_id=UNHEX('8A243080F92E4C719546314B577CF82B')")[0][0]
    e.pruefe(laender == "29", "29 Lieferländer (EU, Schweiz, Großbritannien)", laender)

    print("Rechtstexte ohne interne Vermerke")
    for pfad in ("/Rechtliches/Impressum/", "/Rechtliches/AGB/", "/Rechtliches/Widerrufsbelehrung/", "/Rechtliches/Datenschutz/", "/Bestellen/Versand-und-Lieferung/"):
        seite = Browser().get(SHOP + pfad)
        e.pruefe(seite.status == 200 and not interne_vermerke(seite.html), "Seite %s ohne „von Ketteler zu prüfen“" % pfad, str(interne_vermerke(seite.html)))

    print("Zahlarten")
    zahlarten = sql("SELECT pm.technical_name FROM sales_channel_payment_method sp JOIN payment_method pm ON pm.id=sp.payment_method_id "
                    "JOIN sales_channel s ON s.id=sp.sales_channel_id WHERE s.type_id=UNHEX('8A243080F92E4C719546314B577CF82B') AND pm.active=1 ORDER BY 1")
    e.pruefe([z[0] for z in zahlarten] == ["payment_invoicepayment", "swag_paypal_paypal"], "Rechnung und PayPal", str(zahlarten))
    sandbox = sql("SELECT configuration_value FROM system_config WHERE configuration_key='SwagPayPal.settings.sandbox'")
    e.pruefe(sandbox and "true" in sandbox[0][0], "PayPal im Testmodus (Sandbox)")

    print("Abo-Kennzeichnung")
    b = Browser()
    b.get(SHOP + "/Zeitschrift-ZMV/ZMV-Print/")
    e.pruefe('kt-abo">Abo<' in b.html, "Kennzeichen „Abo“ in der Kategorie")
    b.get(SHOP + "/detail/" + artikel_id("TEST-ABO-AVR-D"))
    e.pruefe('data-kt="abo-hinweis"' in b.html and "Jahresabonnement" in b.html, "Abo-Hinweis auf der Artikelseite")
    e.pruefe('data-kt="mehrplatz-link"' in b.html, "„Mehrere Lizenzen anfragen“ auf der Artikelseite (Online)")
    b.get(SHOP + "/detail/" + artikel_id("TEST-P-001"))
    e.pruefe('data-kt="mehrplatz-link"' not in b.html, "kein Mehrplatz-Hinweis bei Print")

    print("Mehrplatz-Anfrage")
    m = Browser()
    m.get(SHOP + "/mehrplatz-anfrage?werk=avr&artikel=TEST-O-AVR")
    m.absenden("/mehrplatz-anfrage", {"einrichtung": "", "ansprechperson": "Petra Test", "email": "kaputt", "telefon": "", "anzahl": "1"})
    e.pruefe('data-kt="mehrplatz-fehler"' in m.html and m.html.count("is-invalid") == 3, "Pflichtfelder (Einrichtung, E-Mail) und Anzahl ab 2 geprüft, Telefon freiwillig")
    e.pruefe(re.search(r'name="telefon"[^>]*required', m.html) is None, "Telefon im Formular nicht als Pflichtfeld markiert")
    vorher = sql("SELECT COUNT(*) FROM ketteler_mehrplatz_anfrage")[0][0]
    bestellungen_vorher = sql("SELECT COUNT(*) FROM `order`")[0][0]
    m.absenden("/mehrplatz-anfrage", {"einrichtung": "Caritasverband Teststadt", "ansprechperson": "Petra Test", "email": "petra@ketteler.test",
                                      "telefon": "0221 000000", "anzahl": "12", "nachricht": "Bitte Angebot für 12 Plätze."})
    e.pruefe('data-kt="mehrplatz-gesendet"' in m.html, "Danke-Seite")
    nachher = sql("SELECT COUNT(*) FROM ketteler_mehrplatz_anfrage")[0][0]
    e.pruefe(int(nachher) == int(vorher) + 1, "Anfrage gespeichert")
    e.pruefe(sql("SELECT COUNT(*) FROM `order`")[0][0] == bestellungen_vorher, "keine Bestellung entstanden")
    anfrage_mail = warte(lambda: [x for x in mails("to:" + ENV["KETTELER_KONTAKT_EMAIL"]) if "Caritasverband Teststadt" in x.get("Subject", "")], 20)
    e.pruefe(len(anfrage_mail) == 1, "Mail an den Kundenservice")
    print("Mehrplatz-Anfrage ohne Telefon")
    vorher2 = int(sql("SELECT COUNT(*) FROM ketteler_mehrplatz_anfrage")[0][0])
    m2 = Browser()
    m2.get(SHOP + "/mehrplatz-anfrage?werk=eichstaetter")
    m2.absenden("/mehrplatz-anfrage", {"einrichtung": "Pfarrei Ohne Telefon", "ansprechperson": "Paul Test", "email": "paul@ketteler.test",
                                       "telefon": "", "anzahl": "3", "nachricht": ""})
    e.pruefe('data-kt="mehrplatz-gesendet"' in m2.html and int(sql("SELECT COUNT(*) FROM ketteler_mehrplatz_anfrage")[0][0]) == vorher2 + 1,
             "Anfrage ohne Telefon angenommen und gespeichert")
    ohne_tel = warte(lambda: [x for x in mails("to:" + ENV["KETTELER_KONTAKT_EMAIL"]) if "Pfarrei Ohne Telefon" in x.get("Subject", "")], 20)
    e.pruefe(len(ohne_tel) == 1, "Mail ohne Telefon an den Empfänger aus der Einstellung (KETTELER_KONTAKT_EMAIL)")
    status, liste = api("POST", "/search/ketteler-mehrplatz-anfrage", {"filter": [{"type": "equals", "field": "email", "value": "petra@ketteler.test"}]})
    e.pruefe(status == 200 and liste["total"] >= 1 and liste["data"][0]["anzahl"] == 12, "in der Verwaltung abrufbar (Admin-API)", str(status))
    status, info = api("GET", "/_info/config")
    e.pruefe(status == 200 and any("ketteler-mehrplatz.js" in js for js in info["bundles"].get("KettelerMehrplatz", {}).get("js", [])),
             "Modul „Mehrplatz-Anfragen“ in der Verwaltung geladen")

    print("Kontoanlage, Widerruf-Häkchen, Bestellbestätigung")
    for (kid,) in sql("SELECT LOWER(HEX(id)) FROM customer WHERE email='testkunde@ketteler.test'"):
        api("DELETE", "/customer/" + kid)
    json_anfrage("DELETE", MAILPIT + "/api/v1/messages")
    k = angemeldeter_kunde()
    pruefe_mail(e, "Kontoanlage", 'subject:"Ihr Kundenkonto beim KETTELER-Verlag"')

    vorher = sql("SELECT COUNT(*) FROM `order`")[0][0]
    try:
        bestellen(k, "TEST-O-AVR", widerruf=False)
        ohne = "bestellt"
    except AssertionError:
        ohne = k.url
    e.pruefe("/checkout/confirm" in ohne and sql("SELECT COUNT(*) FROM `order`")[0][0] == vorher, "ohne Häkchen keine Bestellung", ohne)
    e.pruefe("sofort beginnen soll" in k.html, "Hinweis zum fehlenden Häkchen")
    k.get(SHOP + "/checkout/confirm")
    e.pruefe('data-kt="widerruf"' in k.html and "mein Widerrufsrecht verliere" in k.html, "Häkchen in der Kasse")

    oid, nr, _ = bestellen(k, "TEST-O-AVR", "TEST-ABO-ZMV-P")
    zustimmung = sql("SELECT JSON_UNQUOTE(JSON_EXTRACT(custom_fields,'$.ketteler_widerruf.text')) FROM `order` WHERE order_number='%s'" % nr)[0][0]
    e.pruefe("Widerrufsrecht verliere" in zustimmung, "Zustimmung mit Wortlaut an der Bestellung")
    text, html = pruefe_mail(e, "Bestellbestätigung", 'subject:"Ihre Bestellung %s beim KETTELER-Verlag"' % nr)
    e.pruefe(ENV["KETTELER_BIBLIOTHEK_URL"].rstrip("/") + "/avr" in text, "„Jetzt lesen“ in der Bestellbestätigung")
    e.pruefe("Ihre Zustimmung:" in text and "Abonnement:" in text, "Zustimmung und Abo-Hinweis in der Bestellbestätigung")
    e.pruefe(len(warte(lambda: lizenz_zu(nr))) == 1, "Lizenz angelegt")

    print("Kundenkonto")
    k.get(SHOP + "/account")
    e.pruefe('data-kt="jetzt-lesen"' in k.html and "Meine Online-Zugänge" in k.html, "Übersicht mit „Meine Online-Zugänge“")
    e.pruefe("account-overview-newsletter" not in k.html and 'href="/account/payment"' not in k.html, "Übersicht ohne Newsletter und Zahlungsart")
    k.get(SHOP + "/account/online-zugaenge")
    e.pruefe('data-kt-zugang="avr"' in k.html and 'data-kt-werk="avr"' in k.html and nr in k.html, "Seite „Meine Online-Zugänge“ mit „Jetzt lesen“")

    print("Versand- und Stornomail")
    lieferung = sql("SELECT LOWER(HEX(id)) FROM order_delivery WHERE order_id=UNHEX('%s') AND version_id=UNHEX('0fa91ce3e96a4bc2be4bd9ce752c3425')" % oid)[0][0]
    status, _ = api("POST", "/_action/order_delivery/%s/state/ship" % lieferung, {"sendMail": True})
    e.pruefe(status == 200, "Lieferung auf „versandt“", str(status))
    pruefe_mail(e, "Versandmail", 'subject:"Ihre Bestellung %s ist unterwegs"' % nr)
    status, _ = api("POST", "/_action/order/%s/state/cancel" % oid, {"sendMail": True})
    text, _ = pruefe_mail(e, "Stornomail", 'subject:"Ihre Bestellung %s wurde storniert"' % nr)
    e.pruefe("Online-Zugang aus dieser Bestellung ist damit beendet" in text, "Stornomail nennt das Ende des Online-Zugangs")
    e.pruefe(warte(lambda: (lizenz_zu(nr) or [{}])[0].get("status") == "beendet"), "Lizenz beendet")

    print("Bestellung aus der Shopware-Verwaltung bekommt eine Lizenz")
    kanal = sql("SELECT LOWER(HEX(id)) FROM sales_channel WHERE type_id=UNHEX('8A243080F92E4C719546314B577CF82B')")[0][0]
    kunde = sql("SELECT LOWER(HEX(id)) FROM customer WHERE email='testkunde@ketteler.test' AND guest=0")[0][0]
    token = "kt-verwaltung-%d" % time.time()
    ja = json_anfrage
    status, antwort = ja("PATCH", SHOP + "/api/_proxy/switch-customer", {"salesChannelId": kanal, "customerId": kunde},
                         dict(api.kopf, **{"sw-context-token": token}))
    token = (antwort or {}).get("sw-context-token") or token
    e.pruefe(status == 200, "Verwaltung: Kunde gewählt", str(status))
    status, _ = ja("POST", SHOP + "/api/_proxy/store-api/%s/checkout/cart/line-item" % kanal,
                   {"items": [{"type": "product", "referencedId": artikel_id("TEST-O-EK"), "quantity": 1}]},
                   dict(api.kopf, **{"sw-context-token": token}))
    # Wie in der Verwaltung: passende Versandart wählen (die Shop-Kasse wechselt selbst, die Verwaltung nicht)
    online = sql("SELECT LOWER(HEX(id)) FROM shipping_method WHERE technical_name='ketteler_online'")[0][0]
    ja("PATCH", SHOP + "/api/_proxy/store-api/%s/context" % kanal, {"shippingMethodId": online}, dict(api.kopf, **{"sw-context-token": token}))
    status, bestellung = ja("POST", SHOP + "/api/_proxy-order/%s" % kanal, {}, dict(api.kopf, **{"sw-context-token": token}))
    e.pruefe(status == 200 and bestellung.get("orderNumber"), "Verwaltung: Bestellung angelegt", str(status))
    if status == 200:
        e.pruefe(len(warte(lambda: lizenz_zu(bestellung["orderNumber"]))) == 1, "Lizenz zur Bestellung aus der Verwaltung angelegt")

    print("Eichstätter Online-Zugang ohne Bestandsprüfung")
    bestand = int(sql("SELECT stock FROM product WHERE product_number='TEST-O-EK'")[0][0])
    produkt = store_api("kt-bestand-%d" % time.time(), "product/" + artikel_id("TEST-O-EK"))["product"]
    e.pruefe(bestand <= 0 and produkt["available"] and not produkt["isCloseout"], "Bestand %d und trotzdem bestellbar" % bestand)
    return e.ende()


if __name__ == "__main__":
    sys.exit(main())
