# Shop auf Staging: Bestand, Plan, Auslieferung

Stand 12.10.2026, Phase 1 (nur gelesen, nichts auf dem Server geändert). Server: Staging `ketteler-01`, 195.201.26.80,
Zugang nur als `deploy` mit dem Schlüssel des Geräts. Auf dem Server testet Ketteler das Redaktionssystem; dessen Dienste,
Datenbank, ZITADEL, Caddy und Konfiguration bleiben unverändert.

## 1. Bestand (gemessen am 12.10.2026, 21:04 Uhr)

| | |
|---|---|
| System | Ubuntu 24.04.4 LTS, Kernel 6.8, seit 74 Tagen ohne Neustart |
| CPU | 2 vCPU (AMD EPYC Genoa), Hetzner CPX22 |
| Arbeitsspeicher | 3,7 GB, belegt 2,2 GB, **verfügbar 1,6 GB, kein Swap** |
| Platte | 75 GB, belegt 13 GB, frei 60 GB |
| Docker | 29.6.2, Bilder 4,4 GB (1,6 GB nicht mehr benutzt), Volumes 2,8 GB; keine `/etc/docker/daemon.json` (Protokolle ohne Größenbegrenzung) |
| Offene Ports | 22, 80, 443 (Caddy), 127.0.0.1:8090 (Redaktion intern) |

**Container (10, alle von der Redaktion):**

| Container | Speicher | Grenze | Läuft seit |
|---|---|---|---|
| ketteler-db-1 (MySQL 8.0) | 940 MB | keine | 30.07. |
| ketteler-login-stg-stg-zitadel-login-1 | 146 MB | 256 MB | 08.10. |
| ketteler-login-stg-stg-zitadel-api-1 | 111 MB | 512 MB | 07.10. |
| avr-analyse-db (MariaDB) | 115 MB | keine | 28.09. |
| eichst-analyse-db (MariaDB) | 89 MB | keine | 29.09. |
| ketteler-login-stg-stg-zitadel-db-1 | 64 MB | 384 MB | 02.10. |
| ketteler-stg-stg-app-1 (PHP) | 62 MB | keine | 07.10. |
| ketteler-login-stg-stg-mailpit-1 | 21 MB | 96 MB | 07.10. |
| ketteler-stg-stg-web-1 (Caddy intern) | 15 MB | keine | 05.10. |
| ketteler-caddy-1 (Caddy, Port 80/443) | 14 MB | keine | **12.10., 16:42 Uhr** (neu gestartet, nicht durch den Shop) |

**Caddy:** eigener Container `ketteler-caddy-1` aus dem Compose-Projekt `ketteler` (`/opt/ketteler/docker-compose.yml`),
Netz `ketteler_web`. Konfiguration: **eine einzige Datei** `/opt/ketteler/Caddyfile` (Einzeldatei-Bind-Mount, Teil des
Git-Stands der Redaktion, `main` 1a5016c, SHA-256 `55e728bd…7378`), dazu das Volume `ketteler_caddy_config` (u. a.
`basicauth-staging.caddy`). Zertifikate holt dieser Caddy selbst bei Let's Encrypt (Volume `ketteler_caddy_data`). Es gibt
**keinen** Ordner, aus dem Caddy zusätzliche Einträge nachlädt.

**Zeitgesteuert (crontab deploy, Redaktion):** 03:10 ZITADEL-Sicherung, 03:20 Staging-Sicherung, 07:00 Lizenzhinweise,
07:15 Auftragserinnerung, alle 15 Minuten Rückläufer, alle 5 Minuten Überwachung von Live (`~/ueberwachung/live-pruefen.py`).

## 2. Passt der Shop dazu?

Gemessen lokal im Leerlauf: ca. 620 MB für den ganzen Shop-Stack. Unter Last mehr: PHP bis 512 MB je Anfrage, Datenbank-
Puffer, Bildverarbeitung beim ersten weclapp-Abgleich (≈ 350 Artikel mit Bildern).

