"""Schreibt docs/design/bildschirmfotos/README.md: Übersicht aller Bildschirmfotos nach Seite, mit Vorlage, lokal,
Staging und Vergleich (nebeneinander) bei 1440 und 390 px. Aufruf: python3 tests/browser/bilder_uebersicht.py"""

import os
import re

ORDNER = os.path.join(os.path.dirname(__file__), "..", "..", "docs", "design", "bildschirmfotos")
SEITEN = [
    ("startseite", "Startseite", "startseite-entwurf.dc.html"),
    ("werkseite-avr", "Werkseite Kompendium AVR Caritas", "werkseite-avr-entwurf.dc.html"),
    ("werkseite-zmv", "Werkseite Zeitschrift ZMV", "werkseite-zmv-entwurf.dc.html"),
    ("werkseite-eichstaetter", "Werkseite Eichstätter Kommentar", "werkseite-eichstaetter-entwurf.dc.html"),
    ("story-mav-einstieg", "MAV Einstieg (Aktuell & interessant)", "story-mav-einstieg-entwurf.dc.html"),
    ("story-mav-grundwissen", "MAV Einstieg: Grundwissen/Basisausstattung", "story-mav-literatur-entwurf.dc.html"),
    ("story-mav-arbeitshilfen", "MAV Einstieg: Praktische Arbeitshilfen (ohne Vorlage)", None),
    ("story-mav-literatur", "MAV Einstieg: Themenbezogene Literatur (ohne Vorlage)", None),
    ("story-mav-links", "MAV Einstieg: Hilfreiche Links (ohne Vorlage)", None),
    ("story-fachtagung", "ZMV Fachtagung", "story-fachtagung-entwurf.dc.html"),
    ("story-interviews", "Interviews Autoren & Schriftleiter", "story-interviews-entwurf.dc.html"),
    ("story-zmv-aktuell", "ZMV aktuell (ohne Vorlage)", None),
    ("story-verlag", "Verlag (ohne Vorlage)", None),
    ("story-links", "Links (ohne Vorlage)", None),
    ("warenkorb", "Warenkorb", None),
    ("kasse-anmeldung", "Kasse: Anmeldung oder Gast", None),
    ("kasse-uebersicht", "Kasse: Bestellübersicht", None),
    ("konto", "Kundenkonto: Übersicht", None),
    ("konto-bestellungen", "Kundenkonto: Bestellungen", None),
    ("mail-bestellbestaetigung", "Mail: Bestellbestätigung (aus Mailpit)", None),
]


def main():
    dateien = set(os.listdir(ORDNER))
    zeilen = ["# Bildschirmfotos", "",
              "Erzeugt von den Browsertests (`make test-browser BILDER=docs/design/bildschirmfotos`, `make test-staging BILDER=…`,",
              "`make abnahme-staging`). „Vergleich“ zeigt Vorlage und Shop nebeneinander. Die farbigen Bücher der Vorlagen sind",
              "Platzhalter für echte Cover; der Shop zeigt bis zur Lieferung der Cover ruhige Sand-Flächen mit dem Titel.", "",
              "| Seite | Vorlage | 1440 px | 390 px |", "|---|---|---|---|"]
    for schluessel, name, vorlage in SEITEN:
        zellen = []
        for breite in ("1440", "390"):
            teile = []
            for art, titel in (("vergleich-staging", "Vergleich Staging"), ("staging", "Staging"), ("vergleich-lokal", "Vergleich lokal"), ("lokal", "lokal")):
                kandidat = "%s-%s-%s.png" % (art, schluessel, breite) if schluessel != "startseite" or "vergleich" not in art else "%s-%s.png" % (art, breite)
                if kandidat in dateien:
                    teile.append("[%s](%s)" % (titel, kandidat))
            zellen.append(" · ".join(teile) or "fehlt")
        zeilen.append("| %s | %s | %s | %s |" % (name, "`docs/design/%s`" % vorlage if vorlage else "keine", zellen[0], zellen[1]))
    weitere = sorted(d for d in dateien if re.match(r"(dropdown|produktkarten)-", d))
    if weitere:
        zeilen += ["", "Weitere: " + ", ".join("[%s](%s)" % (d[:-4], d) for d in weitere) + "."]
    zeilen += ["", "Lighthouse (Staging): [lighthouse.md](../lighthouse.md)."]
    open(os.path.join(ORDNER, "README.md"), "w", encoding="utf-8").write("\n".join(zeilen) + "\n")
    print("README.md geschrieben")


if __name__ == "__main__":
    main()
