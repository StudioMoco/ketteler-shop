#!/usr/bin/env bash
# PayPal-Sandbox-Zugangsdaten in die .env eintragen, ohne Editor und ohne dass Werte im Terminal oder Verlauf landen.
#   Staging:  ssh -t ketteler '/opt/ketteler-shop/repo/ops/staging/paypal-eintragen.sh'
#   Mac:      ~/Developer/ketteler-shop/ops/staging/paypal-eintragen.sh
# Fragt nacheinander PAYPAL_SANDBOX_CLIENT_ID, PAYPAL_SANDBOX_CLIENT_SECRET (ohne Anzeige) und PAYPAL_SANDBOX_MERCHANT_ID.
# Legt vorher eine Kopie der .env an (.env.vor-paypal-<zeit>, Rechte 600), ändert oder ergänzt nur diese drei Zeilen und
# zeigt zum Schluss, welche Zeilen geändert wurden, nie die Werte. Läuft mit bash 3.2 (Mac) und neuer (Server).
# Übernehmen in den Shop danach: Staging per Auslieferung bzw. Neustart von web/worker und ketteler:einrichten,
# Mac per „docker compose up -d web worker && make einrichten“.
set -euo pipefail
umask 077

if [ -n "${1:-}" ]; then
  ENV_DATEI=$1
elif [ -f /opt/ketteler-shop/.env ]; then
  ENV_DATEI=/opt/ketteler-shop/.env
else
  ENV_DATEI="$(cd "$(dirname "$0")/../.." && pwd)/.env"
fi
[ -f "$ENV_DATEI" ] || { echo "Keine .env gefunden: $ENV_DATEI"; exit 1; }
[ -t 0 ] || { echo "Bitte im Terminal starten (bei ssh mit -t)."; exit 1; }

rechte() { stat -c '%a' "$1" 2>/dev/null || stat -f '%Lp' "$1"; }
bereinigt() { printf '%s' "$1" | tr -d '\r\n' | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//'; }

frage() {   # $1 = Name, $2 = "geheim" für Eingabe ohne Anzeige; Ergebnis in WERT
  local eingabe
  while true; do
    if [ "${2:-}" = geheim ]; then
      read -r -s -p "$1 (Eingabe wird nicht angezeigt): " eingabe; echo
    else
      read -r -p "$1: " eingabe
    fi
    WERT=$(bereinigt "$eingabe")
    if [ -z "$WERT" ]; then
      echo "  Leere Eingabe, bitte noch einmal."
    elif ! printf '%s' "$WERT" | grep -Eq '^[A-Za-z0-9_.-]+$'; then
      echo "  Unerwartete Zeichen (erlaubt: Buchstaben, Ziffern, - _ .). Bitte noch einmal kopieren."
    else
      return 0
    fi
  done
}

echo "PayPal-Sandbox-Zugangsdaten für $ENV_DATEI"
echo "(Werte aus developer.paypal.com, Schalter auf Sandbox; Anleitung: docs/paypal-sandbox.md)"
frage PAYPAL_SANDBOX_CLIENT_ID;              ID=$WERT
frage PAYPAL_SANDBOX_CLIENT_SECRET geheim;   GEHEIM=$WERT
frage PAYPAL_SANDBOX_MERCHANT_ID;            HAENDLER=$WERT

KOPIE="$ENV_DATEI.vor-paypal-$(date +%Y%m%d_%H%M%S)"
[ ! -e "$KOPIE" ] || KOPIE="$KOPIE-$$"   # nie eine frühere Kopie überschreiben
cp -p "$ENV_DATEI" "$KOPIE"
chmod 600 "$KOPIE"

# Werte über die Umgebung an awk (nicht als Argumente, damit sie nicht in der Prozessliste stehen)
NEU="$ENV_DATEI.neu.$$"
trap 'rm -f "$NEU"' EXIT
P_ID=$ID P_GEHEIM=$GEHEIM P_HAENDLER=$HAENDLER awk '
  BEGIN { w["PAYPAL_SANDBOX_CLIENT_ID"] = ENVIRON["P_ID"]; w["PAYPAL_SANDBOX_CLIENT_SECRET"] = ENVIRON["P_GEHEIM"]
          w["PAYPAL_SANDBOX_MERCHANT_ID"] = ENVIRON["P_HAENDLER"] }
  {
    n = index($0, "="); k = (n > 0) ? substr($0, 1, n - 1) : ""
    if (k in w) { if (!(k in erledigt)) print k "=" w[k]; erledigt[k] = 1; next }
    print
  }
  END { for (k in w) if (!(k in erledigt)) print k "=" w[k] }
' "$ENV_DATEI" > "$NEU"
chmod 600 "$NEU"
mv "$NEU" "$ENV_DATEI"
trap - EXIT

[ "$(rechte "$ENV_DATEI")" = 600 ] || { chmod 600 "$ENV_DATEI"; }
[ "$(rechte "$ENV_DATEI")" = 600 ] || { echo "FEHLER: Rechte der .env sind nicht 600."; exit 1; }

echo
echo "Kopie vorher: $KOPIE (Rechte $(rechte "$KOPIE"))"
echo "Rechte der .env: $(rechte "$ENV_DATEI")"
echo "Geänderte Zeilen (ohne Werte):"
diff "$KOPIE" "$ENV_DATEI" | grep -E '^[<>] ' | sed -E 's/^([<>]) ([A-Z_]+)=.*/\1 \2/' | sort -u -k2 -k1 | awk '
  { art[$2] = art[$2] $1 }
  END { for (k in art) { a = art[k]; print "  " k ": " (a == ">" ? "ergänzt" : "geändert") } }' | sort
UNTERSCHIEDE=$(diff "$KOPIE" "$ENV_DATEI" | grep -cE '^[<>] ' || true)
[ "$UNTERSCHIEDE" -gt 0 ] || echo "  keine (Werte waren schon so eingetragen)"
ANDERE=$(diff "$KOPIE" "$ENV_DATEI" | grep -E '^[<>] ' | grep -vcE '^[<>] PAYPAL_SANDBOX_(CLIENT_ID|CLIENT_SECRET|MERCHANT_ID)=' || true)
[ "$ANDERE" = 0 ] || { echo "FEHLER: andere Zeilen verändert, Kopie zurückspielen: cp -p '$KOPIE' '$ENV_DATEI'"; exit 1; }
echo "Fertig. Andere Zeilen unverändert."
