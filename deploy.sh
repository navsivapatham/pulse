#!/usr/bin/env bash
set -euo pipefail

LOCAL="$(cd "$(dirname "$0")" && pwd)"

# Host details live in deploy.env (gitignored); see deploy.env.example.
[ -f "$LOCAL/deploy.env" ] && . "$LOCAL/deploy.env"
: "${SERVER:?set SERVER in deploy.env (user@host)}"
: "${REMOTE:?set REMOTE in deploy.env (app path on the server)}"

echo "▸ Building frontend..."
cd "$LOCAL/frontend"
npm run build

echo "▸ Syncing backend..."
rsync -avz --delete \
  --exclude='.env' \
  --exclude='vendor/' \
  --exclude='storage/logs/*' \
  --exclude='storage/framework/cache/*' \
  --exclude='storage/framework/sessions/*' \
  --exclude='storage/framework/views/*' \
  --exclude='database/database.sqlite' \
  --exclude='node_modules/' \
  "$LOCAL/backend/" "$SERVER:$REMOTE/backend/"

echo "▸ Syncing frontend dist..."
rsync -avz --delete "$LOCAL/frontend/dist/" "$SERVER:$REMOTE/frontend/dist/"

echo "▸ Syncing reminder bot..."
rsync -avz "$LOCAL/reminder.py" "$SERVER:$REMOTE/reminder.py"

echo "▸ Installing backend dependencies..."
ssh "$SERVER" "cd $REMOTE/backend && composer install --no-dev --optimize-autoloader --quiet"

echo "▸ Running migrations..."
ssh "$SERVER" "cd $REMOTE/backend && php artisan migrate --force"

echo "▸ Clearing caches..."
ssh "$SERVER" "cd $REMOTE/backend && php artisan config:cache && php artisan route:cache && php artisan view:cache"

echo "▸ Fixing permissions..."
ssh "$SERVER" "chmod 775 $REMOTE/backend/database && chmod 664 $REMOTE/backend/database/database.sqlite 2>/dev/null; chmod 775 $REMOTE/backend/storage $REMOTE/backend/storage/framework $REMOTE/backend/storage/framework/views $REMOTE/backend/storage/framework/cache $REMOTE/backend/storage/framework/sessions $REMOTE/backend/storage/logs"

echo "▸ Restarting services..."
ssh "$SERVER" "sudo /usr/bin/systemctl restart php8.4-fpm && sudo /usr/bin/systemctl restart pulse-reminder"

echo "✓ Pulse deployed to $SERVER"
