#!/bin/bash
# Erstes Einrichten des Shops auf Staging (einmalig; danach nur ausliefern.sh):
#   bash einrichten.sh shop-staging-JJJJ-MM-TT.N      (aus einer Kopie dieses Skripts, z. B. per scp nach /tmp)
# Legt /opt/ketteler-shop an, klont das Repository mit dem nur lesenden Deploy-Key, erzeugt die .env mit Zufallswerten
# (Rechte 600; Werte nur hier und in Atillas Passwortmanager), baut das Bild, installiert Shopware mit dem Grundaufbau
# (Testartikel, keine weclapp-Artikel), richtet den Test-ZITADEL ein, spielt die Caddy-Datei ein und prüft alles.
set -euo pipefail
TAG=${1:?Tag angeben}
BASIS=/opt/ketteler-shop
if [ ! -d "$BASIS" ]; then
  sudo mkdir "$BASIS" && sudo chown deploy:deploy "$BASIS" && chmod 750 "$BASIS"
fi
if [ ! -d "$BASIS/git" ]; then
  GIT_SSH_COMMAND="ssh -i /home/deploy/.ssh/github_ketteler_shop_ro -o IdentitiesOnly=yes" \
    git clone -q --bare git@github.com:StudioMoco/ketteler-shop.git "$BASIS/git"
fi
mkdir -p "$BASIS/staende" "$BASIS/sicherungen" "$BASIS/protokolle"
touch "$BASIS/stand.env"

# Bibliothek erst nach dem Klonen laden (liegt im Stand)
GIT_SSH_COMMAND="ssh -i /home/deploy/.ssh/github_ketteler_shop_ro -o IdentitiesOnly=yes" \
  git -C "$BASIS/git" fetch -q origin '+refs/tags/*:refs/tags/*' '+refs/heads/*:refs/heads/*'
[ -d "$BASIS/staende/$TAG" ] || git -C "$BASIS/git" worktree add -q --detach "$BASIS/staende/$TAG" "refs/tags/$TAG"
. "$BASIS/staende/$TAG/ops/staging/lib.sh"

if [ ! -f "$BASIS/.env" ]; then
  say ".env mit Zufallswerten anlegen (nur auf dem Server)"
  z() { openssl rand -hex "$1"; }
  PW=$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-24)
  HASH=$(printf '%s\n%s\n' "$PW" "$PW" | docker run -i --rm caddy:2-alpine caddy hash-password 2>/dev/null | tail -1)
  [[ $HASH == \$2* ]] || fehler "Passwort-Hash für den Passwortschutz konnte nicht erzeugt werden."
  DBPW=$(z 16)
  umask 077
  cat > "$BASIS/.env" <<ENV