| Dienst | Grenze (Vorschlag) | üblich |
|---|---|---|
| Shop (PHP-FPM + Caddy, Produktionsbild) | 1.024 MB | 200 bis 600 MB |
| Worker (Warteschlange, geplante Aufgaben) | 384 MB | 60 bis 200 MB |
| MySQL 8.4 (Puffer 256 MB) | 640 MB | 350 bis 500 MB |
| ZITADEL + Postgres (Test-Anmeldung) | 384 + 192 MB | 150 MB |
| Mailpit, Lizenz-Attrappe, Sicherung | 64 + 48 + 64 MB | 30 MB |
| **Summe** | **≈ 2,8 GB** | **≈ 0,8 bis 1,6 GB** |

**Einschätzung:** Mit 1,6 GB verfügbar und **ohne Swap** reicht das **nicht sicher**. Schon im üblichen Betrieb wäre der
Server fast voll; eine Lastspitze im Shop (Abgleich, Theme-Übersetzung) kann den Kernel zwingen, einen Prozess zu beenden,
und das könnte die MySQL der Redaktion treffen (sie hat keine Grenze). Der Kernel meldet schon heute vereinzelt Speicherdruck.

**Empfehlung (Atilla entscheidet):**
1. **Server vorher auf CPX32 umskalieren** (4 vCPU, 8 GB, „nur CPU und RAM“, damit Zurückstufen möglich bleibt), wie in
   `docs/plan.md` schon vorgesehen. Dauer wenige Minuten, der Server ist dabei aus: **mit Ketteler abstimmen**, weil gerade
   getestet wird. Bestellung bei Hetzner macht Atilla.
2. Zusätzlich **2 GB Swap-Datei** als Polster (Systemänderung, braucht ausdrückliches Ja).
3. Ohne OpenSearch (die eingebaute Suche reicht für einige hundert Artikel), Speichergrenzen für jeden Shop-Container wie
   oben, Protokollgrenzen je Dienst (3 × 10 MB).
4. Optional, nur durch die Redaktion zu entscheiden: die beiden Analyse-Datenbanken (`avr-analyse-db`,
   `eichst-analyse-db`, zusammen ≈ 200 MB) anhalten, wenn sie nicht mehr gebraucht werden. Der Shop fasst sie nicht an.

Ohne Umskalieren würde ich Phase 2 nicht beginnen.

## 3. Adressen und DNS

Die Zone `mocodev.de` liegt bei Hetzner (Nameserver `ns1.your-server.de`, `ns.second-ns.com`, `ns3.second-ns.de`, also
Hetzner konsoleH, nicht Hetzner DNS). Die vorhandenen Staging-Adressen heißen `staging-…` und haben nur A-Einträge.

**Vorschlag** (passend zu `staging-shop.mocodev.de`, das Atilla in der zweiten Runde bestätigt hat):

| Adresse | Zweck |
|---|---|
| `staging-shop.mocodev.de` | Shop und Verwaltung (`/admin`), Admin-API für weclapp (`/api`) |
| `staging-shop-login.mocodev.de` | Test-ZITADEL des Shops (Anmeldung, Konsole) |
| `staging-shop-mail.mocodev.de` | Mailpit des Shops |

**DNS-Einträge für Atilla** (in der konsoleH, Zone mocodev.de):

```
staging-shop        IN A  195.201.26.80
staging-shop-login  IN A  195.201.26.80
staging-shop-mail   IN A  195.201.26.80
```

TTL wie die übrigen Einträge (Vorgabe 3600). Keine AAAA-Einträge, wie bei den anderen Staging-Adressen. Alternative Namen
aus dem Auftrag (`shop.mocodev.de`, `shop-login…`, `shop-mail…`) gehen genauso; dann diese drei Namen eintragen.

## 4. Aufbau auf dem Server (Plan)

