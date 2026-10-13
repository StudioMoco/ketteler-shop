#!/usr/bin/env bash
# Testzugänge für die Abnahme (Gesamtauftrag D): legt die Testkonten Privat DE, Privat AT, Firma DE und Einrichtung AT in
# ZITADEL und im Shop an (Passwort: KETTELER_TESTKUNDE_PASSWORT aus der .env) und schreibt alle Zugänge in
# /opt/ketteler-shop/testzugaenge.md (nur für deploy lesbar, 600). Werte erscheinen nirgends sonst, auch nicht in der Ausgabe.
#   bash /opt/ketteler-shop/repo/ops/staging/testzugaenge.sh
# Anzeigen: ssh ketteler 'cat /opt/ketteler-shop/testzugaenge.md'
set -euo pipefail
# shellcheck source=lib.sh
. "$(dirname "$0")/lib.sh"

[ -n "$(envwert KETTELER_TESTKUNDE_PASSWORT)" ] || fehler "KETTELER_TESTKUNDE_PASSWORT fehlt in $BASIS/.env."
say "Testkonten in ZITADEL und im Shop"
konsole ketteler:zitadel:einrichten --testkonten | grep -E "Testkonto|Testkunde|ZITADEL:" || true

say "Zugangsdatei schreiben"
ziel=$BASIS/testzugaenge.md
umask 077
tmp=$(mktemp "$BASIS/.testzugaenge.XXXXXX")
shop=$(envwert APP_URL)
{
  echo "# Testzugänge Staging (nur hier, 600; nicht weitergeben, nicht ins Repository)"
  echo
  echo "Stand: $(date '+%d.%m.%Y %H:%M') · Stand des Shops: $(laufender_tag)"
  echo
  echo "## Passwortschutz vor Shop, Verwaltung, Anmeldung und Mailpit"
  echo "- Benutzer: $(envwert STG_BASICAUTH_BENUTZER)"
  echo "- Passwort: $(envwert STG_BASICAUTH_PASSWORT)"
  echo
  echo "## Adressen"
  echo "- Shop: $shop/"
  echo "- Verwaltung (Shopware): $shop/admin"
  echo "- Anmeldung (ZITADEL): $(envwert KETTELER_ZITADEL_URL)/ui/console"
  echo "- Mailpit (alle Mails des Shops): $(envwert KETTELER_MAILPIT_URL)"
  echo
  echo "## Verwaltung (Shopware)"
  echo "- Benutzer: admin"
  echo "- Passwort: $(envwert SHOP_ADMIN_PASSWORT)"
  echo
  echo "## ZITADEL-Verwaltung"
  echo "- Benutzer: zitadel-admin@ketteler.test"
  echo "- Passwort: $(envwert ZITADEL_ADMIN_PASSWORT)"
  echo
  echo "## Testkonten im Shop (Anmeldung über „Anmelden“, alle mit demselben Passwort)"
  echo "- Passwort: $(envwert KETTELER_TESTKUNDE_PASSWORT)"
  echo "- privat-de@ketteler.test: Privatkundin Deutschland (Paula Privat, Waldmünchen)"
  echo "- privat-at@ketteler.test: Privatkunde Österreich (Anton Alpen, Wien), österreichische Umsatzsteuer bei Online-Artikeln je nach OSS-Schalter"
  echo "- firma-de@ketteler.test: Firma Deutschland mit USt-IdNr. DE 122 792 915 (Inland, deutsche Umsatzsteuer)"
  echo "- einrichtung-at@ketteler.test: Einrichtung Österreich mit geprüfter USt-IdNr. ATU14189108 (Steuerschuldnerschaft des Empfängers, 0 %)"
  echo "- testkunde@ketteler.test, testkunde2@ketteler.test: Konten der automatischen Tests"
} > "$tmp"
chmod 600 "$tmp"
mv -f "$tmp" "$ziel"
ls -l "$ziel" | awk '{print "geschrieben: " $1 " " $9}'
say "Fertig. Anzeigen: ssh ketteler 'cat $ziel'"
