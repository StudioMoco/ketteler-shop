"""Attrappe der Lizenz-Schnittstelle des Redaktionssystems (POST /api/shop/ereignisse).

Nur für die lokale Entwicklung. Bildet das Verhalten aus docs/lizenzen/shop-schnittstelle.md (Redaktions-Repository)
nach, dazu die neue Art „stornieren“ (Beschreibung in docs/plan.md dieses Repositorys):
Signatur (HMAC-SHA256 über Zeit + "." + Körper), Zeitfenster 300 s, Pflichtfelder, eine Lizenz je Bestellung und Produkt,
Wiederholung derselben ereignis_id ohne erneute Wirkung.

Steuerung für Tests (nicht Teil der echten Schnittstelle):
  GET  /_zustand          gespeicherte Ereignisse, Lizenzen, Protokoll, Zahl der Anfragen
  POST /_modus            {"modus": "normal" | "fehler503" | "langsam" | "ablehnen"}
                          (langsam = 15 s Pause vor der Antwort, ablehnen = 422 auf jede Anfrage)
  POST /_zuruecksetzen    alles löschen
"""

import hashlib
import hmac
import json
import os
import re
import threading
import time
from datetime import date, datetime, timedelta, timezone
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

GEHEIMNIS = os.environ.get("KETTELER_LIZENZ_GEHEIMNIS", "")
TOLERANZ = int(os.environ.get("SHOP_WEBHOOK_TOLERANZ", "300"))
DATEI = os.path.join(os.environ.get("ATTRAPPE_DATEN", "/tmp"), "zustand.json")
ARTEN = {"lizenz_anlegen", "probe_anlegen", "kuendigen", "stornieren"}
PRODUKTE = {"avr", "eichstaetter"}
EMAIL = re.compile(r"^[^@\s]+@[^@\s]+\.[^@\s]+$")
DATUM = re.compile(r"^\d{4}-\d{2}-\d{2}$")

sperre = threading.Lock()


def leer():
    return {"modus": "normal", "anfragen": 0, "ereignisse": {}, "lizenzen": [], "kunden": {}, "protokoll": []}


def laden():
    try:
        with open(DATEI, encoding="utf-8") as f:
            return json.load(f)
    except (OSError, ValueError):
        return leer()


def speichern(z):
    tmp = DATEI + ".tmp"
    with open(tmp, "w", encoding="utf-8") as f:
        json.dump(z, f, ensure_ascii=False, indent=1)
    os.replace(tmp, DATEI)


def heute():
    return date.today().isoformat()


def gueltiges_datum(wert):
    if not isinstance(wert, str) or not DATUM.match(wert):
        return False
    try:
        date.fromisoformat(wert)
        return True
    except ValueError:
        return False


def pruefe_felder(d):
    """Gibt (status, fehler) zurück; status None = in Ordnung."""
    fehler = {}
    if not isinstance(d, dict):
        return 422, {"body": ["JSON-Objekt erwartet"]}
    if not isinstance(d.get("ereignis_id"), str) or not d["ereignis_id"].strip() or len(d["ereignis_id"]) > 191:
        fehler["ereignis_id"] = ["Pflichtfeld (Text, höchstens 191 Zeichen)"]
    art = d.get("art")
    if art not in ARTEN:
        fehler["art"] = ["lizenz_anlegen, probe_anlegen, kuendigen oder stornieren"]
    if not isinstance(d.get("email"), str) or not EMAIL.match(d["email"]):
        fehler["email"] = ["gültige E-Mail-Adresse"]
    if "name" in d and d["name"] is not None and not isinstance(d["name"], str):
        fehler["name"] = ["Text"]
    if d.get("produkt") not in PRODUKTE:
        fehler["produkt"] = ["avr oder eichstaetter"]
    if "typ" in d and d["typ"] is not None and d["typ"] != "einzel":
        fehler["typ"] = ["Vom Shop nur einzel; IP-Lizenzen vergibt die Verwaltung"]
    for feld in ("beginn", "ende"):
        if d.get(feld) is not None and not gueltiges_datum(d.get(feld)):
            fehler[feld] = ["Datum JJJJ-MM-TT"]
    if art == "kuendigen" and not d.get("ende"):
        fehler["ende"] = ["Pflicht bei kuendigen"]
    if art != "probe_anlegen" and (not isinstance(d.get("bestellnummer"), str) or not d["bestellnummer"].strip()):
        fehler["bestellnummer"] = ["Pflichtfeld außer bei probe_anlegen"]
    if "grund" in d and d["grund"] is not None and (not isinstance(d["grund"], str) or len(d["grund"]) > 200):
        fehler["grund"] = ["Text, höchstens 200 Zeichen"]
    return (422, fehler) if fehler else (None, None)


def lizenz_zu(z, bestellnummer, produkt):
    for l in z["lizenzen"]:
        if l["bestellnummer"] == bestellnummer and l["produkt"] == produkt:
            return l
    return None


def protokoll(z, text, daten):
    z["protokoll"].append({"zeit": datetime.now(timezone.utc).isoformat(timespec="seconds"), "person": "System",
                           "aktion": text, "daten": daten})


def antwort_lizenz(l, kunde_id):
    return {"lizenz_id": l["id"], "kunde_id": kunde_id, "status": l["status"], "ende": l["ende"]}


