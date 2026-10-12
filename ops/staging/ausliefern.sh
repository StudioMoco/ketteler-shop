#!/bin/bash
# Einen Tag auf Staging ausliefern:  bash /opt/ketteler-shop/repo/ops/staging/ausliefern.sh shop-staging-JJJJ-MM-TT.N
# Ablauf: Redaktion-Stand vorher, Tag holen (nur Tags, nie Branches), Bild bauen, Sicherung, neuen Stand starten,
# Datenbank/Plugins/Theme aktualisieren, Caddy-Datei (nur wenn geändert), Prüfungen. Scheitert eine Prüfung: automatisch
# zurück auf den vorherigen Tag samt Datenbank. Zum Schluss Redaktion-Stand nachher und Vergleich.
. "$(dirname "$(readlink -f "$0")")/lib.sh"
TAG=${1:?Tag angeben, z. B. shop-staging-2026-10-12.1}
[[ $TAG =~ $TAG_MUSTER ]] || fehler "Tag muss shop-staging-JJJJ-MM-TT.N heißen."
mkdir -p "$BASIS/protokolle"
exec > >(tee -a "$BASIS/protokolle/ausliefern-$ZEIT-$TAG.log") 2>&1
HIER=$(dirname "$(readlink -f "$0")")

say "Redaktion: Stand vorher"
bash "$HIER/redaktion-pruefen.sh" > "$BASIS/protokolle/redaktion-vor-$ZEIT.txt"

say "Tag $TAG holen und prüfen"
git_holen
stand_bereit "$TAG"
VORHER=$(laufender_tag)
[ "$VORHER" != "$TAG" ] || fehler "$TAG läuft bereits."

say "Bild bauen"
bild_bauen "$TAG"

SICHERUNG=""
if [ -n "$VORHER" ]; then
  say "Sicherung vor der Auslieferung (Stand $VORHER)"
  SICHERUNG="vor-$TAG-$ZEIT"
  sicherung_jetzt "$SICHERUNG"
  konsole sales-channel:maintenance:enable --all >/dev/null || true
fi

say "Stand $TAG starten"
START=$(date -u +%Y-%m-%dT%H:%M:%SZ)
auf_stand_schalten "$TAG"
dc up -d --remove-orphans --force-recreate front web worker lizenz-attrappe sicherung-shop sicherung-zitadel
dc up -d datenbank mailer zitadel-db zitadel
warte_web

say "Shop aktualisieren (Datenbank, Plugins, Grundaufbau, Theme, Cache)"
if shop_aktualisieren; then AKT=0; else AKT=1; fi
konsole sales-channel:maintenance:disable --all >/dev/null || true

say "Caddy-Datei"
caddy_datei

say "Prüfungen"
sleep 3
if [ "$AKT" = 0 ] && PRUEF_SEIT="$START" bash "$HIER/pruefen.sh"; then
  printf '%s %s ausgeliefert (vorher: %s)\n' "$(date '+%F %T')" "$TAG" "${VORHER:-keiner}" >> "$BASIS/STAND"
else
  if [ -n "$VORHER" ]; then
    say "Fehlgeschlagen: zurück auf $VORHER mit Datenbank-Sicherung $SICHERUNG"
    bash "$BASIS/staende/$VORHER/ops/staging/zurueck.sh" "$VORHER" --datenbank "$SICHERUNG"
  fi
  fehler "Auslieferung von $TAG fehlgeschlagen."
fi

aufraeumen

say "Redaktion: Stand nachher und Vergleich"
bash "$HIER/redaktion-pruefen.sh" > "$BASIS/protokolle/redaktion-nach-$ZEIT.txt"
if diff "$BASIS/protokolle/redaktion-vor-$ZEIT.txt" "$BASIS/protokolle/redaktion-nach-$ZEIT.txt"; then
  echo "Redaktion unverändert."
else
  fehler "Redaktion hat sich verändert (siehe oben). Bitte Atilla informieren."
fi
say "Fertig: $TAG läuft."
