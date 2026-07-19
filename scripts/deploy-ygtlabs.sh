#!/usr/bin/env bash
# CareerTalent AI → careertalent.ygtlabs.ai production deploy
set -euo pipefail

SRC="$(cd "$(dirname "$0")/.." && pwd)"
DEST="/var/www/vhosts/ygtlabs.ai/careertalent.ygtlabs.ai"
DOMAIN="careertalent.ygtlabs.ai"

echo "→ rsync $SRC → $DEST"
rsync -a --delete \
  --exclude '.git' \
  --exclude '.pytest_cache' \
  --exclude '__pycache__' \
  --exclude '*.pyc' \
  --exclude '.phpunit.result.cache' \
  --exclude '.playwright-cli' \
  --exclude '.playwright-mcp' \
  --exclude 'node_modules' \
  --exclude 'frontend/node_modules' \
  --exclude 'frontend/vendor' \
  --exclude 'frontend/.env' \
  --exclude 'frontend/database/database.sqlite' \
  --exclude 'frontend/storage' \
  --exclude 'frontend/bootstrap/cache/*.php' \
  --exclude 'backend/.env' \
  --exclude 'backend/.venv' \
  --exclude 'backend/uploads' \
  --exclude '.superpowers' \
  "$SRC/" "$DEST/"

cd "$DEST/frontend"

echo "→ composer install (production)"
composer install --no-dev --optimize-autoloader --no-interaction

echo "→ npm ci + build"
npm ci --silent 2>/dev/null || npm ci
npm run build

echo "→ FastAPI forward migration"
cd "$DEST/backend"
DEBUG=false .venv/bin/alembic upgrade head

echo "→ landing export"
bash "$DEST/scripts/build-landing.sh"
cd "$DEST/frontend"

echo "→ storage dirs"
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

echo "→ Laravel runtime schema check"
# FastAPI/Alembic owns the shared PostgreSQL business schema. Laravel only
# consumes the existing users/career tables and needs its session/cache tables.
php artisan tinker --execute="foreach (['sessions', 'cache', 'cache_locks'] as \$table) { if (! Illuminate\\Support\\Facades\\Schema::hasTable(\$table)) { throw new RuntimeException('Missing Laravel runtime table: '.\$table); } }"

echo "→ Livewire assets (Alpine panel UI)"
php artisan livewire:publish --assets --no-interaction

echo "→ permissions"
chown -R yigit:www-data "$DEST"
chmod -R ug+rwx "$DEST/frontend/storage" "$DEST/frontend/bootstrap/cache"

echo "→ Laravel caches (yigit user — PHP-FPM ile aynı sahip)"
cd "$DEST/frontend"
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php
sudo -u yigit php artisan package:discover --no-interaction
sudo -u yigit php artisan route:clear
sudo -u yigit php artisan config:cache
sudo -u yigit php artisan route:cache
sudo -u yigit php artisan view:clear
sudo -u yigit php artisan view:cache

echo "→ backend services restart"
systemctl restart careertalent-fastapi.service
systemctl is-active --quiet careertalent-fastapi.service
if grep -Eq '^CELERY_TASK_ALWAYS_EAGER=(true|1|yes|on)$' "$DEST/backend/.env"; then
  systemctl stop careertalent-celery.service 2>/dev/null || true
else
  systemctl restart careertalent-celery.service
  systemctl is-active --quiet careertalent-celery.service
fi
for attempt in {1..15}; do
  if curl -fsS --max-time 3 http://127.0.0.1:8000/health >/dev/null; then
    break
  fi
  if [[ "$attempt" == 15 ]]; then
    echo "FastAPI health timeout" >&2
    exit 1
  fi
  sleep 1
done

echo "→ smoke (origin)"
for path in / /ozellikler /nasil-calisir /bootcamp /faq /iletisim /panel/login /admin/login /company/login; do
  code=$(curl -s -o /dev/null -w '%{http_code}' -H "Host: $DOMAIN" "https://127.0.0.1${path}" --insecure)
  echo "  $code $path"
  [[ "$code" == "200" ]] || { echo "HTTP smoke failed: $code $path" >&2; exit 1; }
done

curl -fsS -H "Host: $DOMAIN" https://127.0.0.1/faq --insecure | grep -Fq 'Aklınıza takılan bir şey mi var?'
curl -fsS -H "Host: $DOMAIN" https://127.0.0.1/iletisim --insecure | grep -Fq 'İletişime Geçin'

lw=$(curl -s -o /dev/null -w '%{http_code}' -H "Host: $DOMAIN" "https://127.0.0.1/vendor/livewire/livewire.min.js" --insecure)
echo "  $lw /vendor/livewire/livewire.min.js"

echo "✓ Deploy tamam: https://$DOMAIN"
