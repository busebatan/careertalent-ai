#!/usr/bin/env bash
# Marketing Blade → landing/ statik export (tek kaynak, drift önleme)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
FRONTEND="$ROOT/frontend"
LANDING="$ROOT/landing"

cd "$FRONTEND"

if [[ ! -f .env ]]; then
  cp -n .env.example .env 2>/dev/null || true
  php artisan key:generate --force --no-interaction 2>/dev/null || true
fi

echo "→ Vite build (CSS/JS)"
npm run build --silent 2>/dev/null || npm run build

echo "→ Blade → landing/ export"
php artisan marketing:export-landing --output="$LANDING"

echo "→ Tamam: $LANDING"
ls -la "$LANDING" "$LANDING"/*/index.html 2>/dev/null || ls -la "$LANDING"