# Ketteler-Shop Staging. Geheimnisse nur hier (Rechte 600) und in Atillas Passwortmanager. Nie ins Repository.
APP_ENV=prod
APP_URL=$SHOP_URL
APP_SECRET=$(z 32)
INSTANCE_ID=$(z 16)
DATABASE_URL=mysql://shopware:$DBPW@datenbank:3306/shopware
DB_PASSWORT=$DBPW
DB_ROOT_PASSWORT=$(z 16)
MAILER_DSN=smtp://mailer:1025
LOCK_DSN=flock
SHOPWARE_HTTP_CACHE_ENABLED=1
SHOPWARE_HTTP_DEFAULT_TTL=7200
SHOPWARE_ES_ENABLED=0
SHOPWARE_ES_INDEXING_ENABLED=0
OPENSEARCH_URL=
SYMFONY_TRUSTED_PROXIES=REMOTE_ADDR
INSTALL_LOCALE=de-DE
FPM_PM_MAX_CHILDREN=4
FPM_PM_START_SERVERS=1
FPM_PM_MAX_SPARE_SERVERS=2
PHP_OPCACHE_MEMORY_CONSUMPTION=128
SHOP_ADMIN_PASSWORT=$(z 12)
KETTELER_BIBLIOTHEK_URL=https://staging-redaktion.mocodev.de/bibliothek
KETTELER_ZITADEL_URL=$LOGIN_URL
KETTELER_ZITADEL_INTERN=http://zitadel:8080
KETTELER_TESTKUNDE_PASSWORT=Kt-$(z 8)-Stg1!
KETTELER_LIZENZ_URL=http://lizenz-attrappe:8090/api/shop/ereignisse
KETTELER_LIZENZ_GEHEIMNIS=$(z 32)
KETTELER_VERWALTUNG_EMAIL=verwaltung@ketteler.test
KETTELER_KONTAKT_EMAIL=kontakt@ketteler-verlag.test
KETTELER_WIDERRUF_EMAIL=widerruf@ketteler-verlag.test
# Leer = offizielle VIES-Schnittstelle der EU
KETTELER_VIES_URL=
KETTELER_MAILPIT_URL=$MAIL_URL
# Optional: echte gültige USt-IdNr. aus AT für die Tests gegen die offizielle VIES-Schnittstelle
KETTELER_TEST_USTID_AT=
PAYPAL_SANDBOX_CLIENT_ID=
PAYPAL_SANDBOX_CLIENT_SECRET=
PAYPAL_SANDBOX_MERCHANT_ID=
ZITADEL_MASTERKEY=$(z 16)
ZITADEL_DB_PASSWORT=$(z 16)
ZITADEL_ADMIN_PASSWORT=Kt-$(z 8)-Stg1!
# Passwortschutz (Basic Auth) vor Shop, Verwaltung, Anmeldung und Mailpit
STG_BASICAUTH_BENUTZER=ketteler
STG_BASICAUTH_PASSWORT=$PW
STG_BASICAUTH_HASH='$HASH'
STG_ZUGANG=$(z 24)
# Leer = /api/ für alle (nur mit Shopware-Zugangsdaten). Später die IP-Adressen von weclapp, durch Leerzeichen getrennt.
WECLAPP_IPS=
ENV
  chmod 600 "$BASIS/.env"
fi

say "Bild bauen und Stand $TAG einschalten"
stand_bereit "$TAG"
bild_bauen "$TAG"
auf_stand_schalten "$TAG"

say "Dienste starten"
dc up -d --wait datenbank mailer zitadel-db lizenz-attrappe
dc up -d --wait zitadel
dc up -d web worker front sicherung-shop sicherung-zitadel
warte_web

if ! konsole system:config:get core.basicInformation.email >/dev/null 2>&1 || [ -z "$(dc exec -T datenbank sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysql -ushopware -N shopware -e "SHOW TABLES LIKE \"migration\""')" ]; then
  say "Shopware installieren (Deutsch, Euro)"
  konsole system:install --basic-setup --shop-locale=de-DE --shop-currency=EUR --force --no-interaction >/dev/null
fi
konsole user:change-password admin --password="$(envwert SHOP_ADMIN_PASSWORT)" >/dev/null

say "Plugins, Grundaufbau, Test-ZITADEL, Theme"
konsole plugin:refresh --no-interaction >/dev/null
# shellcheck disable=SC2086
konsole plugin:install --activate --no-interaction $EIGENE_PLUGINS >/dev/null
konsole ketteler:einrichten >/dev/null
konsole ketteler:steuern:anwenden >/dev/null
konsole ketteler:zitadel:einrichten >/dev/null
konsole theme:refresh >/dev/null
konsole theme:change --all KettelerTheme >/dev/null
konsole assets:install >/dev/null
konsole dal:refresh:index --no-interaction >/dev/null
konsole cache:clear >/dev/null
dc restart worker >/dev/null

say "Caddy-Datei einspielen"
caddy_datei

say "Prüfungen"
sleep 5
bash "$BASIS/repo/ops/staging/pruefen.sh"
printf '%s %s eingerichtet\n' "$(date '+%F %T')" "$TAG" >> "$BASIS/STAND"
say "Fertig. Zugangsdaten stehen in $BASIS/.env (STG_BASICAUTH_*, SHOP_ADMIN_PASSWORT) und gehören in Atillas Passwortmanager."
