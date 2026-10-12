"""End-to-End-Test Umsatzsteuer: OSS-Schalter (aus und an), Länder, Einrichtungen mit USt-IdNr. (VIES).

Matrix je Schalterstellung: Privatkunde DE, AT, IT, CH, UK und Einrichtung in AT mit gültiger USt-IdNr.; jeweils Print,
Online und gemischter Warenkorb. Geprüft werden je Position Bruttopreis, Steuersatz und Steuerbetrag sowie der
Gesamtbetrag. Dazu die vier Fälle der USt-IdNr. (gültig, ungültig, deutsch, VIES nicht erreichbar) mit Hinweis im Konto
und eine Bestellung (Bestellung, Mail, Daten für weclapp über die Admin-API) mit gleichem Bruttopreis.
Die Steuersätze je Land sind Platzhalter (steuersaetze.json); der Test liest sie von dort.
"""

import json
import os
import sys
import time
import urllib.parse

from hilfen import ENV, SHOP, STAGING, WURZEL, AdminApi, Browser, Ergebnis, json_anfrage, konsole, kontext_token, sql
from lizenz_test import MAILPIT, artikel_id, attrappe, warte

SAETZE = json.load(open(os.path.join(WURZEL, "custom/plugins/KettelerSteuern/src/Resources/daten/steuersaetze.json")))["laender"]
ARTIKEL = {"TEST-P-001": ("print7", 24.90), "TEST-P-002": ("print19", 12.50), "TEST-O-EK": ("online", 89.90)}
DE_SATZ = {"print7": 7, "print19": 19, "online": 7}
KORB = {"Print": ["TEST-P-001", "TEST-P-002"], "Online": ["TEST-O-EK"], "gemischt": ["TEST-P-001", "TEST-O-EK"]}
KANAL = None
SCHLUESSEL = None


def oss(an):
    konsole("system:config:set", "KettelerSteuern.config.ossAktiv", "true" if an else "false")
    konsole("ketteler:steuern:anwenden")


def store(token, pfad, daten=None, methode="POST"):
    kopf = {"sw-access-key": SCHLUESSEL}
    if token:
        kopf["sw-context-token"] = token
    req_status, antwort = json_anfrage(methode, SHOP + "/store-api/" + pfad, daten or {}, kopf)
    return req_status, antwort


def land_id(iso):
    return sql("SELECT LOWER(HEX(id)) FROM country WHERE iso='%s'" % iso)[0][0]


def erwarteter_satz(iso, steuer, an):
    if iso in ("CH", "GB"):
        return None
    if not an or iso == "DE":
        return DE_SATZ[steuer]
    art = {"print19": "standard", "print7": "buch", "online": "elektronisch"}[steuer]
    return SAETZE[iso][art]


def korb_fuer(token, nummern):
    for n in nummern:
        store(token, "checkout/cart/line-item", {"items": [{"type": "product", "referencedId": artikel_id(n), "quantity": 1}]})
    return store(token, "checkout/cart")[1]


def pruefe_korb(e, name, korb, iso, an, steuerfrei):
    positionen = {p["payload"]["productNumber"]: p for p in korb["lineItems"]}
    ok = True
    info = []
    for nummer, p in positionen.items():
        steuer, brutto = ARTIKEL[nummer]
        preis = p["price"]
        if steuerfrei:
            erwartet_netto = round(brutto / (1 + DE_SATZ[steuer] / 100), 2)
            gut = abs(preis["unitPrice"] - erwartet_netto) < 0.011 and sum(t["tax"] for t in preis["calculatedTaxes"]) == 0
            info.append("%s %.2f netto" % (nummer, preis["unitPrice"]))
        else:
            satz = erwarteter_satz(iso, steuer, an)
            steuer_betrag = round(brutto * satz / (100 + satz), 2)
            taxes = preis["calculatedTaxes"]
            gut = (abs(preis["unitPrice"] - brutto) < 0.001 and len(taxes) == 1 and float(taxes[0]["taxRate"]) == float(satz)
                   and abs(taxes[0]["tax"] - steuer_betrag) < 0.011)
            info.append("%s %.2f brutto %s%% Steuer %.2f" % (nummer, preis["unitPrice"], taxes[0]["taxRate"] if taxes else "-", taxes[0]["tax"] if taxes else 0))
        ok = ok and gut
    status = korb["price"]["taxStatus"]
    ok = ok and (status == "tax-free") == steuerfrei
    netto, brutto_ges = korb["price"]["netPrice"], korb["price"]["totalPrice"]
    steuer_ges = sum(t["tax"] for t in korb["price"]["calculatedTaxes"])
    ok = ok and abs(netto + steuer_ges - brutto_ges) < 0.011
    e.pruefe(ok, name, "; ".join(info) + " | netto %.2f Steuer %.2f brutto %.2f %s" % (netto, steuer_ges, brutto_ges, status))


