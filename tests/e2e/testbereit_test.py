"""End-to-End-Test „Shop testbereit“ (Gesamtauftrag, Abschnitt D): Stammdaten, Zahlarten, Testartikel (Name, Kategorie,
Gewicht, Steuer, Artikelart), Rechtstexte in den Grundeinstellungen, Suche nach Titel, Nummer und Autor, Adressen ohne
IDs, Cookie-Hinweis nur mit technisch notwendigen Cookies, Formulare (Kündigung, Mehrplatz, Kontakt) mit Bestätigung und
Mails im neuen Design an Mailpit, Testkonten. Auf Staging zusätzlich: Worker und geplante Aufgaben laufen.
Aufruf: python3 tests/e2e/testbereit_test.py (KETTELER_ZIEL=staging für Staging).
"""

import json
import os
import re
import sys
import time
import urllib.parse

from hilfen import ENV, SHOP, STAGING, WURZEL, Browser, Ergebnis, json_anfrage, konsole, sql
from lizenz_test import MAILPIT, artikel_id, warte
from shop_test import GEDANKENSTRICH, mail_text, mails

KANAL = "s.type_id=UNHEX('8A243080F92E4C719546314B577CF82B')"
ARTIKEL = json.load(open(os.path.join(WURZEL, "custom/plugins/KettelerEinrichtung/src/Resources/daten/artikel.json"), encoding="utf-8"))["artikel"]


def konfig(schluessel):
    zeile = sql("SELECT configuration_value FROM system_config WHERE configuration_key='%s' AND sales_channel_id IS NULL" % schluessel)
    return json.loads(zeile[0][0]).get("_value") if zeile else None


def pruefe_design(e, name, nachricht):
    text, html = mail_text(nachricht)
    e.pruefe("data:image/png;base64," in html and 'alt="KETTELER-Verlag"' in html, name + ": Logo im Kopf")
    e.pruefe("#b7005c" in html and "#fbf8f3" in html and "'IBM Plex Sans'" in html and "Arial" in html, name + ": Farben und Schrift mit Ersatz")
    e.pruefe("Verträge hier kündigen" in html and "AGB" in html and "Datenschutzerklärung" in html and "/navigation/" not in html, name + ": Rechtstexte im Fuß, Adressen ohne ID")
    e.pruefe(("Guten Tag" in text) and " du " not in text.lower() and not GEDANKENSTRICH.search(text), name + ": Sie-Form, ohne Gedankenstrich")


