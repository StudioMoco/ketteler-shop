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

    def ausrollen(text, werte):
        """sc-for-Listen ausrollen, auch verschachtelt (list="{{t.acro}}" greift auf den äußeren Eintrag zu)."""
        aus, pos = [], 0
        start = re.compile(r'<sc-for list="\{\{([\w.]+)\}\}" as="(\w+)"[^>]*>')
        while True:
            m = start.search(text, pos)
            if not m:
                aus.append(text[pos:])
                return "".join(aus)
            aus.append(text[pos:m.start()])
            tiefe, i = 1, m.end()
            while tiefe:
                a, z = text.find("<sc-for", i), text.find("</sc-for>", i)
                if a != -1 and a < z:
                    tiefe, i = tiefe + 1, a + 7
                else:
                    tiefe, i = tiefe - 1, z + 9
            inhalt = text[m.end():i - 9]
            liste = werte
            for teil in m.group(1).split("."):
                liste = liste[teil]
            for eintrag in liste:
                teil = ausrollen(inhalt, dict(werte, **{m.group(2): eintrag}))
                if isinstance(eintrag, dict):
                    for k, v in eintrag.items():
                        if not isinstance(v, (list, dict)):
                            teil = teil.replace("{{%s.%s}}" % (m.group(2), k), str(v))
                else:
                    teil = teil.replace("{{%s}}" % m.group(2), str(eintrag))
                aus.append(teil)
            pos = i

    html = ausrollen(html, daten)
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