def kunde_anlegen(api, email, iso, ustid=None, passwort="Kt-Steuer-Test-1!"):
    for (kid,) in sql("SELECT LOWER(HEX(id)) FROM customer WHERE email='%s'" % email):
        api("DELETE", "/customer/" + kid)
    anrede = sql("SELECT LOWER(HEX(id)) FROM salutation WHERE salutation_key='not_specified'")[0][0]
    gruppe = sql("SELECT LOWER(HEX(customer_group_id)) FROM sales_channel WHERE id=UNHEX('%s')" % KANAL)[0][0]
    zahlart = sql("SELECT LOWER(HEX(id)) FROM payment_method WHERE technical_name='payment_invoicepayment'")[0][0]
    kid = os.urandom(16).hex()
    adresse = os.urandom(16).hex()
    daten = {"id": kid, "email": email, "firstName": "Test", "lastName": "Steuer " + iso, "salutationId": anrede,
             "customerNumber": "ST-" + kid[:6], "groupId": gruppe, "salesChannelId": KANAL, "defaultPaymentMethodId": zahlart,
             "defaultBillingAddressId": adresse, "defaultShippingAddressId": adresse, "password": passwort,
             "accountType": "business" if ustid else "private", "company": "Testeinrichtung " + iso if ustid else None,
             "addresses": [{"id": adresse, "firstName": "Test", "lastName": "Steuer", "salutationId": anrede, "street": "Teststraße 1",
                            "zipcode": "1010", "city": "Teststadt", "countryId": land_id(iso), "company": "Testeinrichtung " + iso if ustid else None}]}
    if ustid:
        daten["vatIds"] = [ustid]
    status, antwort = api("POST", "/customer", daten)
    assert status in (200, 204), "Kunde nicht angelegt: %s %s" % (status, antwort)
    token = kontext_token("POST", SHOP + "/store-api/account/login", {"username": email, "password": passwort}, {"sw-access-key": SCHLUESSEL})
    assert token, "Anmeldung des Testkunden fehlgeschlagen"
    return kid, token


def ustid_stand(kid):
    zeile = sql("SELECT IFNULL(JSON_EXTRACT(custom_fields,'$.ketteler_ustid'),'null') FROM customer WHERE id=UNHEX('%s')" % kid)
    return json.loads(zeile[0][0]) if zeile else None


def als_kunde(api, kid):
    """Storefront-Sitzung als dieser Kunde über die Funktion „Als Kunde anmelden“ der Verwaltung (kein Passwort)."""
    status, antwort = api("POST", "/_proxy/generate-imitate-customer-token", {"customerId": kid, "salesChannelId": KANAL})
    benutzer = sql("SELECT LOWER(HEX(id)) FROM user WHERE username='admin'")[0][0]
    b = Browser()
    b.get(SHOP + "/")
    b.post(SHOP + "/account/login/imitate-customer", {"token": antwort["token"], "customerId": kid, "userId": benutzer})
    return b


