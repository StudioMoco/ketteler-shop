"""Statische Vorschau einer Gestaltungsvorlage (Standard: docs/design/startseite-entwurf.dc.html) für Vergleichsbilder.

Die Vorlage braucht zum Anzeigen ein Hilfsskript (support.js) und lädt Google Fonts. Hier: Akzentfarbe einsetzen,
Listen (sc-for) mit den Daten aus der Vorlage ausrollen, Schriften aus dem Theme (lokal), Logo aus dem Theme, keine
Anfrage an fremde Server. Aufruf: python3 tests/browser/vorlage_vorschau.py <ziel.html>
"""

import json
import os
import re
import sys

WURZEL = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
VORLAGE = os.path.join(WURZEL, "docs/design/startseite-entwurf.dc.html")
SCHRIFTEN = os.path.join(WURZEL, "custom/plugins/KettelerTheme/src/Resources/app/storefront/src/assets/fonts")
LOGO_ORDNER = os.path.join(WURZEL, "custom/plugins/KettelerTheme/src/Resources/public/logo")
LOGO = os.path.join(LOGO_ORDNER, "ketteler-logo-fachverlag.png")
if not os.path.exists(LOGO):
    LOGO = os.path.join(LOGO_ORDNER, "ketteler-logo.png")


def js_liste(quelle, name):
    """Die Liste name: [ {...}, ... ] (oder Liste von Texten) aus dem Skript der Vorlage als Python-Liste."""
    m = re.search(r"\b" + name + r":\s*(\[.*?\n\s*\])", quelle, re.S) or re.search(r"\b" + name + r":\s*(\[[^\n]*\])", quelle)
    text = re.sub(r"(\{|,)\s*(\w+):", r'\1 "\2":', m.group(1))
    text = re.sub(r"'((?:[^'\\]|\\.)*)'", lambda t: json.dumps(t.group(1)), text)
    return json.loads(text)


def main(ziel, vorlage=VORLAGE):
    html = open(vorlage, encoding="utf-8").read()
    daten = {name: js_liste(html, name) for name in set(re.findall(r'<sc-for list="\{\{(\w+)\}\}"', html))}
    html = html.replace("{{accent}}", "#b7005c")

    def ausrollen(m):
        liste, name, inhalt = m.group(1), m.group(2), m.group(3)
        aus = []
        for eintrag in daten[liste]:
            teil = inhalt
            if isinstance(eintrag, dict):
                for k, v in eintrag.items():
                    teil = teil.replace("{{%s.%s}}" % (name, k), str(v))
            else:
                teil = teil.replace("{{%s}}" % name, str(eintrag))
            aus.append(teil)
        return "".join(aus)

    html = re.sub(r'<sc-for list="\{\{(\w+)\}\}" as="(\w+)"[^>]*>(.*?)</sc-for>', ausrollen, html, flags=re.S)
    html = re.sub(r'<script src="./support.js"></script>', "", html)
    html = re.sub(r'<link[^>]+fonts\.(googleapis|gstatic)\.com[^>]*>', "", html)
    html = re.sub(r'<script type="text/x-dc".*?</script>', "", html, flags=re.S)
    html = re.sub(r'src="/_blob/[0-9a-f]+"', 'src="file://%s"' % LOGO, html)
    schriften = []
    for familie, datei, gewichte in (("IBM Plex Sans", "ibm-plex-sans", (400, 500, 600)), ("IBM Plex Serif", "ibm-plex-serif", (400, 500))):
        for g in gewichte:
            for stil in ("normal", "italic"):
                schriften.append("@font-face{font-family:'%s';font-weight:%d;font-style:%s;src:url('file://%s/%s-latin-%d-%s.woff2') format('woff2')}"
                                 % (familie, g, stil, SCHRIFTEN, datei, g, stil))
    html = html.replace("<style>", "<style>" + "".join(schriften), 1)
    # Platzhalter-Cover der Vorlage (farbige Bücher) kennzeichnen: ihre Aufschriften sind kein Seitentext
    html = re.sub(r'<div style="([^"]*border-radius: [23]px [3-6]px [3-6]px [23]px[^"]*)"', r'<div data-cover="1" style="\1"', html)
    open(ziel, "w", encoding="utf-8").write(html)


if __name__ == "__main__":
    main(sys.argv[1], *(sys.argv[2:3]))
