"""Hilfen für die End-to-End-Tests gegen die lokale Umgebung (Shop, ZITADEL, Lizenz-Attrappe) oder gegen Staging.

Nur Python-Standardbibliothek. Zugangsdaten liest das Skript aus der .env des Repositorys (lokal) bzw. zur Laufzeit per SSH
aus /opt/ketteler-shop/.env auf dem Server (KETTELER_ZIEL=staging); sie werden nie ausgegeben oder gespeichert.
Staging: Passwortschutz (Basic Auth) für alles außer /api/, Datenbank und Konsole per SSH, Attrappe per SSH-Tunnel.
"""

import atexit
import base64
import http.cookiejar
import json
import os
import re
import shlex
import socket
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request
from html.parser import HTMLParser

WURZEL = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))

# Docker Desktop legt „docker“ je nach Einstellung an verschiedene Stellen; die Tests sollen auch ohne make laufen
os.environ["PATH"] = os.pathsep.join([os.environ.get("PATH", ""), "/usr/local/bin", os.path.expanduser("~/.docker/bin"),
                                      "/Applications/Docker.app/Contents/Resources/bin"])


ZIEL = os.environ.get("KETTELER_ZIEL", "lokal")
STAGING = ZIEL == "staging"
SERVER = os.environ.get("KETTELER_SERVER", "ketteler")          # SSH-Name des Staging-Servers (~/.ssh/config)
STG = "ketteler-shop-stg"                                        # Compose-Projekt auf dem Server
STG_ATTRAPPE_PORT = 18090                                        # 127.0.0.1 auf dem Server, nur für den SSH-Tunnel


def env_zeilen(text):
    werte = {}
    for zeile in text.splitlines():
        zeile = zeile.strip()
        if zeile and not zeile.startswith("#") and "=" in zeile:
            k, v = zeile.split("=", 1)
            werte[k] = v
    return werte


def env():
    if STAGING:
        return env_zeilen(subprocess.run(["ssh", SERVER, "cat /opt/ketteler-shop/.env"], capture_output=True, text=True, check=True).stdout)
    with open(os.path.join(WURZEL, ".env"), encoding="utf-8") as f:
        return env_zeilen(f.read())


ENV = env()
SHOP = ENV.get("APP_URL", "http://localhost:8000").rstrip("/")
ZITADEL = ENV.get("KETTELER_ZITADEL_URL", "http://localhost:8080").rstrip("/")
MAILPIT = ENV.get("KETTELER_MAILPIT_URL", "").rstrip("/") or "http://localhost:" + ENV.get("MAILPIT_PORT", "8025")
ATTRAPPE = "http://127.0.0.1:%d" % STG_ATTRAPPE_PORT if STAGING else "http://localhost:" + ENV.get("ATTRAPPE_PORT", "8090")
GESCHUETZT = {urllib.parse.urlparse(u).hostname for u in (SHOP, ZITADEL, MAILPIT)} if STAGING else set()


def tunnel():
    """Staging: SSH-Tunnel zur Lizenz-Attrappe (auf dem Server nur an 127.0.0.1 gebunden)."""
    with socket.socket() as s:
        if s.connect_ex(("127.0.0.1", STG_ATTRAPPE_PORT)) == 0:
            return
    p = subprocess.Popen(["ssh", "-N", "-o", "ExitOnForwardFailure=yes", "-L", "%d:127.0.0.1:%d" % (STG_ATTRAPPE_PORT, STG_ATTRAPPE_PORT), SERVER])
    atexit.register(p.terminate)
    for _ in range(50):
        with socket.socket() as s:
            if s.connect_ex(("127.0.0.1", STG_ATTRAPPE_PORT)) == 0:
                return
        time.sleep(0.2)
    raise RuntimeError("SSH-Tunnel zur Attrappe kommt nicht zustande")


class Passwortschutz(urllib.request.BaseHandler):
    """Staging: Basic Auth für Shop, Anmeldung und Mailpit, außer für /api/ (dort meldet sich der Aufrufer selbst an)."""
    handler_order = 400

    def __init__(self):
        roh = "%s:%s" % (ENV.get("STG_BASICAUTH_BENUTZER", ""), ENV.get("STG_BASICAUTH_PASSWORT", ""))
        self.kopf = "Basic " + base64.b64encode(roh.encode()).decode()

    def https_request(self, req):
        teile = urllib.parse.urlparse(req.full_url)
        if teile.hostname in GESCHUETZT and not teile.path.startswith("/api/") and not req.has_header("Authorization"):
            req.add_unredirected_header("Authorization", self.kopf)
        return req

    http_request = https_request


if STAGING:
    tunnel()
    urllib.request.install_opener(urllib.request.build_opener(Passwortschutz()))


