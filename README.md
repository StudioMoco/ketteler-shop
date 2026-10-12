# Ketteler-Shop

Neuer Shop des KETTELER-Verlags mit **Shopware 6.6** (Community Edition). Ersetzt später den NCE-Shop auf
ketteler-verlag.de. Dieses Repository enthält alles, um den Shop auf einem beliebigen Mac lokal aufzubauen:
Einrichtung, Theme, eigene Plugins, Grundeinstellungen und Testartikel. Datenbank und hochgeladene Dateien gehören
nicht dazu, ein frischer Aufbau ergibt immer denselben Stand.

Stand: lokal mit Grundaufbau, Anmeldung über einen eigenen ZITADEL, Lizenzvergabe gegen eine Attrappe der
Lizenz-Schnittstelle und „Jetzt lesen“. Nicht mit Staging, Live oder weclapp verbunden. Plan und Schnittstellen:
[docs/plan.md](docs/plan.md), Fragen an den Verlag: [docs/fragen-ketteler.md](docs/fragen-ketteler.md).

## Neuen Rechner einrichten

Gilt für Macs mit Apple-Chip (M1 bis M4) und mit Intel-Chip.

### 1. Einmalig installieren

1. **Docker Desktop** von https://www.docker.com/products/docker-desktop/ laden („Download for Mac“, passend zum
   Chip: Apple Silicon oder Intel; nachsehen unter  → Über diesen Mac → Chip bzw. Prozessor).
   Die `.dmg` öffnen, Docker in „Programme“ ziehen, Docker starten, Bedingungen bestätigen. Eine Anmeldung bei Docker
   ist nicht nötig.
2. In Docker Desktop unter **Settings → Resources** den Speicher (Memory) auf **mindestens 6 GB** stellen, dann
   „Apply & restart“.
3. **Git**, **make** und **python3** (für die Tests) kommen mit den Entwicklerwerkzeugen von Apple. Im Terminal `git --version` eingeben; fehlt Git,
   bietet macOS die Installation an („Installieren“ klicken).
4. **GitHub-Schlüssel des Geräts:** wie für das Redaktionssystem eingerichtet (siehe `docs/geraete-und-schluessel.md`
   im Redaktions-Repository). Der Schlüssel gilt für das ganze GitHub-Konto StudioMoco, also auch für dieses
   Repository. Prüfen mit:

   ```bash
   ssh -T git@github.com
   ```

   Die Antwort beginnt mit „Hi StudioMoco!“.

### 2. Repository holen

```bash
mkdir -p ~/Developer
```

```bash
git clone git@github.com:StudioMoco/ketteler-shop.git ~/Developer/ketteler-shop
```

### 3. Shop aufbauen

Docker Desktop muss laufen (Wal-Symbol oben in der Menüleiste). Dann:

```bash
cd ~/Developer/ketteler-shop && make setup
```

Beim ersten Mal dauert das 10 bis 20 Minuten (Docker lädt die Bilder, Composer die PHP-Pakete, ZITADEL richtet sich
ein). Am Ende stehen die Adressen im Terminal. Auf dem Rechner sollten dafür etwa 10 GB frei sein. `make setup` legt dabei die Datei `.env` an: Darin stehen die Zugangsdaten dieses Rechners
(zufällig erzeugt). Sie bleibt auf dem Rechner und kommt nie ins Repository.

### 4. Shop im Browser öffnen

| Was | Adresse | Anmeldung |
|---|---|---|
| Shop | http://localhost:8000 | keine |
| Verwaltung (Shopware-Admin) | http://localhost:8000/admin | Benutzer `admin`, Passwort in `.env` unter `SHOP_ADMIN_PASSWORT` |
| Mails (Mailpit) | http://localhost:8025 | keine; alle Mails von Shop und ZITADEL landen hier, nichts geht nach außen |
| Anmeldung (ZITADEL, Konsole) | http://localhost:8080/ui/console | `zitadel-admin@ketteler.test`, Passwort in `.env` unter `ZITADEL_ADMIN_PASSWORT` |
| Lizenz-Attrappe (Zustand) | http://localhost:8090/_zustand | keine; zeigt, welche Lizenzen der Shop „angelegt“ hat |

Die Passwörter stehen nur in der `.env` dieses Rechners, zum Beispiel:

```bash
grep -E "SHOP_ADMIN_PASSWORT|KETTELER_TESTKUNDE_PASSWORT" ~/Developer/ketteler-shop/.env
```

### 5. Als Kunde anmelden und bestellen

Im Shop oben rechts auf das Personen-Symbol, dann **Anmelden**. Der Shop leitet zur Anmeldeseite (ZITADEL) und nach der
Anmeldung zurück. Testkunden (Passwort in `.env` unter `KETTELER_TESTKUNDE_PASSWORT`):

| Testkunde | Besonderheit |
|---|---|
| `testkunde@ketteler.test` | normale Kundin; beim ersten Mal fragt der Shop die Rechnungsanschrift ab |
| `testkunde2@ketteler.test` | normaler Kunde |
| `unbestaetigt@ketteler.test` | E-Mail nicht bestätigt; ZITADEL verlangt die Bestätigung (Code in Mailpit) |

**Konto anlegen** geht ebenfalls über ZITADEL; die Bestätigungsmail landet in Mailpit. Ohne Anmeldung kann man nur
gedruckte Artikel bestellen (Gastbestellung). Nach einer Bestellung mit Online-Zugang erscheint „Jetzt lesen“ in der
Bestätigung und im Kundenkonto; lokal zeigt der Link auf eine Platzhalter-Adresse.

## Alltag

