#!/bin/bash
# Prüfungen nach jeder Auslieferung bzw. Rücknahme (auf dem Server, nur lesend). Exit 0 = alles gut.
# Zugangsdaten nur aus /opt/ketteler-shop/.env, an curl über eine Konfiguration auf stdin (nie auf der Kommandozeile).
. "$(dirname "$(readlink -f "$0")")/lib.sh"
set +e
FEHLER=0
ok()  { printf '  ok     %s\n' "$1"; }
nok() { printf '  FEHLER %s  (%s)\n' "$1" "$2"; FEHLER=$((FEHLER + 1)); }
mit_pw() { printf 'user = "%s:%s"\n' "$(envwert STG_BASICAUTH_BENUTZER)" "$(envwert STG_BASICAUTH_PASSWORT)" | curl -K - "$@"; }
code() { curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$@"; }

# Direkt nach dem Einspielen holt der gemeinsame Caddy evtl. noch Zertifikate: bis zu 90 s auf Antwort warten
for _ in $(seq 1 30); do [ "$(code "$SHOP_URL/robots.txt")" = 000 ] || break; sleep 3; done
c=$(code "$SHOP_URL/"); [ "$c" = 401 ] && ok "Shop ohne Passwort: 401" || nok "Shop ohne Passwort gesperrt" "$c"
c=$(code "$SHOP_URL/admin"); [ "$c" = 401 ] && ok "Verwaltung ohne Passwort: 401" || nok "Verwaltung ohne Passwort gesperrt" "$c"
c=$(code "$LOGIN_URL/ui/console"); [ "$c" = 401 ] && ok "ZITADEL-Verwaltung ohne Passwort: 401" || nok "ZITADEL ohne Passwort gesperrt" "$c"
c=$(code "$LOGIN_URL/.well-known/openid-configuration"); [ "$c" = 401 ] && ok "Anmeldung ohne Passwort: 401" || nok "Anmeldung ohne Passwort gesperrt" "$c"
c=$(code "$MAIL_URL/"); [ "$c" = 401 ] && ok "Mailpit ohne Passwort: 401" || nok "Mailpit ohne Passwort gesperrt" "$c"
kopf=$(mit_pw -s -D - -o /tmp/shop-start.$$ --max-time 30 "$SHOP_URL/")
echo "$kopf" | head -1 | grep -q ' 200' && grep -q 'KETTELER' /tmp/shop-start.$$ && ok "Shop mit Passwort: 200" || nok "Shop mit Passwort" "$(echo "$kopf" | head -1)"
echo "$kopf" | grep -qi '^x-robots-tag: noindex, nofollow' && ok "X-Robots-Tag: noindex" || nok "X-Robots-Tag" "fehlt"
grep -q 'kt-servicezeile' /tmp/shop-start.$$ && ok "Ketteler-Theme aktiv (Servicezeile im Kopf)" || nok "Ketteler-Theme aktiv" "Kopf ohne Servicezeile"
# Übersetzte Theme-Dateien, auf die die Seite verweist, müssen da sein (sonst 410: Seite ohne Stile und Skripte)
fehlend=""; anzahl=0
for datei in $(grep -o '/theme/[0-9a-f]*/[a-z]*/[^"]*\.\(css\|js\)' /tmp/shop-start.$$ | sort -u); do
  anzahl=$((anzahl + 1))
  c=$(mit_pw -s -o /dev/null -w '%{http_code}' --max-time 20 "$SHOP_URL$datei"); [ "$c" = 200 ] || fehlend="$fehlend $c:$datei"
done
[ "$anzahl" -gt 0 ] && [ -z "$fehlend" ] && ok "Theme-Dateien (CSS, JavaScript) erreichbar" || nok "Theme-Dateien erreichbar" "${fehlend:-keine gefunden}"
rm -f /tmp/shop-start.$$
c=$(mit_pw -s -o /dev/null -w '%{http_code}' --max-time 30 "$SHOP_URL/admin"); [ "$c" = 200 ] && ok "Verwaltung mit Passwort: 200" || nok "Verwaltung mit Passwort" "$c"
curl -s --max-time 10 "$SHOP_URL/robots.txt" | grep -qx 'Disallow: /' && ok "robots.txt sperrt alles" || nok "robots.txt" "falsch"
c=$(code "$SHOP_URL/api/_info/health-check"); [ "$c" = 200 ] && ok "Admin-API ohne Passwortschutz erreichbar (weclapp): 200" || nok "Admin-API erreichbar" "$c"
tok=$(printf '{"grant_type":"password","client_id":"administration","scopes":"write","username":"admin","password":"%s"}' "$(envwert SHOP_ADMIN_PASSWORT)" \
  | curl -s --max-time 20 -H 'Content-Type: application/json' --data-binary @- "$SHOP_URL/api/oauth/token" | grep -c access_token)
[ "$tok" = 1 ] && ok "Admin-API: Anmeldung" || nok "Admin-API: Anmeldung" "kein Token"
c=$(mit_pw -s --max-time 20 "$LOGIN_URL/.well-known/openid-configuration" | grep -c "\"issuer\":\"$LOGIN_URL\"")
[ "$c" = 1 ] && ok "Anmeldung (ZITADEL) mit Passwort, Aussteller stimmt" || nok "Anmeldung (ZITADEL)" "Aussteller"
c=$(mit_pw -s -o /dev/null -w '%{http_code}' --max-time 20 "$MAIL_URL/api/v1/messages"); [ "$c" = 200 ] && ok "Mailpit mit Passwort: 200" || nok "Mailpit" "$c"
c=$(curl -s --max-time 10 http://127.0.0.1:18090/_zustand | grep -c anfragen); [ "$c" = 1 ] && ok "Lizenz-Attrappe erreichbar" || nok "Lizenz-Attrappe" "keine Antwort"
dc exec -T mailer wget -q -T 5 -O /dev/null http://example.com >/dev/null 2>&1 && nok "Mailpit ohne Weg nach außen" "Verbindung ins Internet möglich" || ok "Mailpit ohne Weg nach außen"
n=$(dc logs --since "${PRUEF_SEIT:-10m}" web worker 2>&1 | grep -ciE 'PHP Fatal|\.CRITICAL|\.EMERGENCY' || true)
[ "$n" = 0 ] && ok "keine schweren Fehler im Protokoll" || nok "Protokoll" "$n schwere Fehler"
echo
[ "$FEHLER" = 0 ] && echo "Alle Prüfungen bestanden." || { echo "$FEHLER Prüfung(en) fehlgeschlagen."; exit 1; }
