#!/bin/sh
# Start des Shop-Containers (web) bzw. des Workers (Argument "worker"). Wartet auf die Datenbank; ist Shopware installiert,
# werden die Dateien der Plugins nach public/bundles kopiert und das Theme übersetzt (beides liegt nicht im Bild).
set -eu
cd /var/www/html

php -r '
$u = parse_url(getenv("DATABASE_URL"));
for ($i = 0; $i < 90; $i++) {
    try { new PDO(sprintf("mysql:host=%s;port=%d", $u["host"], $u["port"] ?? 3306), urldecode($u["user"]), urldecode($u["pass"] ?? "")); exit(0); }
    catch (Throwable $e) { sleep(2); }
}
fwrite(STDERR, "Datenbank nicht erreichbar\n"); exit(1);'

installiert() {
  php -r '$u = parse_url(getenv("DATABASE_URL")); $p = new PDO(sprintf("mysql:host=%s;port=%d;dbname=%s", $u["host"], $u["port"] ?? 3306, ltrim($u["path"], "/")), urldecode($u["user"]), urldecode($u["pass"] ?? "")); exit($p->query("SHOW TABLES LIKE \"migration\"")->fetch() ? 0 : 1);' 2>/dev/null
}

if [ "${1:-web}" = "worker" ]; then
  until installiert; do sleep 10; done
  while true; do
    php bin/console scheduled-task:run --time-limit=60 --no-wait >/dev/null 2>&1 || true
    php bin/console messenger:consume async low_priority ketteler_lizenz --time-limit=60 --memory-limit=160M -q || sleep 5
  done
fi

if installiert; then
  php bin/console assets:install >/dev/null
  if [ -z "$(ls -A public/theme 2>/dev/null)" ]; then
    php bin/console theme:compile >/dev/null || echo "theme:compile fehlgeschlagen" >&2
  fi
fi
exec /usr/bin/supervisord -c /etc/supervisord.conf
