"""End-to-End-Test Anmeldung über ZITADEL und Gastbestellung. Aufruf: make test-e2e (läuft gegen die lokale Umgebung).

Prüft: PKCE-Anmeldung, erstes Anmelden mit Kontoanlage, Wiederanmeldung über die ZITADEL-Kennung, Verknüpfung über
die bestätigte E-Mail, unbestätigte E-Mail, falscher state, gesperrte Passwortwege, Abmelden auch bei ZITADEL,
Gastbestellung nur bei reinem Print.
"""

import hashlib
import sys
import urllib.parse

from hilfen import SHOP, ENV, AdminApi, Browser, Ergebnis, sql, zitadel_anmelden

PASSWORT = ENV["KETTELER_TESTKUNDE_PASSWORT"]


def artikel_id(nummer):
    return hashlib.md5(("ketteler.artikel." + nummer).encode()).hexdigest()


def in_den_warenkorb(b, nummer):
    pid = artikel_id(nummer)
    b.post(SHOP + "/checkout/line-item/add", {
        "lineItems[%s][id]" % pid: pid, "lineItems[%s][type]" % pid: "product",
        "lineItems[%s][referencedId]" % pid: pid, "lineItems[%s][quantity]" % pid: "1",
        "lineItems[%s][stackable]" % pid: "1", "lineItems[%s][removable]" % pid: "1",
        "redirectTo": "frontend.checkout.cart.page"}, kopf={"X-Requested-With": "XMLHttpRequest"})


def kunden_aufraeumen(api):
    """Kunden aus früheren Läufen entfernen (nur Testadressen @ketteler.test)."""
    for (kid,) in sql("SELECT LOWER(HEX(id)) FROM customer WHERE email LIKE '%@ketteler.test'"):
        api("DELETE", "/customer/" + kid)


def sub_von(email):
    zeilen = sql("SELECT JSON_UNQUOTE(JSON_EXTRACT(custom_fields, '$.ketteler_zitadel_sub')) FROM customer "
                 "WHERE email='%s' AND guest=0" % email)
    return zeilen[0][0] if zeilen and zeilen[0][0] != "NULL" else None


def gast_registrieren(b, email):
    land = sql("SELECT LOWER(HEX(id)) FROM country WHERE iso='DE'")[0][0]
    anrede = sql("SELECT LOWER(HEX(id)) FROM salutation WHERE salutation_key='not_specified'")[0][0]
    b.get(SHOP + "/checkout/register")
    return b.absenden("/account/register", {
        "salutationId": anrede, "firstName": "Gabi", "lastName": "Gast", "email": email,
        "billingAddress[street]": "Teststraße 1", "billingAddress[zipcode]": "50670", "billingAddress[city]": "Köln",
        "billingAddress[countryId]": land, "accountType": "private"})


