"""Wandelt die Rechtstexte aus content/rechtstexte/*.md (Rohmaterial aus dem bisherigen Shop) in HTML für Shopware um.

Aufruf nach Änderungen an den Texten: python3 tools/rechtstexte_html.py, danach make einrichten.
Der Wortlaut bleibt unverändert.
Nur Standardbibliothek. Unterstützt: Überschriften, Absätze (einfache Zeilenumbrüche = <br>), Listen, Tabellen,
**fett**, *kursiv*, Links, *** als Trenner.
"""

import html
import os
import re

WURZEL = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
QUELLE = os.path.join(WURZEL, "content", "rechtstexte")
ZIEL = os.path.join(WURZEL, "custom", "plugins", "KettelerEinrichtung", "src", "Resources", "daten", "rechtstexte")
TEXTE = ["impressum", "agb", "widerruf", "datenschutz", "versand-lieferung", "preise-zmv", "agb-anzeigen"]
# Kein Vermerk auf der Seite selbst (auch nicht im Quelltext): Kunden sehen sie. „Entwurf, von Ketteler zu prüfen“
# steht im Seitennamen in der Verwaltung, in docs/plan.md und in docs/rechtstexte-pruefliste.md.


def inline(text):
    text = html.escape(text, quote=False)
    text = re.sub(r"\[([^\]]+)\]\(([^)\s]+)\)", lambda m: '<a href="%s">%s</a>' % (html.escape(m.group(2)), m.group(1)), text)
    text = re.sub(r"\*\*(.+?)\*\*", r"<strong>\1</strong>", text, flags=re.S)
    text = re.sub(r"(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])", r"<em>\1</em>", text)
    return text


def umwandeln(markdown):
    markdown = re.sub(r"\A---\n.*?\n---\n", "", markdown, flags=re.S)
    markdown = re.sub(r"<!--.*?-->", "", markdown, flags=re.S)
    aus, absatz, liste, tabelle = [], [], None, []

    def schliessen():
        nonlocal absatz, liste, tabelle
        if absatz:
            # erst zusammenfügen, dann umwandeln: Fettdruck kann über mehrere Zeilen gehen
            aus.append("<p>" + inline("\n".join(absatz)).replace("\n", "<br>") + "</p>")
            absatz = []
        if liste:
            typ, punkte = liste
            aus.append("<%s>%s</%s>" % (typ, "".join("<li>%s</li>" % inline(p) for p in punkte), typ))
            liste = None
        if tabelle:
            zeilen = [z for z in tabelle if not re.match(r"^\|\s*:?-{2,}", z)]
            teile = [[inline(c.strip()) for c in z.strip().strip("|").split("|")] for z in zeilen]
            kopf = "<tr>%s</tr>" % "".join("<th>%s</th>" % c for c in teile[0])
            rumpf = "".join("<tr>%s</tr>" % "".join("<td>%s</td>" % c for c in z) for z in teile[1:])
            aus.append('<table class="table">%s%s</table>' % (kopf, rumpf))
            tabelle = []

    for zeile in markdown.splitlines():
        roh = zeile.rstrip()
        if not roh.strip():
            schliessen()
            continue
        if roh.strip() in ("***", "---", "* * *"):
            schliessen()
            aus.append("<hr>")
            continue
        kopf = re.match(r"^(#{1,6})\s+(.*)$", roh)
        if kopf:
            schliessen()
            ebene = min(len(kopf.group(1)) + 1, 6)
            aus.append("<h%d>%s</h%d>" % (ebene, inline(kopf.group(2)), ebene))
            continue
        if roh.lstrip().startswith("|"):
            if absatz or liste:
                schliessen()
            tabelle.append(roh)
            continue
        punkt = re.match(r"^\s*(?:[-*+]|(\d+)\.)\s+(.*)$", roh)
        if punkt and not roh.startswith("**"):
            typ = "ol" if punkt.group(1) else "ul"
            if absatz or tabelle:
                schliessen()
            if liste and liste[0] != typ:
                schliessen()
            liste = liste or (typ, [])
            liste[1].append(punkt.group(2))
            continue
        if liste or tabelle:
            schliessen()
        absatz.append(roh.strip())
    schliessen()
    return "\n".join(aus)


def main():
    os.makedirs(ZIEL, exist_ok=True)
    for name in TEXTE:
        with open(os.path.join(QUELLE, name + ".md"), encoding="utf-8") as f:
            inhalt = f.read()
        ergebnis = umwandeln(inhalt)
        with open(os.path.join(ZIEL, name + ".html"), "w", encoding="utf-8") as f:
            f.write(ergebnis + "\n")
        print("%s.html: %d Zeichen" % (name, len(ergebnis)))


if __name__ == "__main__":
    main()