- Ordner `/opt/ketteler-shop` (Besitzer deploy): `repo/` (Git-Stand aus GitHub, nur lesend geholt), `.env` (600, nur auf
  dem Server), `sicherungen/`, `protokolle/`.
- Eigenes Compose-Projekt `ketteler-shop-stg`, eigenes Netz `ketteler-shop-stg`, eigene Volumes (Datenbank, Medien,
  Dateien, ZITADEL, Mailpit). Nur der vordere Shop-Container hängt zusätzlich im Netz `ketteler_web`, damit der vorhandene
  Caddy ihn erreicht (Alias `shop-stg-front`).
- Dienste: `front` (eigener kleiner Caddy des Shops: Passwortschutz, noindex, robots.txt, Weiterleitung an die drei
  Shop-Dienste), `web` (Shopware im Produktionsbild `ghcr.io/shopware/docker-base:8.3-caddy`), `worker`, `datenbank`
  (MySQL 8.4), `zitadel` + `zitadel-db`, `mailer` (Mailpit ohne Weiterleitung), `lizenz-attrappe`, `sicherung`.
- **Passwortschutz, noindex, robots.txt im Shop-eigenen `front`**, nicht im Caddy der Redaktion. Eigenes Passwort, nur in
  der `.env` und in Atillas Passwortmanager. Ausnahme vom Passwortschutz nur für `/api/` (Admin-API, die weclapp mit eigener
  Anmeldung nutzt). Wenn weclapp feste Adressen nennt, wird `/api/`
  zusätzlich auf diese Adressen beschränkt.
- Mails: Shopware und ZITADEL senden nur an das Mailpit im Stack; Mailpit hat keine Weiterleitung. Nichts verlässt den
  Server.
- Lizenzen gegen die Attrappe im Stack, Anmeldung gegen den Test-ZITADEL im Stack, VIES über die offizielle Schnittstelle
  (`KETTELER_VIES_URL` leer), PayPal nur Sandbox und nur mit Zugangsdaten.

**Die eine gemeinsame Stelle, der Caddy der Redaktion** (offene Frage, siehe Abschnitt 9): Damit Port 443 die drei
Shop-Adressen an `front` weitergibt, braucht der vorhandene Caddy drei Einträge. Weil seine Konfiguration eine einzige,
versionierte Datei der Redaktion ist, wäre jede Ergänzung eine Änderung an der Redaktion. Sauber getrennt ginge es so:

- **Variante A (empfohlen):** Einmalig in der Redaktion (über ihren eigenen Ablauf mit Commit und Tag, durch die Sitzung
  der Redaktion oder mit Atillas ausdrücklichem Ja) eine Zeile `import /etc/caddy/sites/*.caddy` und einen Ordner-Mount
  `/opt/caddy-sites:/etc/caddy/sites:ro` ergänzen. Danach legt der Shop nur seine eigene Datei
  `/opt/caddy-sites/ketteler-shop.caddy` ab; prüfen mit `caddy validate`, dann `caddy reload` (kein Neustart). Der
  Ordner-Mount verlangt einmalig ein Neuerstellen des Caddy-Containers (kurze Unterbrechung von Redaktion und Login,
  Sekunden), daher mit Ketteler abstimmen.
- **Variante B:** Drei Site-Blöcke direkt in `/opt/ketteler/Caddyfile` (in place, wie in der Redaktion üblich), Commit im
  Redaktions-Repository. Kein Neustart nötig, nur `caddy reload`. Nachteil: Shop-Zeilen leben in der Redaktion.

## 5. Auslieferung (Plan, Skripte kommen mit Phase 2 ins Repository)

- **Tags:** `shop-staging-JJJJ-MM-TT.N` auf GitHub. Ausgeliefert wird nur ein Tag, nie ein Branch.
- **Schlüssel:** eigener, nur lesender Deploy-Key des Servers für `StudioMoco/ketteler-shop` (`~deploy/.ssh/github_ketteler_shop_ro`,
  in GitHub unter Settings > Deploy keys ohne Schreibrecht; anlegen macht Atilla nach Freigabe des Fingerabdrucks).
