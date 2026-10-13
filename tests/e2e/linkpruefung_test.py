"""Linkprüfung über den ganzen Shop (Gesamtauftrag E): von der Startseite aus jede eigene Seite und jede verlinkte Datei
(PDF, Bilder der Seiten) abrufen. Erwartet: 200; 302 nur dort, wo der Shop absichtlich zur Anmeldung bzw. zum Warenkorb
leitet (Konto, Kasse); 301 nur für die alten Adressen aus weiterleitungen.json, die selbst mit 200 enden. Fremde Ziele
(zmv-online.de usw.) werden gezählt, aber nicht aufgerufen. Aufruf: python3 tests/e2e/linkpruefung_test.py
(KETTELER_ZIEL=staging für Staging).
"""

import collections
import json
import os
import re
import sys
import urllib.parse

from hilfen import SHOP, WURZEL, Browser, Ergebnis

AUSLASSEN = re.compile(r"/(checkout/line-item|account/logout|widgets/|wishlist/(add|remove)|cookie/|compare|_profiler|_wdt|sitemap)")
ABSICHTLICH_302 = re.compile(r"/(account|checkout/confirm|checkout/finish|checkout/register|wishlist)(/|$|\?)")
GRENZE = 700


def main():
    e = Ergebnis()
    host = urllib.parse.urlparse(SHOP).netloc
    offen = collections.deque([SHOP + "/"])
    gesehen = {SHOP + "/"}
    status = {}
    fremd = set()
    b = Browser()
    while offen and len(status) < GRENZE:
        url = offen.popleft()
        b.get(url, folgen=False)
        s = b.verlauf[-1][2]
        status[url] = (s, b.url if s in (301, 302) else None)
        if s != 200 or not hasattr(b, "html") or b.html is None:
            continue
        if not re.search(r"\.(pdf|png|jpe?g|webp|svg|mp4|vtt|docx?)(\?|$)", url, re.I):
            for href in re.findall(r'(?:href|src)="([^"#]+)', b.html):
                ziel = urllib.parse.urljoin(url, href.replace("&amp;", "&"))
                teile = urllib.parse.urlparse(ziel)
                if teile.scheme not in ("http", "https"):
                    continue
                if teile.netloc != host:
                    fremd.add(teile.netloc)
                    continue
                if AUSLASSEN.search(teile.path) or re.search(r"/(theme|bundles|thumbnail)/", teile.path):
                    continue
                if ziel not in gesehen:
                    gesehen.add(ziel)
                    offen.append(ziel)
        b.html = None
    zaehler = collections.Counter(s for s, _ in status.values())
    falsch = [(u, s, z) for u, (s, z) in status.items() if s not in (200, 302) or (s == 302 and not ABSICHTLICH_302.search(urllib.parse.urlparse(u).path))]
    print("  %d eigene Adressen geprüft (%s), %d fremde Ziele nicht aufgerufen" % (len(status), ", ".join("%d × %d" % (n, s) for s, n in sorted(zaehler.items())), len(fremd)))
    for u, s, z in falsch[:15]:
        print("    %s  %s%s" % (s, u, (" -> " + z) if z else ""))
    e.pruefe(not falsch and len(status) > 50, "%d von %d eigenen Links in Ordnung (200, bzw. 302 zur Anmeldung/Kasse)" % (len(status) - len(falsch), len(status)))
    pdf = [u for u in status if urllib.parse.urlparse(u).path.lower().endswith(".pdf")]
    e.pruefe(len(pdf) >= 20 and all(status[u][0] == 200 for u in pdf), "%d Platzhalter-PDFs erreichbar" % len(pdf))

    liste = json.load(open(os.path.join(WURZEL, "custom/plugins/KettelerTheme/src/Resources/config/weiterleitungen.json"), encoding="utf-8"))["weiterleitungen"]
    gut = 0
    for w in liste:
        r = Browser()
        r.get(SHOP + w["alt"], folgen=False)
        ok = r.verlauf[-1][2] == 301
        if ok:
            r.get(r.url.split("#")[0])
            ok = r.status == 200
        gut += ok
    e.pruefe(gut == len(liste), "%d von %d alten Adressen: 301 auf eine Seite mit 200" % (gut, len(liste)))
    print("\nErgebnis Linkprüfung: %d eigene Adressen, %d alte Adressen mit 301, %d fremde Ziele" % (len(status), gut, len(fremd)))
    return e.ende()


if __name__ == "__main__":
    sys.exit(main())
