"""End-to-End-Test der Entscheidungen zu den Werkseiten (docs/plan.md): alte Adressen antworten mit 301 auf die neuen
Seiten, AVR Print im Abverkauf ist bei Bestand 0 nicht bestellbar (bei Bestand > 0 schon), Online-Artikel nur einzeln
und ohne Mengenpreise, 05029-M und „ab 2. Lizenz“ gibt es nirgends. Die Seiten selbst prüft tests/browser/werkseiten.mjs.
Aufruf: python3 tests/e2e/werkseiten_test.py (KETTELER_ZIEL=staging für Staging).
"""

import json
import os
import sys
import urllib.parse

from hilfen import SHOP, WURZEL, Browser, Ergebnis, sql
from lizenz_test import artikel_id
from shop_test import warenkorb


def main():
    e = Ergebnis()
    print("Weiterleitungen alter Adressen (301)")
    liste = json.load(open(os.path.join(WURZEL, "custom/plugins/KettelerTheme/src/Resources/config/weiterleitungen.json"), encoding="utf-8"))["weiterleitungen"]
    gut = 0
    for w in liste:
        b = Browser()
        b.get(SHOP + w["alt"], folgen=False)
        ziel = b.url
        status = b.verlauf[-1][2]
        ok = status == 301 and urllib.parse.urlparse(ziel).netloc == urllib.parse.urlparse(SHOP).netloc
        if ok:
            b2 = Browser()
            b2.get(ziel.split("#")[0])
            ok = b2.status == 200
        gut += ok
        e.pruefe(ok, "%s -> 301 -> %s" % (w["alt"], urllib.parse.urlparse(ziel).path + ("#" + ziel.split("#")[1] if "#" in ziel else "")), "Status %s" % status)
    b = Browser()
    b.get(SHOP + "/", folgen=False)
    e.pruefe(b.verlauf[-1][2] == 200, "Startseite selbst wird nicht umgeleitet")
    b.get(SHOP + "/gutschein", folgen=True)
    e.pruefe("Eichstätter Kommentar MAVO und KAGO" in b.html, "/gutschein landet auf der Eichstätter-Werkseite")

    print("AVR Print: nur solange der Vorrat reicht")
    stand = dict(sql("SELECT product_number, CONCAT(stock, '/', is_closeout) FROM product WHERE product_number IN ('TEST-P-AVR','TEST-P-AVR-0')"))
    bestand = int(stand.get("TEST-P-AVR", "0/0").split("/")[0])
    # TEST-P-AVR startet mit Bestand 3 (ketteler:einrichten); jede Testbestellung verringert ihn
    e.pruefe(0 < bestand <= 3 and stand.get("TEST-P-AVR", "").endswith("/1") and stand.get("TEST-P-AVR-0") == "0/1",
             "Abverkauf: TEST-P-AVR mit Bestand (%d), TEST-P-AVR-0 Bestand 0" % bestand, str(stand))
    korb, _ = warenkorb("DE", "TEST-P-AVR")
    e.pruefe(any(p.get("referencedId") == artikel_id("TEST-P-AVR") for p in korb.get("lineItems", [])), "Bestand 3: bestellbar")
    korb, _ = warenkorb("DE", "TEST-P-AVR-0")
    e.pruefe(not any(p.get("referencedId") == artikel_id("TEST-P-AVR-0") for p in korb.get("lineItems", [])), "Bestand 0: nicht im Warenkorb", json.dumps(korb.get("errors", {}), ensure_ascii=False)[:200])
    b = Browser()
    b.get(SHOP + "/detail/" + artikel_id("TEST-P-AVR-0"))
    e.pruefe(b.status == 200 and "btn-buy" not in b.html, "Bestand 0: Artikelseite ohne Kaufen-Knopf")

    print("Mehrere Lizenzen nur über die Mehrplatz-Anfrage")
    ONLINE = "JOIN product_translation t ON t.product_id=p.id WHERE JSON_UNQUOTE(JSON_EXTRACT(t.custom_fields, '$.ketteler_artikelart'))='online'"
    online = sql("SELECT DISTINCT p.product_number, IFNULL(p.max_purchase, 0) FROM product p " + ONLINE)
    e.pruefe(online and all(int(m) == 1 for _, m in online), "Online-Artikel nur einzeln (%d Artikel, höchstens 1 Stück)" % len(online), str(online))
    staffeln = sql("SELECT COUNT(*) FROM product_price pp JOIN product p ON p.id=pp.product_id " + ONLINE)[0][0]
    e.pruefe(int(staffeln) == 0, "keine Mengenpreise bei Online-Artikeln", str(staffeln))
    m = sql("SELECT COUNT(*) FROM product p LEFT JOIN product_translation t ON t.product_id=p.id WHERE p.product_number LIKE '05029-M%' OR t.name LIKE '%Zusätzliche digitale Lizenzen%' OR t.name LIKE '%ab 2. Lizenz%'")[0][0]
    e.pruefe(int(m) == 0, "05029-M und „ab 2. Lizenz“ nicht im Katalog")
    b = Browser()
    b.get(SHOP + "/search?search=05029-M")
    karten = b.html.split('data-kt="produktkarte"')[1:]
    e.pruefe(b.status == 200 and not any("Zusätzliche digitale Lizenzen" in k or "05029-M" in k for k in karten), "Suche nach 05029-M findet keinen solchen Artikel (%d Treffer insgesamt)" % len(karten))
    return e.ende()


if __name__ == "__main__":
    sys.exit(main())
