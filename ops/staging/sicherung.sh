#!/bin/sh
# Sicherung der Shop-Datenbank (Container sicherung-shop, Bild mysql) bzw. der ZITADEL-Testdatenbank (sicherung-zitadel,
# Bild postgres). Läuft im Stack, nicht in der crontab des Servers.
#   sicherung.sh dienst <shop|zitadel>     täglich 03:40 Uhr (TZ=Europe/Berlin), 14 Stände in /sicherungen/taeglich
#   sicherung.sh jetzt <shop|zitadel> <name>   sofort, nach /sicherungen/auslieferung/<name>_<db>.sql.gz (30 Tage)
# Jede Sicherung wird geprüft (gzip-Test und Schlusszeile des Dumps; pg_dump ab 17.6 schreibt danach noch
# „\\unrestrict …“); erst dann gilt sie als gelungen.
set -eu
ART=$1; DB=$2

dump() {   # $1 = Zieldatei
  tmp="$1.tmp"
  if [ "$DB" = shop ]; then
    mysqldump -h datenbank -uroot --single-transaction --routines --triggers --no-tablespaces --set-gtid-purged=OFF shopware | gzip -6 > "$tmp"
    schluss="Dump completed"
  else
    pg_dump -h zitadel-db -U zitadel zitadel | gzip -6 > "$tmp"
    schluss="PostgreSQL database dump complete"
  fi
  gzip -t "$tmp" && gunzip -c "$tmp" | tail -8 | grep -q "$schluss" || { rm -f "$tmp"; echo "$(date '+%F %T') FEHLER $DB $1"; return 1; }
  mv "$tmp" "$1"
  echo "$(date '+%F %T') OK $(basename "$1") $(wc -c < "$1") Bytes"
}

if [ "$ART" = jetzt ]; then
  mkdir -p /sicherungen/auslieferung
  dump "/sicherungen/auslieferung/${3}_${DB}.sql.gz"
  find /sicherungen/auslieferung -name "*_${DB}.sql.gz" -mtime +30 -delete
  exit 0
fi

mkdir -p /sicherungen/taeglich
while true; do
  heute=$(date +%Y%m%d)
  # Nur im Fenster 03:40 bis 03:59 Uhr (kein Nachholen beim Start; bei Fehlschlag neuer Versuch in der nächsten Minute)
  uhr=$(date +%H%M)
  if [ "$uhr" \> "0339" ] && [ "$uhr" \< "0400" ] && ! ls /sicherungen/taeglich/"${DB}_${heute}"_*.sql.gz >/dev/null 2>&1; then
    dump "/sicherungen/taeglich/${DB}_${heute}_$(date +%H%M).sql.gz" >> /sicherungen/sicherung.log 2>&1 || true
    ls -1t /sicherungen/taeglich/"${DB}"_*.sql.gz 2>/dev/null | tail -n +15 | xargs -r rm -f
  fi
  sleep 60
done
