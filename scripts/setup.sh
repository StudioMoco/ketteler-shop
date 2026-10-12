#!/usr/bin/env bash
# Baut die lokale Entwicklungsumgebung auf einem frischen Rechner auf (Aufruf: make setup).
# Läuft auch mehrfach: Vorhandenes bleibt, der Grundaufbau wird erneut auf denselben Stand gebracht.
set -euo pipefail
cd "$(dirname "$0")/.."

schritt() { printf '\n\033[1;35m==> %s\033[0m\n' "$1"; }
fehler() { printf '\n\033[1;31mFehler: %s\033[0m\n' "$1" >&2; exit 1; }

# Docker Desktop legt "docker" je nach Einstellung an verschiedene Stellen
export PATH="$PATH:/usr/local/bin:$HOME/.docker/bin:/Applications/Docker.app/Contents/Resources/bin"

# 1. Docker vorhanden und gestartet?
command -v docker >/dev/null 2>&1 || fehler "Docker ist nicht installiert. Bitte Docker Desktop installieren (siehe README)."
docker info >/dev/null 2>&1 || fehler "Docker läuft nicht. Bitte Docker Desktop starten und warten, bis der Wal ruhig ist."

# 2. .env anlegen (nur beim ersten Mal), Geheimnisse zufällig erzeugen
if [ ! -f .env ]; then
  schritt "Lege .env an (Zufallswerte, bleibt nur auf diesem Rechner)"
  zufall() { openssl rand -hex "$1"; }
  while IFS= read -r zeile; do
    case "$zeile" in
      DB_PASSWORT=)          echo "DB_PASSWORT=$(zufall 16)" ;;
      DB_ROOT_PASSWORT=)     echo "DB_ROOT_PASSWORT=$(zufall 16)" ;;
      APP_SECRET=)           echo "APP_SECRET=$(zufall 32)" ;;
      INSTANCE_ID=)          echo "INSTANCE_ID=$(zufall 16)" ;;
      SHOP_ADMIN_PASSWORT=)  echo "SHOP_ADMIN_PASSWORT=$(zufall 8)" ;;
      *)                     echo "$zeile" ;;
    esac
  done < .env.example > .env
  chmod 600 .env
fi

# Werte aus .env lesen, die das Skript selbst braucht
wert() { grep -E "^$1=" .env | head -1 | cut -d= -f2-; }
SHOP_PORT=$(wert SHOP_PORT); SHOP_PORT=${SHOP_PORT:-8000}
MAILPIT_PORT=$(wert MAILPIT_PORT); MAILPIT_PORT=${MAILPIT_PORT:-8025}

schritt "Container starten (beim ersten Mal werden die Bilder geladen, das dauert einige Minuten)"
docker compose up -d --wait database mailer
docker compose up -d web

konsole() { docker compose exec -T web "$@"; }

schritt "PHP-Abhängigkeiten installieren (composer install)"
konsole composer install --no-interaction --no-progress

# 3. Shopware installieren, falls die Datenbank leer ist
if docker compose exec -T database sh -c 'MYSQL_PWD="$MARIADB_PASSWORD" mariadb -ushopware shopware -e "SELECT 1 FROM migration LIMIT 1"' >/dev/null 2>&1; then
  schritt "Shopware ist schon installiert, Datenbank aktualisieren"
  konsole bin/console system:update:finish --no-interaction
else
  schritt "Shopware installieren (Deutsch, Euro)"
  konsole bin/console system:install --create-database --basic-setup --shop-locale=de-DE --shop-currency=EUR --force --no-interaction
fi

schritt "Admin-Passwort aus .env setzen"
konsole bin/console user:change-password admin --password="$(wert SHOP_ADMIN_PASSWORT)" >/dev/null

schritt "Eigene Plugins installieren"
konsole bin/console plugin:refresh --no-interaction >/dev/null
konsole bin/console plugin:install --activate --no-interaction KettelerEinrichtung KettelerTheme
konsole bin/console plugin:update --no-interaction KettelerEinrichtung KettelerTheme >/dev/null 2>&1 || true

schritt "Grundaufbau: Steuern, Zahlart, Versand, Zusatzfelder, Kategorien, Testartikel"
konsole bin/console ketteler:einrichten

schritt "Theme zuweisen und übersetzen"
konsole bin/console theme:refresh >/dev/null
konsole bin/console theme:change --all KettelerTheme
konsole bin/console assets:install >/dev/null
konsole bin/console dal:refresh:index --no-interaction >/dev/null
konsole bin/console cache:clear >/dev/null

cat <<TEXT

Fertig. Der Shop läuft:

  Shop:       http://localhost:${SHOP_PORT}
  Verwaltung: http://localhost:${SHOP_PORT}/admin   (Benutzer: admin, Passwort: steht in .env unter SHOP_ADMIN_PASSWORT)
  Mails:      http://localhost:${MAILPIT_PORT}        (alle Mails landen hier, nichts geht nach außen)

Anhalten mit "make stop", wieder starten mit "make start".
TEXT