- **`ops/staging/ausliefern.sh <tag>`:**
  1. Tag holen und prüfen, dass er auf GitHub existiert und der Arbeitsstand auf dem Server sauber ist (`git status` leer; kein Code von Hand).
  2. Bild bauen `ketteler-shop:<tag>` (Dockerfile im Repository: Composer ohne Entwicklungspakete, Theme-Dateien, eigene
     Plugins).
  3. **Sicherung der Shop-Datenbank** (und der ZITADEL-Testdatenbank) nach `sicherungen/vor-<tag>-<zeit>.sql.gz`, geprüft
     (gzip-Test, Schlusszeile).
  4. Wartungsmodus, neues Bild starten, `system:update:finish`, `plugin:update` für unsere Plugins, `ketteler:einrichten`,
     `ketteler:steuern:anwenden`, `theme:compile`, `cache:clear`, Worker neu starten.
  5. Prüfungen: Shop 200 mit Passwort, 401 ohne Passwort, `X-Robots-Tag: noindex`, robots.txt, Store-API, Admin-API-Token,
     Mailpit erreichbar, Attrappe erreichbar, keine Fehler im Protokoll. Schlägt eine Prüfung fehl: automatisch zurück.
  6. Ausgelieferten Tag in `/opt/ketteler-shop/STAND` vermerken.
- **`ops/staging/zurueck.sh <tag> [--datenbank]`:** vorheriges Bild starten, auf Wunsch die Sicherung vor dem fehlgeschlagenen
  Update zurückspielen, Prüfungen wie oben.
- **`ops/staging/redaktion-pruefen.sh`:** nur lesend: Container der Redaktion mit Startzeit, Prüfsumme Caddyfile,
  Erreichbarkeit `staging-redaktion`/`-login`/`-mail`. Läuft vor und nach jeder Auslieferung; Unterschied = Abbruch.

## 6. Sicherung

Eigener kleiner Dienst `sicherung` im Shop-Stack (kein Eintrag in der crontab der Redaktion): täglich 03:40 Uhr Dump von
Shop-Datenbank und ZITADEL-Testdatenbank nach `/opt/ketteler-shop/sicherungen/taeglich/`, geprüft, 14 Stände; dazu die
Sicherung vor jeder Auslieferung (30 Tage). Medien sind Testdaten bzw. kommen aus weclapp und werden nicht gesichert.
Damit bleiben die Bestellungen aus dem weclapp-Test nachvollziehbar.

## 7. weclapp-Anbindung (Vorbereitung, nicht angebunden)

Quellen (öffentliche Doku, abgerufen 12.10.2026): Shopware 6 = https://doc.weclapp.com/documentation/e-commerce/01-shops/01-2-shopware-6/,
Shopware 5 = …/01-shops/01-1-shopware-5/ (gleiche Anbindung, ausführlicher; gilt für Shopware 6 nicht sicher, unten mit
[SW5] markiert), Übersicht = …/01-shops/ (Funktionstabelle Stand 02.03.2022, nur „Shopware“ allgemein), Fehler =
…/01-shops/01-7-die-haeufigsten-fehler-shops/, Migration = /knowledgebase/release-21-15-1-migration-von-bestehenden-anbindungen-und-artikel-einstellungen/.

### Was in welche Richtung geht

