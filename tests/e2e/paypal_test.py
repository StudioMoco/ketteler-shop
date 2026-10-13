"""End-to-End-Test PayPal im Testmodus (Sandbox). Läuft nur, wenn die Zugangsdaten einer eigenen PayPal-Sandbox-App in
der .env stehen (PAYPAL_SANDBOX_CLIENT_ID, PAYPAL_SANDBOX_CLIENT_SECRET); sonst wird er übersprungen, nicht bestanden.
Anleitung: docs/paypal-sandbox.md.

Geprüft: Testmodus an, PayPal in der Kasse wählbar, der Shop legt bei PayPal (Sandbox) eine Zahlung an. Die Freigabe durch
einen Test-Käufer bei PayPal ist ein Schritt im Browser und wird von Hand geprüft (siehe Anleitung).
"""

import os
import sys

from hilfen import ENV, SHOP, AdminApi, Ergebnis, json_anfrage, kontext_token, sql
from lizenz_test import artikel_id


def testkunde(api, schluessel, email="paypal-test@ketteler.test", passwort="Kt-PayPal-Test-1!"):
    for (kid,) in sql("SELECT LOWER(HEX(id)) FROM customer WHERE email='%s'" % email):
        api("DELETE", "/customer/" + kid)
    kanal = sql("SELECT LOWER(HEX(id)) FROM sales_channel WHERE access_key='%s'" % schluessel)[0][0]
    anrede = sql("SELECT LOWER(HEX(id)) FROM salutation WHERE salutation_key='not_specified'")[0][0]
    gruppe = sql("SELECT LOWER(HEX(customer_group_id)) FROM sales_channel WHERE id=UNHEX('%s')" % kanal)[0][0]
    zahlart = sql("SELECT LOWER(HEX(id)) FROM payment_method WHERE technical_name='payment_invoicepayment'")[0][0]
    land = sql("SELECT LOWER(HEX(id)) FROM country WHERE iso='DE'")[0][0]
    kid, adresse = os.urandom(16).hex(), os.urandom(16).hex()
    status, antwort = api("POST", "/customer", {
        "id": kid, "email": email, "firstName": "Test", "lastName": "PayPal", "salutationId": anrede, "customerNumber": "PP-" + kid[:6],
        "groupId": gruppe, "salesChannelId": kanal, "defaultPaymentMethodId": zahlart, "defaultBillingAddressId": adresse,
        "defaultShippingAddressId": adresse, "password": passwort, "accountType": "private",
        "addresses": [{"id": adresse, "firstName": "Test", "lastName": "PayPal", "salutationId": anrede, "street": "Teststraße 1",
                       "zipcode": "50670", "city": "Köln", "countryId": land}]})
    assert status in (200, 204), "Testkunde nicht angelegt: %s %s" % (status, antwort)
    token = kontext_token("POST", SHOP + "/store-api/account/login", {"username": email, "password": passwort}, {"sw-access-key": schluessel})
    assert token, "Anmeldung des Testkunden fehlgeschlagen"
    return token


def main():
    e = Ergebnis()
    name = "PayPal im Testmodus durchspielen"
    if not ENV.get("PAYPAL_SANDBOX_CLIENT_ID") or not ENV.get("PAYPAL_SANDBOX_CLIENT_SECRET"):
        e.ueberspringe(name, "keine Sandbox-Zugangsdaten in der .env, siehe docs/paypal-sandbox.md")
        return e.ende()
    im_kanal = sql("SELECT COUNT(*) FROM sales_channel_payment_method sp JOIN payment_method pm ON pm.id=sp.payment_method_id "
                   "JOIN sales_channel s ON s.id=sp.sales_channel_id WHERE s.type_id=UNHEX('8A243080F92E4C719546314B577CF82B') AND pm.technical_name='swag_paypal_paypal'")[0][0]
    if int(im_kanal) == 0:
        sandbox = sql("SELECT configuration_value FROM system_config WHERE configuration_key='SwagPayPal.settings.sandbox'")
        e.pruefe(sandbox and "true" in sandbox[0][0], "PayPal bleibt im Testmodus eingerichtet")
        e.ueberspringe(name, "PayPal ist im Verkaufskanal ausgeschaltet (Gesamtauftrag D); zum Testen dem Kanal zuordnen")
        return e.ende()

    sandbox = sql("SELECT configuration_value FROM system_config WHERE configuration_key='SwagPayPal.settings.sandbox'")
    e.pruefe(sandbox and "true" in sandbox[0][0], "PayPal im Testmodus (Sandbox), nie Live")
    schluessel = sql("SELECT access_key FROM sales_channel WHERE type_id=UNHEX('8A243080F92E4C719546314B577CF82B')")[0][0]
    # PayPal legt Zahlungen nur für angemeldete Kunden an: eigener Testkunde, Anmeldung über die Store-API
    token = testkunde(AdminApi(), schluessel)
    kopf = {"sw-access-key": schluessel, "sw-context-token": token}
    json_anfrage("POST", SHOP + "/store-api/checkout/cart/line-item",
                 {"items": [{"type": "product", "referencedId": artikel_id("TEST-P-001"), "quantity": 1}]}, kopf)
    _, zahlarten = json_anfrage("POST", SHOP + "/store-api/payment-method", {"onlyAvailable": True}, kopf)
    paypal = [z for z in zahlarten.get("elements", []) if z.get("technicalName") == "swag_paypal_paypal"]
    e.pruefe(len(paypal) == 1, "PayPal in der Kasse wählbar")
    if paypal:
        json_anfrage("PATCH", SHOP + "/store-api/context", {"paymentMethodId": paypal[0]["id"]}, kopf)
        status, antwort = json_anfrage("POST", SHOP + "/store-api/paypal/create-order", {"product": "spb"}, kopf)
        e.pruefe(status == 200 and isinstance(antwort, dict) and antwort.get("token"), "Zahlung bei PayPal (Sandbox) angelegt", str(status))
    return e.ende()


if __name__ == "__main__":
    sys.exit(main())