def main():
    e = Ergebnis()
    api = AdminApi()
    kunden_aufraeumen(api)

    print("Anmeldeweg")
    b = Browser()
    b.get(SHOP + "/account/oidc/anmelden", folgen=False)
    q = urllib.parse.parse_qs(urllib.parse.urlparse(b.url).query)
    e.pruefe(b.url.startswith(ENV["KETTELER_ZITADEL_URL"] + "/oauth/v2/authorize"), "Weiterleitung zu ZITADEL")
    e.pruefe(q.get("code_challenge_method") == ["S256"] and len(q.get("code_challenge", [""])[0]) == 43, "PKCE S256")
    e.pruefe(any(s.startswith("urn:zitadel:iam:org:id:") for s in q.get("scope", [""])[0].split()), "Kunden-Organisation im Scope")
    e.pruefe("client_secret" not in b.url and len(q.get("state", [""])[0]) >= 40 and q.get("nonce"), "state und nonce gesetzt")

    print("Erste Anmeldung legt das Kundenkonto an")
    b.get(b.url)
    zitadel_anmelden(b, "testkunde@ketteler.test", PASSWORT)
    e.pruefe(b.url.startswith(SHOP + "/account/oidc/vervollstaendigen"), "Konto vervollständigen", b.url)
    e.pruefe("testkunde@ketteler.test" in b.html, "E-Mail aus ZITADEL vorbelegt")
    b.absenden("/account/oidc/vervollstaendigen", {"kontotyp": "privat", "strasse": "Musterweg 2", "plz": "50670", "ort": "Köln"})
    e.pruefe(b.url.rstrip("/").endswith("/account") and "Tessa" in b.html, "angemeldet im Kundenkonto", b.url)
    sub = sub_von("testkunde@ketteler.test")
    e.pruefe(sub is not None and sub.isdigit(), "ZITADEL-Kennung am Kunden gespeichert")

    print("Profil: E-Mail und Passwort nur bei ZITADEL")
    b.get(SHOP + "/account/profile")
    e.pruefe('data-kt="konto-verwalten"' in b.html and 'name="password"' not in b.html, "keine Passwortfelder im Profil")

    print("Abmelden beendet auch die ZITADEL-Sitzung")
    b.get(SHOP + "/account/logout")
    e.pruefe(any("/oidc/v1/end_session" in u for _, u, _ in b.verlauf[-6:]), "Weiterleitung über end_session")
    e.pruefe(b.url.rstrip("/") == SHOP, "zurück auf der Startseite", b.url)
    b.get(SHOP + "/account")
    e.pruefe("/account/login" in b.url, "Kundenkonto verlangt wieder Anmeldung", b.url)
    b.get(SHOP + "/account/oidc/anmelden")
    e.pruefe("/ui/login/" in b.url and (b.formular("/ui/login/loginname") is not None or "Abgemeldet" in b.text()),
             "ZITADEL fragt erneut nach der Anmeldung (Konto als abgemeldet angezeigt)", b.url)

    print("Zweite Anmeldung findet das Konto über die ZITADEL-Kennung")
    zitadel_anmelden(b, "testkunde@ketteler.test", PASSWORT)
    e.pruefe(b.url.rstrip("/").endswith("/account") and "Tessa" in b.html, "direkt angemeldet", b.url)
    anzahl = sql("SELECT COUNT(*) FROM customer WHERE email='testkunde@ketteler.test' AND guest=0")[0][0]
    e.pruefe(anzahl == "1", "kein zweites Kundenkonto", anzahl)

    print("Verknüpfung über die bestätigte E-Mail")
    land = sql("SELECT LOWER(HEX(id)) FROM country WHERE iso='DE'")[0][0]
    anrede = sql("SELECT LOWER(HEX(id)) FROM salutation WHERE salutation_key='not_specified'")[0][0]
    kanal = sql("SELECT LOWER(HEX(id)) FROM sales_channel WHERE type_id=UNHEX('8A243080F92E4C719546314B577CF82B')")[0][0]
    gruppe = sql("SELECT LOWER(HEX(customer_group_id)) FROM sales_channel WHERE id=UNHEX('%s')" % kanal)[0][0]
    zahlart = sql("SELECT LOWER(HEX(payment_method_id)) FROM sales_channel WHERE id=UNHEX('%s')" % kanal)[0][0]
    adresse = "a" * 32
    status, _ = api("POST", "/customer", {
        "id": "b" * 32, "email": "testkunde2@ketteler.test", "firstName": "Theo", "lastName": "Bestand",
        "salutationId": anrede, "customerNumber": "BESTAND-1", "groupId": gruppe, "salesChannelId": kanal,
        "defaultPaymentMethodId": zahlart, "defaultBillingAddressId": adresse, "defaultShippingAddressId": adresse,
        "addresses": [{"id": adresse, "firstName": "Theo", "lastName": "Bestand", "salutationId": anrede,
                       "street": "Altweg 3", "zipcode": "93449", "city": "Waldmünchen", "countryId": land}]})
    e.pruefe(status in (200, 204), "Bestandskunde ohne Kennung angelegt", str(status))
    b2 = Browser()
    b2.get(SHOP + "/account/oidc/anmelden")
    zitadel_anmelden(b2, "testkunde2@ketteler.test", PASSWORT)
    e.pruefe(b2.url.rstrip("/").endswith("/account"), "ohne Kontoanlage angemeldet", b2.url)
    e.pruefe(sub_von("testkunde2@ketteler.test") is not None, "Kennung am Bestandskunden gespeichert")
    e.pruefe(sql("SELECT customer_number FROM customer WHERE email='testkunde2@ketteler.test'")[0][0] == "BESTAND-1", "derselbe Kunde")

    print("Unbestätigte E-Mail")
    b3 = Browser()
    b3.get(SHOP + "/account/oidc/anmelden")
    zitadel_anmelden(b3, "unbestaetigt@ketteler.test", PASSWORT)
    e.pruefe("/ui/login/" in b3.url and "/mail/verification" in b3.url + b3.html, "ZITADEL verlangt Bestätigung", b3.url)
    e.pruefe(sql("SELECT COUNT(*) FROM customer WHERE email='unbestaetigt@ketteler.test'")[0][0] == "0", "kein Kundenkonto angelegt")

    print("Manipulierte Rückkehr")
    b4 = Browser()
    b4.get(SHOP + "/account/oidc/anmelden", folgen=False)
    b4.get(SHOP + "/account/oidc/rueckkehr?code=gefaelscht&state=falsch")
    e.pruefe("/account/login" in b4.url, "falscher state wird abgewiesen", b4.url)
    b4.get(SHOP + "/account")
    e.pruefe("/account/login" in b4.url, "nicht angemeldet")

    print("Passwortwege von Shopware gesperrt")
    b5 = Browser()
    b5.post(SHOP + "/account/login", {"username": "testkunde@ketteler.test", "password": PASSWORT}, folgen=False)
    e.pruefe("/account/oidc/anmelden" in b5.url, "Passwort-Anmeldung leitet zu ZITADEL", b5.url)
    b5.get(SHOP + "/account/recover", folgen=False)
    e.pruefe("/account/oidc/anmelden" in b5.url, "Passwort vergessen leitet zu ZITADEL", b5.url)
    b5.get(SHOP + "/account/register", folgen=False)
    e.pruefe("registrieren=1" in b5.url, "Registrieren leitet zu ZITADEL", b5.url)

    print("Gastbestellung nur bei reinem Print")
    g = Browser()
    in_den_warenkorb(g, "TEST-O-EK")
    g.get(SHOP + "/checkout/register")
    e.pruefe('data-kt="online-hinweis"' in g.html and g.formular("/account/register") is None, "kein Gastformular mit Online-Artikel")
    # Gastformular von Hand abschicken (Umgehung der Oberfläche): Bestellung muss trotzdem blockiert sein
    land = sql("SELECT LOWER(HEX(id)) FROM country WHERE iso='DE'")[0][0]
    anrede = sql("SELECT LOWER(HEX(id)) FROM salutation WHERE salutation_key='not_specified'")[0][0]
    g.post(SHOP + "/account/register", {
        "salutationId": anrede, "firstName": "Otto", "lastName": "Online", "email": "gast-online@ketteler.test",
        "billingAddress[street]": "Teststraße 1", "billingAddress[zipcode]": "50670", "billingAddress[city]": "Köln",
        "billingAddress[countryId]": land, "accountType": "private", "redirectTo": "frontend.checkout.confirm.page"})
    g.get(SHOP + "/checkout/confirm")
    g.post(SHOP + "/checkout/order", {"tos": "on"})
    e.pruefe("/checkout/finish" not in g.url, "erzwungener Gast mit Online-Artikel: Bestellung blockiert", g.url)
    g2 = Browser()
    in_den_warenkorb(g2, "TEST-P-001")
    gast_registrieren(g2, "gast-print@ketteler.test")
    e.pruefe(g2.url.endswith("/checkout/confirm"), "Gast mit Print kommt zur Kasse", g2.url)
    e.pruefe(g2.formular("/checkout/order") is not None and "ketteler-online-nur-mit-konto" not in g2.html, "Bestellen möglich")
    # Gast legt danach einen Online-Artikel dazu: Bestellung muss blockiert sein
    in_den_warenkorb(g2, "TEST-O-EK")
    g2.get(SHOP + "/checkout/confirm")
    e.pruefe("Online-Zugänge können nur mit Kundenkonto bestellt werden" in g2.html, "Hinweis bei Gast mit Online-Artikel")
    g2.post(SHOP + "/checkout/order", {"tos": "on"})
    e.pruefe("/checkout/finish" not in g2.url, "Bestellung blockiert", g2.url)
    e.pruefe(sql("SELECT COUNT(*) FROM `order` o JOIN order_customer oc ON oc.order_id=o.id WHERE oc.email IN ('gast-print@ketteler.test','gast-online@ketteler.test')")[0][0] == "0",
             "keine Bestellung angelegt")
    return e.ende()


if __name__ == "__main__":
    sys.exit(main())