def main():
    global KANAL, SCHLUESSEL
    e = Ergebnis()
    api = AdminApi()
    KANAL = sql("SELECT LOWER(HEX(id)) FROM sales_channel WHERE type_id=UNHEX('8A243080F92E4C719546314B577CF82B')")[0][0]
    SCHLUESSEL = sql("SELECT access_key FROM sales_channel WHERE id=UNHEX('%s')" % KANAL)[0][0]
    if not STAGING:
        attrappe("/_modus", {"modus": "normal"})
    # Lokal die Nachbildung (feste Testnummern); auf Staging die offizielle VIES-Schnittstelle: dort nur echte Nummern.
    gueltige_at = ENV.get("KETTELER_TEST_USTID_AT", "") if STAGING else "ATU 1234 5675"
    token_at = None

    print("USt-IdNr. (%s)" % ("offizielle VIES-Schnittstelle" if STAGING else "VIES-Nachbildung"))
    if gueltige_at:
        firma_at, token_at = kunde_anlegen(api, "steuer-at-firma@ketteler.test", "AT", gueltige_at)
        stand = warte(lambda: ustid_stand(firma_at), 10)
        e.pruefe(stand and stand["status"] == "gueltig" and stand["ustid"] == gueltige_at.replace(" ", "") and stand["land"] == "AT", "gültige Nummer aus AT: bestätigt", str(stand))
        e.pruefe(stand and stand.get("geprueft") and len(stand.get("verlauf", [])) == 1, "Nachweis: Prüfzeit und Verlauf am Kunden")
    else:
        e.ueberspringe("gültige Nummer aus AT und alle Fälle mit Einrichtung AT", "auf Staging keine echte gültige Test-USt-IdNr. eingetragen (KETTELER_TEST_USTID_AT)")
    firma_falsch, token_falsch = kunde_anlegen(api, "steuer-at-falsch@ketteler.test", "AT", "ATU99999999")
    e.pruefe((ustid_stand(firma_falsch) or {}).get("status") == "ungueltig", "ungültige Nummer: nicht bestätigt", str(ustid_stand(firma_falsch)))
    firma_de, token_de = kunde_anlegen(api, "steuer-de-firma@ketteler.test", "DE", "DE123456788")
    e.pruefe((ustid_stand(firma_de) or {}).get("status") == "inland", "deutsche Nummer: keine Befreiung (inland)")
    faelle = [("ungültig", token_falsch, False), ("deutsch", token_de, False)]
    if gueltige_at:
        faelle.insert(0, ("gültig", token_at, True))
    if STAGING:
        e.ueberspringe("VIES nicht erreichbar", "Ausfall der offiziellen Schnittstelle lässt sich nicht herbeiführen (lokal mit der Nachbildung geprüft)")
    else:
        attrappe("/_modus", {"modus": "vies_ausfall"})
        firma_aus, token_aus = kunde_anlegen(api, "steuer-at-ausfall@ketteler.test", "AT", "ATU12345675")
        attrappe("/_modus", {"modus": "normal"})
        e.pruefe((ustid_stand(firma_aus) or {}).get("status") == "nicht_pruefbar", "VIES nicht erreichbar: nicht prüfbar")
        faelle.append(("VIES-Ausfall", token_aus, False))
    for name, token, steuerfrei in faelle:
        korb = korb_fuer(token, ["TEST-P-001"])
        e.pruefe((korb["price"]["taxStatus"] == "tax-free") == steuerfrei, "Warenkorb Einrichtung (%s): %s" % (name, "steuerfrei" if steuerfrei else "mit Umsatzsteuer"))
    b = als_kunde(api, firma_falsch)
    b.get(SHOP + "/account")
    e.pruefe('data-kt="ustid-ungueltig"' in b.html, "Konto: klare Meldung bei ungültiger Nummer")
    if not STAGING:
        b = als_kunde(api, firma_aus)
        b.get(SHOP + "/account")
        e.pruefe('data-kt="ustid-nicht_pruefbar"' in b.html and "nicht erreichbar" in b.html, "Konto: Hinweis bei VIES-Ausfall")
        # Nach dem Ausfall erneut prüfen (Profil speichern bzw. Befehl): jetzt bestätigt
        api("PATCH", "/customer/" + firma_aus, {"vatIds": ["ATU12345675"]})
        e.pruefe((ustid_stand(firma_aus) or {}).get("status") == "gueltig", "nach dem Ausfall erneut geprüft: bestätigt")
        e.pruefe([v["status"] for v in (ustid_stand(firma_aus) or {}).get("verlauf", [])] == ["gueltig", "nicht_pruefbar"],
                 "Verlauf: frühere Prüfung bleibt erhalten")

    print("Nachweis: Abfragenummer, erneute Prüfung nach einem Jahr, Nachweis an der Bestellung")
    if STAGING:
        e.ueberspringe("Abfragenummer von VIES", "eigene USt-IdNr. von Ketteler als Anfragender noch offen (Frage an Ketteler)")
    else:
        konsole("system:config:set", "KettelerSteuern.config.anfragendeUstId", "DE123456788")
        api("PATCH", "/customer/" + firma_at, {"vatIds": ["ATU12345675"]})
        stand = ustid_stand(firma_at) or {}
        e.pruefe(str(stand.get("anfrageNummer", "")).startswith("WAPI") and stand.get("anfragende") == "DE123456788",
                 "mit eigener USt-IdNr. als Anfragender: Abfragenummer gespeichert", str(stand.get("anfrageNummer")))
        konsole("system:config:set", "KettelerSteuern.config.anfragendeUstId", "")
    if gueltige_at:
        def altern():
            alt = dict(ustid_stand(firma_at))
            alt["geprueft"] = "2024-01-15T10:00:00+00:00"
            api("PATCH", "/customer/" + firma_at, {"customFields": {"ketteler_ustid": alt}})
        altern()
        anzahl = len(ustid_stand(firma_at).get("verlauf", []))
        store(token_at, "checkout/cart", methode="DELETE")
        korb = korb_fuer(token_at, ["TEST-P-001"])
        stand = ustid_stand(firma_at) or {}
        e.pruefe(korb["price"]["taxStatus"] == "tax-free" and stand.get("geprueft", "").startswith(time.strftime("%Y-")) and len(stand.get("verlauf", [])) == anzahl + 1,
                 "Bestätigung älter als ein Jahr: beim Kauf neu geprüft, weiter steuerfrei", str(stand.get("geprueft")))
        if STAGING:
            e.ueberspringe("erneute Prüfung scheitert: keine Befreiung", "Ausfall der offiziellen Schnittstelle lässt sich nicht herbeiführen (lokal geprüft)")
        else:
            altern()
            attrappe("/_modus", {"modus": "vies_ausfall"})
            store(token_at, "checkout/cart", methode="DELETE")
            korb = korb_fuer(token_at, ["TEST-P-001"])
            attrappe("/_modus", {"modus": "normal"})
            e.pruefe(korb["price"]["taxStatus"] != "tax-free" and (ustid_stand(firma_at) or {}).get("status") == "nicht_pruefbar",
                     "erneute Prüfung scheitert: keine Befreiung", korb["price"]["taxStatus"])
            api("PATCH", "/customer/" + firma_at, {"vatIds": ["ATU12345675"]})
        store(token_at, "checkout/cart", methode="DELETE")
        korb_fuer(token_at, ["TEST-P-001"])
        status, bestellung = store(token_at, "checkout/order", {"tos": True})
        nachweis = None
        if status == 200:
            zeile = sql("SELECT IFNULL(JSON_EXTRACT(custom_fields,'$.ketteler_ustid_nachweis'),'null') FROM `order` WHERE order_number='%s'" % bestellung["orderNumber"])
            nachweis = json.loads(zeile[0][0]) if zeile else None
        e.pruefe(status == 200 and nachweis and nachweis.get("status") == "gueltig" and nachweis.get("geprueft"),
                 "Bestellung ohne Umsatzsteuer: Nachweis der Prüfung an der Bestellung", str(nachweis))
        status, _ = json_anfrage("GET", SHOP + "/bundles/kettelersteuern/administration/js/ketteler-steuern.js")
        e.pruefe(status == 200, "Verwaltung: Anzeige des Nachweises wird geladen (Skript erreichbar)", str(status))

    privat_at, token_privat_at = kunde_anlegen(api, "steuer-at-privat@ketteler.test", "AT")
    for an in (False, True):
        oss(an)
        print("OSS %s" % ("an" if an else "aus"))
        for iso in ("DE", "AT", "IT", "CH", "GB"):
            for korb_name, nummern in KORB.items():
                token = "kt-" + os.urandom(8).hex()
                store(token, "context", {"countryId": land_id(iso)}, "PATCH")
                korb = korb_fuer(token, nummern)
                pruefe_korb(e, "Privat %s, %s" % (iso, korb_name), korb, iso, an, iso in ("CH", "GB"))
        for korb_name, nummern in (KORB.items() if token_at else []):
            store(token_at, "checkout/cart", methode="DELETE")
            korb = korb_fuer(token_at, nummern)
            pruefe_korb(e, "Einrichtung AT mit gültiger USt-IdNr., %s" % korb_name, korb, "AT", an, True)

        print("Bruttopreis überall gleich (Privat AT, OSS %s)" % ("an" if an else "aus"))
        token = token_privat_at
        store(token, "checkout/cart", methode="DELETE")
        _, produkt = store(token, "product/" + artikel_id("TEST-O-EK"))
        e.pruefe(abs(produkt["product"]["calculatedPrice"]["unitPrice"] - 89.90) < 0.001, "Artikelseite: 89,90 Euro")
        korb = korb_fuer(token, ["TEST-O-EK"])
        satz = erwarteter_satz("AT", "online", an)
        e.pruefe(abs(korb["price"]["totalPrice"] - 89.90) < 0.001 and float(korb["price"]["calculatedTaxes"][0]["taxRate"]) == float(satz),
                 "Warenkorb und Kasse: 89,90 Euro mit %s %%" % satz)
        json_anfrage("DELETE", MAILPIT + "/api/v1/messages")
        online = sql("SELECT LOWER(HEX(id)) FROM shipping_method WHERE technical_name='ketteler_online'")[0][0]
        store(token, "context", {"shippingMethodId": online}, "PATCH")
        status, bestellung = store(token, "checkout/order", {"tos": True})
        e.pruefe(status == 200 and abs(bestellung["amountTotal"] - 89.90) < 0.001, "Bestellung: 89,90 Euro", str(status))
        if status == 200:
            nr = bestellung["orderNumber"]
            _, voll = api("POST", "/search/order", {"filter": [{"type": "equals", "field": "orderNumber", "value": nr}], "associations": {"lineItems": {}}})
            pos = voll["data"][0]["lineItems"][0]["price"]
            e.pruefe(abs(pos["unitPrice"] - 89.90) < 0.001 and float(pos["calculatedTaxes"][0]["taxRate"]) == float(satz) and voll["data"][0]["price"]["taxStatus"] == "gross",
                     "Daten für weclapp (Admin-API): Brutto 89,90, Satz %s %%" % satz)
            mails = warte(lambda: json_anfrage("GET", MAILPIT + "/api/v1/search?query=" + urllib.parse.quote('subject:"Ihre Bestellung %s"' % nr))[1].get("messages", []), 20)
            if mails:
                text = json_anfrage("GET", MAILPIT + "/api/v1/message/" + mails[0]["ID"])[1].get("Text", "")
                e.pruefe("89,90" in text and ("%s %% Umsatzsteuer" % format(satz, "g")) in text, "Mail: 89,90 Euro und %s %% Umsatzsteuer" % satz)
            else:
                e.pruefe(False, "Mail zur Bestellung")
    oss(False)
    return e.ende()


if __name__ == "__main__":
    sys.exit(main())
