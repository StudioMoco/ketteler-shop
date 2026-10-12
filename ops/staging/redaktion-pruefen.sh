#!/bin/bash
# Nur lesend: Stand der Redaktion (und des gemeinsamen Caddy) in vergleichbarer Form. Vor und nach jeder Änderung des
# Shops laufen lassen und vergleichen; ein Unterschied heißt Abbruch und Meldung an Atilla.
# Grundlage ist stand.sh der Redaktion; herausgefiltert wird nur, was zum Shop gehört oder sich ohnehin ändert (Uhrzeit,
# Speicher, Sicherungen, Überwachung). Die Caddy-Konfiguration wird per „caddy adapt“ ohne die Shop-Datei verglichen.
set -euo pipefail
S=$(bash /opt/ketteler/ops/staging-server/stand.sh 2>/dev/null)
echo "== Container der Redaktion (Name, Start, Neustart-Regel, Neustarts, Zustand)"
echo "$S" | sed -n '/^== Container/,/^== Caddy/p' | grep -v '^==' | grep -v '^ketteler-shop-stg-' | awk '{print $1, $2, $3, $4, $5}'
echo "== Caddy"
echo "$S" | grep -E '^(bind|volume) ' | sort   # Reihenfolge der Mounts liefert docker inspect nicht fest
echo "$S" | grep -E 'Caddyfile im Container'
sha256sum /opt/staging-caddy/sites/ketteler-redaktion.caddy | sed 's# .*#  sites/ketteler-redaktion.caddy (sha256)#'
# Gesamtkonfiguration ohne die Shop-Datei, übersetzt in einem Wegwerf-Container ohne Netz (wie caddy-datei.sh). Vor dem
# Shop ergab stand.sh genau diesen Wert (901b47c7…); so ist bewiesen, dass die Redaktion bytegleich konfiguriert ist.
T=$(mktemp -d); trap 'rm -rf "$T"' EXIT
cp -a /opt/staging-caddy/Caddyfile /opt/staging-caddy/sites "$T/" && rm -f "$T/sites/ketteler-shop.caddy"
docker run --rm --network none -v "$T":/etc/caddy:ro -v ketteler_caddy_config:/config:ro caddy:2-alpine \
  caddy adapt --config /etc/caddy/Caddyfile --adapter caddyfile 2>/dev/null \
  | python3 -c 'import hashlib, json, sys; print(hashlib.sha256(json.dumps(json.load(sys.stdin), sort_keys=True).encode()).hexdigest(), " Caddy-Konfiguration ohne Shop-Datei (adapt, sha256)")'
echo "$S" | grep -E '^== Git|^== Cron'
echo "$S" | sed -n '/^== Cron/,/^== Erreichbarkeit/p' | grep -v '^=='
echo "== Erreichbarkeit"
echo "$S" | sed -n '/^== Erreichbarkeit/,/^== Sicherungen/p' | grep -v '^=='