| Daten | weclapp nach Shop | Shop nach weclapp |
|---|---|---|
| Artikel, Texte | ja, wenn weclapp führt | nur wenn der Shop führt [SW5]: dann legt weclapp Artikel an („immer als Lagerartikel“) |
| Preise | ja; Basispreis = Staffelwert 0, Staffeln als „Erweiterte Preise“; brutto oder netto per Option | nur wenn der Shop führt [SW5]; Einkaufspreise laut Übersicht |
| Bestand | ja, alle 5 Minuten [SW5]; im Shop die internen Lagerbewegungen abschalten | nur per CSV/Inventur [SW5] |
| Bilder | laut Übersicht ja, Richtung nicht genannt | bei Shop führend und angehakter Box [SW5] |
| Kategorien | nicht dokumentiert | bei Shop führend und angehakter Box [SW5] |
| Hersteller, Zusatzfelder | über Zuordnung | nicht dokumentiert |
| **Kunden** | **laut Übersicht ja („Kundendaten von weclapp zum Shop automatisch übertragen“)**, wie bei Shopware 6 auszulösen oder abzuschalten: nicht dokumentiert | mit dem Auftrag; Zuordnung über E-Mail oder Kundennummer; Gastbestellung = neuer Kunde; Option „Kunden beim Auftragsimport updaten“ ändert bestehende weclapp-Kunden |
| Bestellungen | Status zurück (Auftragsstatus, Versand mit Sendungsnummer, Zahlstatus, Storno) | ja; Nummern kommen aus dem Shop |
| Rechnungen (PDF) | nicht dokumentiert | nein |

### Antworten auf die Fragen aus dem Auftrag

- **Schreibt die Anbindung mehr als Aufträge und Kunden in weclapp?** Ja, möglich: Die „Synchronisierungsrichtung
  bestimmt das führende System“. Steht sie auf „Vom Shop zu weclapp“, legt weclapp Artikel aus dem Shop an und übernimmt
  Preise und Texte [SW5]. Die Vorgabe ist nicht dokumentiert. **Muss auf „weclapp führend“ stehen**, sonst landen unsere
  Testartikel (`TEST-…`) in weclapp. Dazu ändert „Kunden beim Auftragsimport updaten“ bestehende weclapp-Kunden; für den
  Test **aus** lassen.
- **Holt sie Kundendaten aus weclapp in den Shop?** Laut Übersichtstabelle ja. Auf Staging wären das echte Personendaten.
  Vor dem Anbinden klären, wo das in weclapp abzuschalten ist (Frage an Atilla, ggf. weclapp-Support).
- **Welche Felder überschreibt sie im Shop?** Pro Feld einstellbar in einer Zuordnungstabelle, Spalte „Übertragen“:
  „immer oder nur bei der Artikelanlage“ [SW5]. Ob nicht zugeordnete Zusatzfelder (unsere `ketteler_artikelart`,
  `ketteler_produkt`, `ketteler_abo`) unberührt bleiben, ist **nicht dokumentiert**. Phase 3 prüft das an einem Artikel.
  Unsere Felder in weclapp **nicht** zuordnen. Zugeordnete Zusatzfelder „müssen … an allen Artikeln befüllt sein“.
- **Was stellt Atilla in weclapp ein?** Einstellungen > Integration > Shops > Shopware 6: Shop-Adresse ohne Unterpfad,
  gültiges Zertifikat, Zugangsdaten, mindestens 2 Sicherheitstage, Kundenzuordnung, Vertriebswege, Kundengruppe zu
  Vertriebsweg mit Preisregel, Steuern (19 %, 7 %, Online), Hersteller, Währung, Einheiten, Zahlarten zu Auftragsarten,
  Synchronisierungsrichtung weclapp führend. Die Doku rät, „zunächst ALLE automatischen Funktionen“ abgeschaltet zu
  lassen. Der weclapp-Benutzer braucht die Rolle „Mein-weclapp-Admin“.
- **Welche Zugangsdaten braucht weclapp vom Shop?** Die Doku empfiehlt Benutzername und Passwort eines Administrators
  („als Administrator in deinem Shopware 6 Backend“); daneben gibt es die Felder client_id und client_secret
  (Integration), aber ohne Beschreibung. Plan: zuerst eine **Integration** mit eigener, knapper Rolle (Artikel, Preise,
  Bestand, Medien, Kategorien, Hersteller schreiben; Bestellungen und Kunden lesen; Bestellstatus schreiben). Klappt das
  nicht, ein eigener Verwaltungsbenutzer `weclapp` mit derselben Rolle, kein Vollzugriff. Zugangsdaten nur an Atilla.