def konsole(*befehl, pruefen=False):
    """bin/console im Shop-Container (lokal: docker compose, Staging: per SSH). Gibt die Ausgabe zurück."""
    if STAGING:
        aufruf = ["ssh", SERVER, "docker exec -u www-data %s-web-1 php bin/console %s" % (STG, " ".join(shlex.quote(b) for b in befehl))]
    else:
        aufruf = ["docker", "compose", "exec", "-T", "web", "bin/console", *befehl]
    return subprocess.run(aufruf, cwd=WURZEL, capture_output=True, text=True, check=pruefen).stdout


class Formulare(HTMLParser):
    """Sammelt Formulare (action, method, Felder) und Texte einer Seite."""

    def __init__(self):
        super().__init__()
        self.formulare = []
        self._aktuell = None
        self.text = []

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == "form":
            self._aktuell = {"action": a.get("action", ""), "method": (a.get("method") or "get").upper(), "felder": {},
                             "attrs": a}
            self.formulare.append(self._aktuell)
        elif tag in ("input", "button", "select", "textarea") and self._aktuell is not None:
            name = a.get("name")
            if not name:
                return
            typ = (a.get("type") or "").lower()
            if tag == "button" or typ in ("submit", "button"):
                return
            if typ in ("checkbox", "radio") and "checked" not in a:
                return
            self._aktuell["felder"].setdefault(name, a.get("value", ""))

    def handle_endtag(self, tag):
        if tag == "form":
            self._aktuell = None

    def handle_data(self, data):
        if data.strip():
            self.text.append(data.strip())


class LokaleCookies(http.cookiejar.DefaultCookiePolicy):
    """Wie ein Browser: Cookies mit Domain=localhost annehmen (Python lehnt Domains ohne Punkt sonst ab)."""

    @staticmethod
    def _host(request):
        return urllib.parse.urlparse(request.get_full_url()).hostname or ""

    def set_ok_domain(self, cookie, request):
        domain = cookie.domain.lstrip(".")
        host = self._host(request)
        return host == domain or host.endswith("." + domain) or domain == "localhost.local" and host == "localhost"

    def return_ok_domain(self, cookie, request):
        domain = cookie.domain.lstrip(".").removesuffix(".local") if cookie.domain.endswith("localhost.local") else cookie.domain.lstrip(".")
        host = self._host(request)
        return host == domain or host.endswith("." + domain)

    def domain_return_ok(self, domain, request):
        return True


class OhneWeiterleitung(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class Browser:
    """Minimaler Browser: Cookies, Weiterleitungen einzeln verfolgbar, Formulare absenden."""

    def __init__(self):
        self.cookies = http.cookiejar.CookieJar(LokaleCookies())
        zusatz = [Passwortschutz()] if STAGING else []
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies), OhneWeiterleitung(), *zusatz)
        self.verlauf = []
        self.url = None
        self.status = None
        self.html = ""

    def anfrage(self, methode, url, daten=None, kopf=None, folgen=True, max_schritte=25):
        for _ in range(max_schritte):
            koerper = None
            k = {"User-Agent": "ketteler-e2e", "Accept": "text/html,application/json"}
            k.update(kopf or {})
            if daten is not None:
                koerper = urllib.parse.urlencode(daten, doseq=True).encode()
                k["Content-Type"] = "application/x-www-form-urlencoded"
            req = urllib.request.Request(url, data=koerper, headers=k, method=methode)
            try:
                antwort = self.opener.open(req, timeout=60)
                status, ort, inhalt = antwort.status, None, antwort.read()
            except urllib.error.HTTPError as e:
                status, ort, inhalt = e.code, e.headers.get("Location"), e.read()
            self.verlauf.append((methode, url, status))
            self.url, self.status = url, status
            if status in (301, 302, 303, 307, 308) and ort:
                url = urllib.parse.urljoin(url, ort)
                if not folgen:
                    self.url = url
                    return self
                if status in (301, 302, 303):
                    methode, daten = "GET", None
                continue
            self.html = inhalt.decode("utf-8", "replace")
            return self
        raise RuntimeError("Zu viele Weiterleitungen: " + " -> ".join(u for _, u, _ in self.verlauf[-6:]))

    def get(self, url, **kw):
        return self.anfrage("GET", urllib.parse.urljoin(self.url or SHOP, url), **kw)

    def post(self, url, daten, **kw):
        return self.anfrage("POST", urllib.parse.urljoin(self.url or SHOP, url), daten=daten, **kw)

    def formulare(self):
        p = Formulare()
        p.feed(self.html)
        return p.formulare

    def formular(self, action_teil):
        for f in self.formulare():
            if action_teil in f["action"]:
                return f
        return None

    def absenden(self, action_teil, felder=None):
        f = self.formular(action_teil)
        if f is None:
            raise AssertionError("Formular %s nicht gefunden auf %s" % (action_teil, self.url))
        daten = dict(f["felder"])
        daten.update(felder or {})
        return self.anfrage(f["method"], urllib.parse.urljoin(self.url, f["action"]), daten=daten)

    def text(self):
        p = Formulare()
        p.feed(self.html)
        return " ".join(p.text)

    def enthaelt(self, muster):
        return re.search(muster, self.html) is not None


