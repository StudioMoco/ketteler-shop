#!/bin/bash
# Nur lesend: Stand der Redaktion (und des gemeinsamen Caddy) in vergleichbarer Form. Vor und nach jeder Änderung des
# Shops laufen lassen und vergleichen; ein Unterschied heißt Abbruch und Meldung an Atilla.
# Grundlage ist stand.sh der Redaktion; herausgefiltert wird nur, was zum Shop gehört oder sich ohnehin ändert (Uhrzeit,
# Speicher, Sicherungen, Überwachung). Die Caddy-Konfiguration wird per „caddy adapt“ ohne die Shop-Adressen verglichen.
set -euo pipefail
S=$(bash /opt/ketteler/ops/staging-server/stand.sh 2>/dev/null)
echo "== Container der Redaktion (Name, Start, Neustart-Regel, Neustarts, Zustand)"
echo "$S" | sed -n '/^== Container/,/^== Caddy/p' | grep -v '^==' | grep -v '^ketteler-shop-stg-' | awk '{print $1, $2, $3, $4, $5}'
echo "== Caddy"
echo "$S" | grep -E 'bind |volume |Caddyfile im Container'
sha256sum /opt/staging-caddy/sites/ketteler-redaktion.caddy | sed 's# .*#  sites/ketteler-redaktion.caddy (sha256)#'
docker exec ketteler-caddy-1 caddy adapt --config /etc/caddy/Caddyfile --adapter caddyfile 2>/dev/null | python3 -c '
import hashlib, json, sys
SHOP = ("ketteler-shop.mocodev.de", "ketteler-shop-login.mocodev.de", "ketteler-shop-mail.mocodev.de")
d = json.load(sys.stdin)
for srv in d["apps"]["http"]["servers"].values():
    srv["routes"] = [r for r in srv.get("routes", []) if not any(h in SHOP for m in r.get("match", []) for h in m.get("host", []))]
for p in d.get("apps", {}).get("tls", {}).get("automation", {}).get("policies", []):
    if "subjects" in p:
        p["subjects"] = [s for s in p["subjects"] if s not in SHOP]
print(hashlib.sha256(json.dumps(d, sort_keys=True).encode()).hexdigest(), " Caddy-Konfiguration ohne Shop-Adressen (adapt, sha256)")'
echo "$S" | grep -E '^== Git|^== Cron'
echo "$S" | sed -n '/^== Cron/,/^== Erreichbarkeit/p' | grep -v '^=='
echo "== Erreichbarkeit"
echo "$S" | sed -n '/^== Erreichbarkeit/,/^== Sicherungen/p' | grep -v '^=='
