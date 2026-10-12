#!/bin/bash
# Auf einen früheren Tag zurück:  bash /opt/ketteler-shop/repo/ops/staging/zurueck.sh <tag> [--datenbank <sicherung>]
# Ohne --datenbank bleibt die Datenbank, wie sie ist (geht, solange der neuere Stand keine Datenbank-Änderungen hatte).
# Mit --datenbank wird die Sicherung <sicherung> (Name aus sicherungen/auslieferung, ohne _shop/_zitadel.sql.gz)
# von Shop und ZITADEL eingespielt; vorher wird der jetzige Stand selbst noch einmal gesichert.
. "$(dirname "$(readlink -f "$0")")/lib.sh"
TAG=${1:?Tag angeben}
[[ $TAG =~ $TAG_MUSTER ]] || fehler "Tag muss shop-staging-JJJJ-MM-TT.N heißen."
SICH=""
[ "${2:-}" = "--datenbank" ] && SICH=${3:?Name der Sicherung angeben}
mkdir -p "$BASIS/protokolle"
exec > >(tee -a "$BASIS/protokolle/zurueck-$ZEIT-$TAG.log") 2>&1
VORHER=$(laufender_tag)

say "Stand $TAG bereitstellen"
git_holen || true
stand_bereit "$TAG"
bild_bauen "$TAG"

if [ -n "$SICH" ]; then
  for db in shop zitadel; do
    [ -s "$BASIS/sicherungen/auslieferung/${SICH}_$db.sql.gz" ] || fehler "Sicherung ${SICH}_$db.sql.gz fehlt."
    gzip -t "$BASIS/sicherungen/auslieferung/${SICH}_$db.sql.gz" || fehler "Sicherung ${SICH}_$db.sql.gz ist beschädigt."
  done
  say "Jetzigen Stand sichern (vor-zurueck-$ZEIT), dann Sicherung $SICH einspielen"
  sicherung_jetzt "vor-zurueck-$ZEIT"
  dc stop web worker zitadel >/dev/null
  gunzip -c "$BASIS/sicherungen/auslieferung/${SICH}_shop.sql.gz" | dc exec -T datenbank sh -c \
    'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -e "DROP DATABASE shopware; CREATE DATABASE shopware DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" && MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot shopware'
  gunzip -c "$BASIS/sicherungen/auslieferung/${SICH}_zitadel.sql.gz" | dc exec -T zitadel-db sh -c \
    'psql -q -U zitadel -d postgres -c "DROP DATABASE zitadel WITH (FORCE);" -c "CREATE DATABASE zitadel OWNER zitadel;" >/dev/null && psql -q -U zitadel -d zitadel >/dev/null'
fi

say "Stand $TAG starten"
START=$(date -u +%Y-%m-%dT%H:%M:%SZ)
auf_stand_schalten "$TAG"
dc up -d --remove-orphans --force-recreate front web worker lizenz-attrappe sicherung-shop sicherung-zitadel
dc up -d datenbank mailer zitadel-db zitadel
warte_web
konsole theme:compile >/dev/null
konsole cache:clear >/dev/null
konsole sales-channel:maintenance:disable --all >/dev/null || true
caddy_datei

say "Prüfungen"
sleep 3
PRUEF_SEIT="$START" bash "$BASIS/staende/$TAG/ops/staging/pruefen.sh" || fehler "Prüfungen nach der Rücknahme fehlgeschlagen."
printf '%s %s zurückgenommen (vorher: %s%s)\n' "$(date '+%F %T')" "$TAG" "${VORHER:-keiner}" "${SICH:+, Datenbank aus $SICH}" >> "$BASIS/STAND"
say "Fertig: $TAG läuft."
