#!/bin/sh
set -e

echo "🚀  Coup — Entrypoint"

# ── Wait for PostgreSQL ──────────────────────────────────
echo "⏳  Waiting for database..."
until php -r "
    \$c = @pg_connect('host=' . getenv('DB_HOST') . ' port=' . getenv('DB_PORT') . ' dbname=' . getenv('DB_DATABASE') . ' user=' . getenv('DB_USERNAME') . ' password=' . getenv('DB_PASSWORD'));
    if (!\$c) { exit(1); }
    pg_close(\$c);
" 2>/dev/null; do
  sleep 2
done
echo "✅  Database ready"

# ── Create .env from environment variables ───────────────
echo "📝  Creating .env file from environment..."
cat > .env <<EOF
APP_NAME="${APP_NAME:-Coup}"
APP_ENV="${APP_ENV:-production}"
APP_KEY="${APP_KEY}"
APP_DEBUG="${APP_DEBUG:-false}"
APP_URL="${APP_URL:-http://localhost}"

APP_LOCALE="${APP_LOCALE:-pt_BR}"
APP_FALLBACK_LOCALE="${APP_FALLBACK_LOCALE:-en}"
APP_FAKER_LOCALE="${APP_FAKER_LOCALE:-pt_BR}"

APP_MAINTENANCE_DRIVER="${APP_MAINTENANCE_DRIVER:-file}"

BCRYPT_ROUNDS="${BCRYPT_ROUNDS:-12}"

LOG_CHANNEL="${LOG_CHANNEL:-stack}"
LOG_STACK="${LOG_STACK:-single}"
LOG_LEVEL="${LOG_LEVEL:-warning}"

DB_CONNECTION="${DB_CONNECTION:-pgsql}"
DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-coup}"
DB_USERNAME="${DB_USERNAME:-coup}"
DB_PASSWORD="${DB_PASSWORD:-secret}"

SESSION_DRIVER="${SESSION_DRIVER:-database}"
SESSION_LIFETIME="${SESSION_LIFETIME:-120}"

BROADCAST_CONNECTION="${BROADCAST_CONNECTION:-reverb}"
FILESYSTEM_DISK="${FILESYSTEM_DISK:-local}"
QUEUE_CONNECTION="${QUEUE_CONNECTION:-sync}"
CACHE_STORE="${CACHE_STORE:-database}"

REVERB_APP_ID="${REVERB_APP_ID}"
REVERB_APP_KEY="${REVERB_APP_KEY}"
REVERB_APP_SECRET="${REVERB_APP_SECRET}"
REVERB_HOST="${REVERB_HOST:-0.0.0.0}"
REVERB_PORT="${REVERB_PORT:-8080}"
REVERB_SCHEME="${REVERB_SCHEME:-http}"

VITE_REVERB_APP_KEY="${VITE_REVERB_APP_KEY}"
VITE_APP_NAME="${VITE_APP_NAME}"
EOF

# ── Generate app key if missing ──────────────────────────
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
  echo "🔑  Generating new application key..."
  # Generate key directly using PHP
  NEW_KEY=$(php -r "echo 'base64:' . base64_encode(random_bytes(32));")
  export APP_KEY="$NEW_KEY"
  # Update .env file with new key
  sed -i "s|APP_KEY=.*|APP_KEY=$NEW_KEY|g" .env
  echo "✅  Generated key: ${NEW_KEY:0:20}..."
fi

# ── Run migrations ───────────────────────────────────────
echo "📦  Running migrations..."
php artisan migrate --force

# ── Cache config / routes / views ────────────────────────
echo "⚡  Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ── Fix storage permissions ──────────────────────────────
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# ── Sync built assets into the named volume ─────────────
# The Docker named volume persists across rebuilds, so the image's
# /var/www/html/public/build is hidden.  We stashed a copy in
# /tmp/public-build during the Docker build; now we sync it in.
echo "📂  Syncing public assets into shared volume..."
mkdir -p /var/www/html/public/build
cp -rf /tmp/public-build/* /var/www/html/public/build/ 2>/dev/null || true

echo "🎮  Starting Coup server..."
exec "$@"
