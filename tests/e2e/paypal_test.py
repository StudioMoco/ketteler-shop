"""End-to-End-Test PayPal im Testmodus (Sandbox). Läuft nur, wenn die Zugangsdaten einer eigenen PayPal-Sandbox-App in
der .env stehen (PAYPAL_SANDBOX_CLIENT_ID, PAYPAL_SANDBOX_CLIENT_SECRET); sonst wird er übersprungen, nicht bestanden.
Anleitung: docs/paypal-sandbox.md.

Geprüft: Testmodus an, PayPal in der Kasse wählbar, der Shop legt bei PayPal (Sandbox) eine Zahlung an. Die Freigabe durch
einen Test-Käufer bei PayPal ist ein Schritt im Browser und wird von Hand geprüft (siehe Anleitung).
"""

import os
import sys

from hilfen import ENV, SHOP, Ergebnis, json_anfrage, sql
from lizenz_test import artikel_id


def main():
    e = Ergebnis()
    name = "PayPal im Testmodus durchspielen"
    if not ENV.get("PAYPAL_SANDBOX_CLIENT_ID") or not ENV.get("PAYPAL_SANDBOX_CLIENT_SECRET"):
        e.ueberspringe(name, "keine Sandbox-Zugangsdaten in der .env, siehe docs/paypal-sandbox.md")
        return e.ende()

    sandbox = sql("SELECT configuration_value FROM system_config WHERE configuration_key='SwagPayPal.settings.sandbox'")
    e.pruefe(sandbox and "true" in sandbox[0][0], "PayPal im Testmodus (Sandbox), nie Live")
    schluessel = sql("SELECT access_key FROM sales_channel WHERE type_id=UNHEX('8A243080F92E4C719546314B577CF82B')")[0][0]
    token = "kt-paypal-" + os.urandom(6).hex()
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