- **Erst nur wenige Artikel?** Ja: Pro Artikel in weclapp im Reiter „E-Commerce Einstellungen“ die Häkchen „Im Shop
  verfügbar“, „Im Shop aktiv“, „Bestand übertragen“ (Migration); Voraussetzung „Verkaufsartikel“, „Artikel ist aktiv“,
  Hersteller [SW5]. Abgleich von Hand starten („einzeln oder komplett“ laut Store-Seite). Ein Kategoriefilter ist nicht
  dokumentiert. Vorschlag: für den ersten Abgleich etwa 10 Artikel freigeben (Print, AVR Digital, Eichstätter Online
  608082, ein Paket, ein ZMV-Abo).

### Folgen für Staging

- weclapp braucht Shop und Admin-API **ohne Passwortschutz** („kann weclapp viele Daten nicht übertragen“; „Shop und
  das Backend müssen öffentlich … erreichbar sein“). Plan: nur `/api/` vom Passwortschutz ausnehmen und auf die
  IP-Adressen von weclapp beschränken (auf Anfrage bei support@weclapp.com). Reicht das nicht, als Frage an Atilla, nicht
  den ganzen Shop öffnen.
- Nicht dokumentiert und in Phase 3 zu prüfen: Versandarten-Zuordnung, welche Bestell- und Zahlstatus abgeholt werden, wie
  oft, was mit Artikeln passiert, die es nur im Shop gibt (unsere Testartikel).

## 8. Artikelnummern mit unerlaubten Zeichen (aus der vorhandenen Analyse)

Erlaubt sind laut weclapp nur „a-Z, 0-9, Punkt, Bindestrich und Unterstrich“. Die Analyse nennt nur Beispiele, keine
vollständige Liste der 451 Artikel. Von den genannten Nummern verstößt **„ZMV Einzelartikel“** (Leerzeichen). Unklar ist
**„VA19-…“** (in der Analyse abgekürzt). Alle anderen genannten Nummern sind erlaubt (z. B. `05029-D`, `050291-05029-D`,
`608082`, `030-P`, `z1-2026`, `DE_1_1001_Gramm`). Die vollständige Liste kommt in Phase 3 aus den echten Daten.

## 9. Offene Fragen an Atilla (Phase 1)

1. Server vor Phase 2 auf CPX32 umskalieren (mit Ketteler abstimmen, Unterbrechung wenige Minuten)? Zusätzlich 2 GB Swap?
2. Caddy: Variante A (einmalig Ordner-Import in der Redaktion, danach nur eigene Datei) oder Variante B (drei Einträge im
   Caddyfile der Redaktion)? Wer setzt die Änderung in der Redaktion um?
3. Adressen: `staging-shop…` wie vorgeschlagen oder `shop…`?
4. Datenbank: Staging mit MySQL 8.4 (wie Live geplant); lokal läuft MariaDB 11.4. Lokal auf MySQL 8.4 umstellen, damit
   alle drei gleich sind?
5. Deploy-Key: Darf ich auf dem Server einen nur lesenden Schlüssel für `ketteler-shop` erzeugen, den Atilla in GitHub
   einträgt?
6. weclapp-Adressen: Fragst du (oder Ketteler) beim weclapp-Support die IP-Adressen an, damit `/api/` nur für weclapp
   ohne Passwortschutz offen ist?
7. Kundendaten aus weclapp: Wo ist „Kundendaten von weclapp zum Shop“ abzuschalten? Ohne Klärung binde ich in Phase 3
   nicht an, damit keine echten Personendaten auf Staging landen.
8. Synchronisierungsrichtung in weclapp: bitte beim Einrichten „weclapp führend“ wählen und „Kunden beim Auftragsimport
   updaten“ aus lassen; automatische Funktionen zuerst aus.