def zitadel_anmelden(b, email, passwort):
    """Auf der ZITADEL-Anmeldeseite (Login v1) anmelden. Endet dort, wohin ZITADEL danach weiterleitet."""
    if "/ui/login/" not in b.url:
        raise AssertionError("Nicht auf der ZITADEL-Anmeldeseite: " + b.url)
    if b.formular("/ui/login/userselection"):
        # Kontoauswahl nach dem Abmelden: „Anderer Benutzer“
        b.absenden("/ui/login/userselection", {"userID": "0"})
    if b.formular("/ui/login/loginname"):
        b.absenden("/ui/login/loginname", {"loginName": email})
    b.absenden("/ui/login/password", {"password": passwort})
    # Hinweis zur Zwei-Faktor-Einrichtung überspringen, falls ZITADEL ihn zeigt
    for _ in range(3):
        if b.formular("/ui/login/mfa/init/skip"):
            b.absenden("/ui/login/mfa/init/skip")
        else:
            break
    return b


def sql(abfrage):
    """Abfrage in der Shop-Datenbank (lokal Container database, Staging per SSH). Gibt Zeilen als Listen zurück."""
    mysql = 'MYSQL_PWD="$MYSQL_PASSWORD" mysql --default-character-set=utf8mb4 -ushopware shopware -N -B -r'
    if STAGING:
        befehl = ["ssh", SERVER, "docker exec -i %s-datenbank-1 sh -c %s" % (STG, shlex.quote(mysql))]
    else:
        befehl = ["docker", "compose", "exec", "-T", "database", "sh", "-c", mysql]
    aus = subprocess.run(befehl, cwd=WURZEL, input=abfrage, capture_output=True, text=True, check=True).stdout
    return [z.split("\t") for z in aus.splitlines() if z]


def kontext_token(methode, url, daten, kopf):
    """Wie json_anfrage, gibt aber das neue sw-context-token aus dem Antwort-Kopf zurück (z. B. nach dem Login)."""
    k = {"Content-Type": "application/json", "Accept": "application/json"}
    k.update(kopf or {})
    req = urllib.request.Request(url, data=json.dumps(daten).encode(), headers=k, method=methode)
    with urllib.request.urlopen(req, timeout=60) as a:
        return a.headers.get("sw-context-token")


def json_anfrage(methode, url, daten=None, kopf=None):
    k = {"Content-Type": "application/json", "Accept": "application/json"}
    k.update(kopf or {})
    req = urllib.request.Request(url, data=None if daten is None else json.dumps(daten).encode(), headers=k,
                                 method=methode)
    try:
        with urllib.request.urlopen(req, timeout=60) as a:
            inhalt = a.read()
            try:
                return a.status, (json.loads(inhalt) if inhalt else None)
            except ValueError:
                return a.status, inhalt.decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        inhalt = e.read()
        try:
            return e.code, json.loads(inhalt)
        except ValueError:
            return e.code, inhalt.decode("utf-8", "replace")


class AdminApi:
    """Shopware Admin-API mit dem lokalen Admin-Benutzer (Passwort aus .env)."""

    def __init__(self):
        status, antwort = json_anfrage("POST", SHOP + "/api/oauth/token", {
            "grant_type": "password", "client_id": "administration", "scopes": "write",
            "username": "admin", "password": ENV["SHOP_ADMIN_PASSWORT"]})
        assert status == 200, "Admin-API-Anmeldung fehlgeschlagen: %s" % status
        self.kopf = {"Authorization": "Bearer " + antwort["access_token"]}

    def __call__(self, methode, pfad, daten=None):
        return json_anfrage(methode, SHOP + "/api" + pfad, daten, self.kopf)


class Ergebnis:
    def __init__(self):
        self.gut, self.schlecht, self.uebersprungen = 0, [], []

    def ueberspringe(self, name, grund):
        """Nicht ausgeführt; zählt weder als bestanden noch als fehlgeschlagen."""
        self.uebersprungen.append((name, grund))
        print("  übersprungen " + name + "  (" + grund + ")", flush=True)

    def pruefe(self, bedingung, name, info=""):
        if bedingung:
            self.gut += 1
            print("  ok    " + name, flush=True)
        else:
            self.schlecht.append(name)
            print("  FEHLER " + name + (("  (" + info + ")") if info else ""), flush=True)

    def ende(self):
        print("\n%d bestanden, %d fehlgeschlagen, %d übersprungen" % (self.gut, len(self.schlecht), len(self.uebersprungen)))
        for s in self.schlecht:
            print("  - " + s)
        return 0 if not self.schlecht else 1


# Vermerke, die nur intern stehen dürfen (Plan, Prüfliste, Verwaltung), nie in Texten für Kunden
INTERNE_VERMERKE = ("zu prüfen", "zu ergänzen", "von Ketteler", "Entwurf,")


def interne_vermerke(text):
    return [v for v in INTERNE_VERMERKE if v in text]