| Befehl | Wirkung |
|---|---|
| `make start` | Shop starten (nach einem Neustart des Macs) |
| `make stop` | Shop anhalten, Daten bleiben |
| `make setup` | nach `git pull`: Pakete, Plugins, Grundaufbau und Theme auf den neuen Stand bringen |
| `make einrichten` | nur den Grundaufbau erneut anwenden (Kategorien, Testartikel) |
| `make neu` | Datenbank dieses Rechners verwerfen und alles frisch aufbauen (fragt vorher) |
| `make test` | Unit-Tests der eigenen Plugins (wenige Sekunden) |
| `make test-e2e` | Ende-zu-Ende-Tests gegen den laufenden Shop: Anmeldung, Gastbestellung, Lizenzen (etwa 3 Minuten) |
| `make konsole` | Kommandozeile im Shop-Container (z. B. `bin/console`) |
| `make logs` | Protokolle ansehen |

Arbeiten auf mehreren Rechnern: Änderungen committen und pushen, auf dem anderen Rechner `git pull` und `make setup`.
Was in der Shopware-Verwaltung von Hand geändert wird, bleibt nur auf diesem Rechner. Was bleiben soll, gehört in die
Dateien unten.

## Was wo liegt

| Pfad | Inhalt |
|---|---|
| `compose.yaml` | Container: `web` (Shopware-Entwicklungsbild mit PHP 8.3, Caddy), `worker` (Warteschlange), `database` (MariaDB 11.4), `mailer` (Mailpit), `zitadel` und `zitadel-db` (Anmeldung), `lizenz-attrappe` |
| `scripts/setup.sh`, `Makefile` | Aufbau und Bedienung |
| `.env.example` | Vorlage für die lokale `.env` (ohne Geheimnisse) |
| `composer.json`, `composer.lock` | Shopware-Version 6.6.10.29 und PHP-Pakete, fest |
| `custom/plugins/KettelerTheme` | Theme im Ketteler-Design: Farben, IBM Plex Sans, Logo, Kopf und Fuß wie die Bibliothek |
| `custom/plugins/KettelerEinrichtung` | Befehl `bin/console ketteler:einrichten`: Grundeinstellungen, Zusatzfelder, Kategorien, Testartikel |
| `custom/plugins/KettelerEinrichtung/src/Resources/daten/kategorien.json` | Kategorien (vorläufig nach dem heutigen Shop) |
| `custom/plugins/KettelerEinrichtung/src/Resources/daten/artikel.json` | Testartikel mit Platzhaltertexten |
| `custom/plugins/KettelerAnmeldung` | Anmeldung über ZITADEL (PKCE, Abmelden auch bei ZITADEL, Verknüpfung der Konten), Gastbestellung nur für Print, Befehl `ketteler:zitadel:einrichten` (nur lokal) |
| `custom/plugins/KettelerLizenzen` | Lizenz beim Online-Kauf über die Warteschlange, Storno, „Jetzt lesen“, Befehl `ketteler:lizenz:erneut` |
| `tools/lizenz-attrappe` | Nachbildung der Lizenz-Schnittstelle des Redaktionssystems für lokale Tests |
| `tests/e2e` | Ende-zu-Ende-Tests (Python, ohne Zusatzpakete) |
| `docs/plan.md` | Plan: Lizenz-Plugin, ZITADEL, weclapp, Staging und Live, offene Fragen |

## Grundaufbau (macht `ketteler:einrichten`)

- Sprache Deutsch, Währung Euro, Land Deutschland (nur diese im Verkaufskanal).
- Steuersätze 19 % und 7 %. Welcher Artikel welchen Satz hat, kommt später aus weclapp.
- Zahlart **Rechnung** (Zahlungsziel 10 Tage), alle anderen Zahlarten aus.
- Versand: **Versand per Post** (vorläufig 4,50 € pauschal) für Bestellungen mit Print oder Bundle, **Ohne Versand
  (Online-Zugang)** für Bestellungen nur mit Online-Artikeln. Gesteuert über „versandkostenfrei“ am Artikel.
- Artikel-Zusatzfelder (Verwaltung → Artikel → Reiter Spezifikationen, Abschnitt „Ketteler“):
  - `ketteler_artikelart`: print, online oder bundle
  - `ketteler_produkt`: Online-Werk für die Lizenz, `avr` oder `eichstaetter` (leer bei Print)
- Nummernkreise: Bestellungen `SW100000` ff., Kunden `SK100000` ff. (stoßen nicht mit weclapp-Nummern zusammen).
- Kategorien nach der Struktur des heutigen Shops, dazu Fußzeile mit Service und Rechtlichem (Platzhalter).
- Sechs Testartikel (Nummern beginnen mit `TEST-`): Print mit 7 % und 19 %, zwei Online-Zugänge (AVR, Eichstätter),
  ein Bundle, ein Einzelheft.

Alle Datensätze haben feste Kennungen. Ein zweiter Lauf ändert nichts, Änderungen an den JSON-Dateien werden beim
nächsten `make einrichten` übernommen.

## Wenn etwas nicht klappt

- **„Docker läuft nicht“:** Docker Desktop öffnen und warten, bis unten links „Engine running“ steht.
- **Port 8000, 8025, 8080 oder 8090 belegt:** in `.env` `SHOP_PORT`, `MAILPIT_PORT`, `ZITADEL_PORT` bzw.
  `ATTRAPPE_PORT` ändern; bei `SHOP_PORT` auch `APP_URL`, bei `ZITADEL_PORT` auch `KETTELER_ZITADEL_URL` anpassen und `make neu`.
- **Anmeldung meldet „nicht eingerichtet“ oder „nicht erreichbar“:** `make setup` erneut ausführen (richtet ZITADEL ein).
- **Seite lädt sehr langsam:** im Entwicklungsmodus ist der erste Aufruf jeder Seite langsam, danach geht es schneller.
- **Alles zurück auf Anfang:** `make neu`.
