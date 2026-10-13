# Gemeinsame Teile der Staging-Skripte (wird mit „. lib.sh“ geladen). Ordner auf dem Server:
#   /opt/ketteler-shop/git        Klon von GitHub (bare, nur lesender Deploy-Key)
#   /opt/ketteler-shop/staende/T  ein Arbeitsstand je Tag (git worktree), wird nie verändert
#   /opt/ketteler-shop/repo       Verweis auf den laufenden Stand
#   /opt/ketteler-shop/.env       Geheimnisse (600, nur hier und in Atillas Passwortmanager)
#   /opt/ketteler-shop/stand.env  SHOP_TAG=<laufender Tag>;  STAND: Verlauf der Auslieferungen
#   /opt/ketteler-shop/sicherungen, protokolle
set -euo pipefail
WERKZEUG=$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")   # Ordner der Skripte des Standes, der gerade ausgeführt wird
BASIS=/opt/ketteler-shop
GIT=$BASIS/git
SCHLUESSEL=/home/deploy/.ssh/github_ketteler_shop_ro
ZEIT=$(date +%Y%m%d_%H%M%S)
SHOP_URL=https://ketteler-shop.mocodev.de
LOGIN_URL=https://ketteler-shop-login.mocodev.de
MAIL_URL=https://ketteler-shop-mail.mocodev.de
EIGENE_PLUGINS="SwagPayPal KettelerEinrichtung KettelerTheme KettelerAnmeldung KettelerLizenzen KettelerMehrplatz KettelerSteuern"
TAG_MUSTER='^shop-staging-[0-9]{4}-[0-9]{2}-[0-9]{2}\.[0-9]+$'

say() { printf '\n==> %s\n' "$*"; }
fehler() { printf '\nFEHLER: %s\n' "$*" >&2; exit 1; }

# docker compose des Shop-Stacks; Compose-Datei aus dem Stand, auf den repo zeigt
dc() {
  docker compose -f "$BASIS/repo/ops/staging/compose.yaml" --project-directory "$BASIS" \
    --env-file "$BASIS/.env" --env-file "$BASIS/stand.env" "$@"
}
konsole() { dc exec -T -u www-data web php bin/console "$@"; }
envwert() { grep -E "^$1=" "$BASIS/.env" | head -1 | cut -d= -f2- | sed "s/^'//; s/'\$//"; }
laufender_tag() { sed -n 's/^SHOP_TAG=//p' "$BASIS/stand.env" 2>/dev/null || true; }

git_holen() {
  GIT_SSH_COMMAND="ssh -i $SCHLUESSEL -o IdentitiesOnly=yes" git -C "$GIT" fetch -q --prune origin '+refs/tags/*:refs/tags/*' '+refs/heads/*:refs/heads/*'
}

# Arbeitsstand für einen Tag anlegen (falls nicht vorhanden) und prüfen, dass er unverändert ist
stand_bereit() {
  local tag=$1
  git -C "$GIT" rev-parse -q --verify "refs/tags/$tag" >/dev/null || fehler "Tag $tag gibt es auf GitHub nicht."
  if [ ! -d "$BASIS/staende/$tag" ]; then
    git -C "$GIT" worktree add -q --detach "$BASIS/staende/$tag" "refs/tags/$tag"
  fi
  [ -z "$(git -C "$BASIS/staende/$tag" status --porcelain)" ] || fehler "Arbeitsstand $tag ist verändert (kein Code von Hand auf dem Server)."
  [ "$(git -C "$BASIS/staende/$tag" rev-parse HEAD)" = "$(git -C "$GIT" rev-parse "refs/tags/$tag^{commit}")" ] || fehler "Arbeitsstand $tag passt nicht zum Tag."
}

bild_bauen() {
  local tag=$1
  if docker image inspect "ketteler-shop:$tag" >/dev/null 2>&1; then echo "Bild ketteler-shop:$tag vorhanden."; return; fi
  docker build -q -f "$BASIS/staende/$tag/ops/staging/Dockerfile" --build-arg STAND="$tag" -t "ketteler-shop:$tag" "$BASIS/staende/$tag" >/dev/null
  echo "Bild ketteler-shop:$tag gebaut."
}

auf_stand_schalten() {
  local tag=$1
  ln -sfn "staende/$tag" "$BASIS/repo.neu" && mv -T "$BASIS/repo.neu" "$BASIS/repo"
  printf 'SHOP_TAG=%s\n' "$tag" > "$BASIS/stand.env"
}

warte_web() {
  for _ in $(seq 1 90); do
    konsole about >/dev/null 2>&1 && return 0
    sleep 2
  done
  fehler "Shop-Container antwortet nicht."
}

# Nach jedem Start eines Standes: Datenbank und Plugins aktualisieren, Grundaufbau, Theme, Cache
shop_aktualisieren() {
  konsole system:update:finish --no-interaction >/dev/null
  konsole plugin:refresh --no-interaction >/dev/null
  # shellcheck disable=SC2086
  konsole plugin:install --activate --no-interaction $EIGENE_PLUGINS >/dev/null
  # shellcheck disable=SC2086
  konsole plugin:update --no-interaction $EIGENE_PLUGINS >/dev/null 2>&1 || true
  konsole ketteler:einrichten >/dev/null
  konsole ketteler:steuern:anwenden >/dev/null
  konsole assets:install >/dev/null
  theme_setzen
  konsole cache:clear >/dev/null
  dc restart worker >/dev/null
}

# KettelerTheme am Verkaufskanal festlegen und übersetzen. Nach einem Update des Theme-Plugins stand der Kanal einmal
# wieder auf dem Standard-Theme (13.10.2026); darum bei jeder Auslieferung und Rücknahme ausdrücklich setzen.
theme_setzen() {
  konsole theme:refresh >/dev/null
  konsole theme:change --all KettelerTheme >/dev/null
}

# Sicherung sofort; mit dem Sicherungsskript dieses Standes (nicht der im laufenden Stand eingebundenen Kopie)
sicherung_jetzt() {   # $1 = Name
  dc exec -T sicherung-shop sh -s -- jetzt shop "$1" < "$WERKZEUG/sicherung.sh"
  dc exec -T sicherung-zitadel sh -s -- jetzt zitadel "$1" < "$WERKZEUG/sicherung.sh"
}

caddy_datei() {
  local quelle=$BASIS/repo/ops/staging/ketteler-shop.caddy
  if cmp -s "$quelle" /opt/staging-caddy/sites/ketteler-shop.caddy; then echo "Caddy-Datei unverändert."; return; fi
  bash /opt/ketteler/ops/staging-server/caddy-datei.sh ketteler-shop "$quelle"
}

aufraeumen() {   # alte Stände und Bilder, die drei neuesten bleiben (der laufende immer)
  local lauf; lauf=$(laufender_tag)
  for t in $(ls -1t "$BASIS/staende" | tail -n +4); do
    [ "$t" = "$lauf" ] && continue
    git -C "$GIT" worktree remove --force "$BASIS/staende/$t" && docker image rm "ketteler-shop:$t" >/dev/null 2>&1 || true
  done
  git -C "$GIT" worktree prune
}