def wirkung(z, d):
    """Führt das Ereignis aus. Gibt (status, körper) zurück."""
    art, email, produkt = d["art"], d["email"].lower(), d["produkt"]
    if art in ("lizenz_anlegen", "probe_anlegen"):
        kunde_id = z["kunden"].setdefault(email, len(z["kunden"]) + 1)
        if art == "lizenz_anlegen":
            vorhanden = lizenz_zu(z, d["bestellnummer"], produkt)
            if vorhanden:
                if vorhanden["email"] != email:
                    return 422, {"message": "E-Mail passt nicht zur Lizenz dieser Bestellung."}
                return 201, antwort_lizenz(vorhanden, vorhanden["kunde_id"])
        ende = None
        if art == "probe_anlegen":
            ende = d.get("ende") or (date.today() + timedelta(days=14)).isoformat()
        l = {"id": len(z["lizenzen"]) + 1, "kunde_id": kunde_id, "email": email, "name": d.get("name") or email,
             "produkt": produkt, "typ": "einzel", "status": "aktiv", "beginn": d.get("beginn") or heute(), "ende": ende,
             "bestellnummer": d.get("bestellnummer"), "herkunft": "shop",
             "weclapp_kundennummer": d.get("weclapp_kundennummer")}
        z["lizenzen"].append(l)
        protokoll(z, "Shop-Ereignis: " + ("Lizenz angelegt" if art == "lizenz_anlegen" else "Probe angelegt"),
                  {"lizenz_id": l["id"], "bestellnummer": l["bestellnummer"], "produkt": produkt})
        return 201, antwort_lizenz(l, kunde_id)

    l = lizenz_zu(z, d["bestellnummer"], produkt)
    if l is None:
        return 404, {"message": "Zu Bestellnummer und Produkt gibt es keine Lizenz."}
    if l["email"] != email:
        return 422, {"message": "E-Mail passt nicht zur Lizenz dieser Bestellung."}
    if art == "kuendigen":
        if l["status"] == "beendet":
            return 422, {"message": "Lizenz ist bereits beendet."}
        l["status"], l["ende"] = "gekuendigt", d["ende"]
        protokoll(z, "Shop-Ereignis: Kündigung", {"lizenz_id": l["id"], "ende": l["ende"]})
        return 200, antwort_lizenz(l, l["kunde_id"])
    # stornieren: sofort beenden; schon beendet = keine erneute Wirkung
    if l["status"] != "beendet":
        vorher = l["status"]
        l["status"], l["ende"] = "beendet", heute()
        protokoll(z, "Shop-Ereignis: Storno", {"lizenz_id": l["id"], "bestellnummer": l["bestellnummer"],
                                                 "vorher": vorher, "grund": d.get("grund")})
    return 200, antwort_lizenz(l, l["kunde_id"])


class Handler(BaseHTTPRequestHandler):
    server_version = "LizenzAttrappe/1"

    def senden(self, status, koerper=None):
        daten = b"" if koerper is None else json.dumps(koerper, ensure_ascii=False).encode()
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(daten)))
        self.end_headers()
        self.wfile.write(daten)

    def log_message(self, fmt, *args):
        print("%s %s" % (self.address_string(), fmt % args), flush=True)

    def do_GET(self):
        if self.path == "/_zustand":
            with sperre:
                return self.senden(200, laden())
        return self.senden(404, {"message": "Nicht gefunden"})

    def do_POST(self):
        laenge = int(self.headers.get("Content-Length") or 0)
        roh = self.rfile.read(laenge)
        if self.path == "/_modus":
            with sperre:
                z = laden()
                z["modus"] = json.loads(roh or b"{}").get("modus", "normal")
                speichern(z)
            return self.senden(200, {"modus": z["modus"]})
        if self.path == "/_zuruecksetzen":
            with sperre:
                speichern(leer())
            return self.senden(200, {"ok": True})
        if self.path != "/api/shop/ereignisse":
            return self.senden(404, {"message": "Nicht gefunden"})

        with sperre:
            z = laden()
            z["anfragen"] += 1
            modus = z["modus"]
            speichern(z)
        if modus == "fehler503":
            return self.senden(503, {"message": "Wartung (Attrappe)"})
        if modus == "langsam":
            time.sleep(15)
        if modus == "ablehnen":
            return self.senden(422, {"message": "Abgelehnt (Attrappe)", "errors": {"email": ["Testfall"]}})

        if not GEHEIMNIS:
            return self.senden(503, {"message": "Geheimnis nicht eingerichtet"})
        zeit = self.headers.get("X-Ketteler-Zeit", "")
        signatur = self.headers.get("X-Ketteler-Signatur", "")
        if not zeit.isdigit() or abs(time.time() - int(zeit)) > TOLERANZ:
            return self.senden(401)
        erwartet = "sha256=" + hmac.new(GEHEIMNIS.encode(), zeit.encode() + b"." + roh, hashlib.sha256).hexdigest()
        if not hmac.compare_digest(erwartet, signatur):
            return self.senden(401)
        try:
            d = json.loads(roh)
        except ValueError:
            return self.senden(422, {"message": "Kein gültiges JSON"})
        status, fehler = pruefe_felder(d)
        if status:
            return self.senden(status, {"message": "Felder ungültig", "errors": fehler})

        with sperre:
            z = laden()
            alt = z["ereignisse"].get(d["ereignis_id"])
            if alt:
                return self.senden(200, dict(alt["koerper"], wiederholt=True))
            status, koerper = wirkung(z, d)
            if status < 300:
                z["ereignisse"][d["ereignis_id"]] = {"status": status, "koerper": koerper, "daten": d}
            speichern(z)
        return self.senden(status, koerper)


if __name__ == "__main__":
    os.makedirs(os.path.dirname(DATEI), exist_ok=True)
    print("Lizenz-Attrappe auf Port 8090", flush=True)
    ThreadingHTTPServer(("0.0.0.0", 8090), Handler).serve_forever()