def main():
    e = Ergebnis()
    json_anfrage("DELETE", MAILPIT + "/api/v1/messages")

    print("Stammdaten")
    name = sql("SELECT t.name FROM sales_channel_translation t JOIN sales_channel s ON s.id=t.sales_channel_id WHERE " + KANAL + " LIMIT 1")
    e.pruefe(name and name[0][0] == "KETTELER-Verlag", "Verkaufskanal „KETTELER-Verlag“", str(name))
    e.pruefe(konfig("core.basicInformation.shopName") == "KETTELER-Verlag" and konfig("core.basicInformation.email") == "kontakt@ketteler-verlag.de", "Shopname und Absender aus dem Impressum")
    waehrung = sql("SELECT c.iso_code FROM currency c JOIN sales_channel s ON s.currency_id=c.id WHERE " + KANAL)
    sprache = sql("SELECT l.name FROM language l JOIN sales_channel s ON s.language_id=l.id WHERE " + KANAL)
    e.pruefe(waehrung and waehrung[0][0] == "EUR" and sprache and sprache[0][0] == "Deutsch", "Deutsch und Euro", str(waehrung) + str(sprache))
    laender = {z[0] for z in sql("SELECT c.iso FROM country c JOIN sales_channel_country sc ON sc.country_id=c.id JOIN sales_channel s ON s.id=sc.sales_channel_id WHERE " + KANAL)}
    e.pruefe({"DE", "AT", "CH", "IT", "GB", "FR", "NL"} <= laender and len(laender) == 29, "Lieferländer DE, AT, CH, IT, UK und EU (29)")
    belege = sql("SELECT config FROM document_base_config")
    e.pruefe(belege and all('"vatId": "DE 122 792 915"' in z[0] and "Josef Singer" in z[0] for z in belege), "Firmendaten auf allen Belegen (USt-IdNr., Geschäftsführer)")

    print("Testartikel")
    steuer = {"print7": 7.0, "print19": 19.0}
    fehler = []
    for a in ARTIKEL:
        zeile = sql("SELECT p.weight, t.tax_rate, p.shipping_free, (SELECT COUNT(*) FROM product_category pc WHERE pc.product_id=p.id), "
                    "JSON_UNQUOTE(JSON_EXTRACT(pt.custom_fields, '$.ketteler_artikelart')), pt.name FROM product p JOIN tax t ON t.id=p.tax_id "
                    "JOIN product_translation pt ON pt.product_id=p.id WHERE p.product_number='%s' LIMIT 1" % a["nummer"])
        if not zeile:
            fehler.append(a["nummer"] + " fehlt")
            continue
        gewicht, satz, versandfrei, kategorien, art, name = zeile[0]
        if "(Test)" not in name:
            fehler.append(a["nummer"] + " ohne (Test)")
        if art != a["artikelart"]:
            fehler.append(a["nummer"] + " Artikelart " + str(art))
        if a["artikelart"] in ("print", "bundle") and not float(gewicht or 0) > 0:
            fehler.append(a["nummer"] + " ohne Gewicht")
        if a["artikelart"] == "online" and versandfrei != "1":
            fehler.append(a["nummer"] + " nicht versandfrei")
        if a["steuer"] in steuer and float(satz) != steuer[a["steuer"]]:
            fehler.append(a["nummer"] + " Steuer " + satz)
        if int(kategorien) == 0 and a["nummer"] != "TEST-B-EK":
            fehler.append(a["nummer"] + " ohne Kategorie")
    e.pruefe(not fehler, "%d Testartikel: „(Test)“ im Namen, Artikelart, Gewicht bei Print, versandfrei online, Steuersatz, Kategorie" % len(ARTIKEL), "; ".join(fehler)[:600])
    online_ohne_werk = [a["nummer"] for a in ARTIKEL if a["artikelart"] in ("online", "bundle") and not a.get("produkt")]
    print("  Hinweis: Online-Artikel ohne Werk für die Lizenz (ZMV Digital, Frage 14): " + ", ".join(online_ohne_werk))

    print("Rechtstexte in den Grundeinstellungen")
    seiten = {k: konfig("core.basicInformation." + k) for k in ("imprintPage", "tosPage", "revocationPage", "privacyPage", "shippingPaymentInfoPage")}
    e.pruefe(all(seiten.values()), "Impressum, AGB, Widerruf, Datenschutz, Versand zugeordnet", str(seiten))
    fuss = Browser().get(SHOP + "/")
    e.pruefe(all(t in fuss.html for t in ("Impressum", "AGB", "Widerrufsbelehrung", "Datenschutzerklärung", "Versand &amp; Lieferung", "Verträge hier kündigen")), "alle Rechtstexte im Fuß")
    e.pruefe("/vertraege-kuendigen" in fuss.html, "„Verträge hier kündigen“ führt auf die Kündigung")

    print("Suche, Adressen, Cookies")
    for begriff, erwartet in (("AVR", "Die AVR von A bis Z"), ("05029", "Die AVR von A bis Z"), ("Küster", "AVR - Eingruppierung")):
        s = Browser().get(SHOP + "/search?search=" + urllib.parse.quote(begriff))
        e.pruefe(erwartet in s.html, "Suche „%s“ findet „%s“" % (begriff, erwartet))
    start = Browser().get(SHOP + "/")
    ohne_id = re.findall(r'href="[^"]*/(?:navigation|detail)/[0-9a-f]{32}', start.html)
    e.pruefe(not ohne_id, "Startseite: Links ohne IDs (SEO-Adressen)", str(ohne_id[:3]))
    liste = Browser().get(SHOP + "/Praxishandbuecher/")
    e.pruefe(not re.findall(r'href="[^"]*/detail/[0-9a-f]{32}', liste.html), "Produktlisten: Artikel-Links ohne IDs")
    cookies = Browser().get(SHOP + "/cookie/offcanvas")
    e.pruefe(cookies.status == 200 and "Technisch erforderlich" in cookies.html and not re.search(r"Statistik|Marketing|Komfort", cookies.html),
             "Cookie-Hinweis nur mit technisch notwendigen Cookies")

    print("Kündigung (Verträge hier kündigen)")
    b = Browser()
    b.get(SHOP + "/vertraege-kuendigen")
    e.pruefe(b.status == 200 and "Jetzt kündigen" in b.html and "Abo-Nummer oder letzte Rechnungsnummer" in b.html, "Formular erreichbar, Knopf „Jetzt kündigen“")
    b.absenden("/vertraege-kuendigen", {"vertrag": "", "vorname": "Kai", "nachname": "Kündig", "email": "kaputt", "email2": "x", "art": "ordentlich", "termin": "naechstmoeglich"})
    e.pruefe('data-kt="kuendigung-fehler"' in b.html and b.html.count("is-invalid") >= 2, "Pflichtfelder und E-Mail geprüft")
    b.absenden("/vertraege-kuendigen", {"vertrag": "ABO-TEST-4711", "vorname": "Kai", "nachname": "Kündig", "firma": "", "strasse": "", "plz": "", "ort": "",
                                        "email": "kuendigung@ketteler.test", "email2": "kuendigung@ketteler.test", "art": "ordentlich", "grund": "", "termin": "naechstmoeglich", "datum": ""})
    e.pruefe('data-kt="kuendigung-gesendet"' in b.html and "Eingegangen am" in b.html and "ABO-TEST-4711" in b.html, "Bestätigungsseite mit Eingangszeit zum Speichern")
    kunde = warte(lambda: mails('to:kuendigung@ketteler.test subject:"Wir haben Ihre Kündigung erhalten"'), 30)
    e.pruefe(bool(kunde), "Eingangsbestätigung an die Kundin (Mailpit)")
    if kunde:
        pruefe_design(e, "Kündigungsbestätigung", kunde[0])
    intern = warte(lambda: mails('subject:"Kündigung: ABO-TEST-4711"'), 30)
    e.pruefe(bool(intern), "Mail an den Kundenservice")

    print("Mehrplatz-Anfrage: Eingangsbestätigung")
    m = Browser()
    m.get(SHOP + "/mehrplatz-anfrage?werk=zmv")
    m.absenden("/mehrplatz-anfrage", {"einrichtung": "Caritas Teststadt", "ansprechperson": "Petra Mehrplatz", "email": "mehrplatz@ketteler.test",
                                      "telefon": "", "anzahl": "5", "nachricht": "", "werk": "zmv"})
    e.pruefe('data-kt="mehrplatz-gesendet"' in m.html, "Bestätigung im neuen Stil")
    bestaetigung = warte(lambda: mails('to:mehrplatz@ketteler.test subject:"Wir haben Ihre Anfrage für mehrere Lizenzen erhalten"'), 30)
    e.pruefe(bool(bestaetigung), "Eingangsbestätigung an die anfragende Person")
    if bestaetigung:
        pruefe_design(e, "Mehrplatz-Bestätigung", bestaetigung[0])
        text, _ = mail_text(bestaetigung[0])
        e.pruefe("ZMV Die Mitarbeitervertretung" in text, "Werk in der Bestätigung")

    print("Kontakt")
    k = Browser()
    k.get(SHOP + "/Verlag/Kontakt/")
    slot = re.search(r'name="slotId" value="([0-9a-f]{32})"', k.html)
    navigation = re.search(r'name="navigationId" value="([0-9a-f]{32})"', k.html)
    anrede = sql("SELECT LOWER(HEX(id)) FROM salutation WHERE salutation_key='not_specified'")[0][0]
    e.pruefe(k.status == 200 and slot is not None and "Schloßhof 1" in k.html, "Kontaktseite mit Formular und Anschrift")
    if slot:
        k.post(SHOP + "/form/contact", {"salutationId": anrede, "firstName": "Konrad", "lastName": "Kontakt", "email": "kontakt-test@ketteler.test",
                                        "phone": "", "subject": "Testanfrage Kontaktformular", "comment": "Bitte rufen Sie mich zurück, das ist ein Test des Formulars.",
                                        "slotId": slot.group(1), "navigationId": navigation.group(1) if navigation else "", "entityName": "category", "privacy": "1", "shopware_surname_confirm": ""},
               kopf={"X-Requested-With": "XMLHttpRequest"})
        try:
            antwort = " ".join(a.get("alert", "") for a in json.loads(k.html) if a.get("type") == "success")
        except ValueError:
            antwort = ""
        e.pruefe("Vielen Dank für Ihre Nachricht" in antwort, "Bestätigung nach dem Absenden", k.html[:200])
        eingang = warte(lambda: mails('subject:"Testanfrage Kontaktformular"') or mails("Kontakt"), 30)
        e.pruefe(bool(eingang), "Kontaktanfrage in Mailpit")

    print("Testkonten")
    # Sicherstellen (wie ops/staging/testzugaenge.sh): ZITADEL-Konto und Kundenkonto, Anschrift, USt-IdNr. (geprüft bei VIES)
    ausgabe = konsole("ketteler:zitadel:einrichten", "--testkonten")
    e.pruefe(ausgabe.count("Testkonto ") == 4, "vier Testkonten in ZITADEL und im Shop", ausgabe[-300:])
    konten = {z[0]: z[1:] for z in sql("SELECT c.email, c.account_type, JSON_UNQUOTE(JSON_EXTRACT(c.custom_fields,'$.ketteler_ustid.status')), co.iso, "
                                       "JSON_UNQUOTE(JSON_EXTRACT(c.custom_fields,'$.ketteler_zitadel_sub')) IS NOT NULL FROM customer c "
                                       "JOIN customer_address a ON a.id=c.default_billing_address_id JOIN country co ON co.id=a.country_id "
                                       "WHERE c.email IN ('privat-de@ketteler.test','privat-at@ketteler.test','firma-de@ketteler.test','einrichtung-at@ketteler.test')")}
    e.pruefe(konten.get("privat-de@ketteler.test", [None])[0] == "private" and konten.get("privat-at@ketteler.test", [None, None, None])[2] == "AT", "Privat DE und Privat AT", str(konten))
    e.pruefe(konten.get("firma-de@ketteler.test", [None, None])[1] == "inland", "Firma DE mit USt-IdNr. (Inland)")
    e.pruefe(konten.get("einrichtung-at@ketteler.test", [None, None])[1] == "gueltig", "Einrichtung AT mit geprüfter USt-IdNr. (Steuerschuldnerschaft des Empfängers)")

    if STAGING:
        print("Worker und geplante Aufgaben (Staging)")
        alt = sql("SELECT COUNT(*) FROM scheduled_task WHERE status='scheduled' AND next_execution_time < NOW() - INTERVAL 1 DAY")[0][0]
        e.pruefe(int(alt) == 0, "keine geplante Aufgabe länger als einen Tag überfällig", alt)
        offen = sql("SELECT COUNT(*) FROM messenger_messages WHERE delivered_at IS NULL AND available_at < NOW() - INTERVAL 1 HOUR")[0][0]
        e.pruefe(int(offen) == 0, "keine Nachricht der Warteschlange länger als eine Stunde unbearbeitet", offen)
    return e.ende()


if __name__ == "__main__":
    sys.exit(main())
