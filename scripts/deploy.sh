#!/usr/bin/env bash
set -euo pipefail

FORCE_ENV=false
SKIP_ASSETS=false

usage() {
  echo "Usage: $0 [--force-env] [--skip-assets]" >&2
}

for argument in "$@"; do
  case "$argument" in
    --force-env) FORCE_ENV=true ;;
    --skip-assets) SKIP_ASSETS=true ;;
    *) usage; exit 2 ;;
  esac
done

APP_ENV_CURRENT="$(php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo config("app.env");')"
if [[ "$APP_ENV_CURRENT" != "production" && "$FORCE_ENV" != true ]]; then
  echo "Refusing deploy: APP_ENV is '$APP_ENV_CURRENT', expected 'production'. Pass --force-env to override." >&2
  exit 1
fi

MIGRATION_STARTED=false
MAINTENANCE_STARTED=false

handle_failure() {
  local exit_code=$?
  trap - ERR
  if [[ "$MAINTENANCE_STARTED" == true ]]; then
    if [[ "$MIGRATION_STARTED" == false ]]; then
      echo "Deploy failed before migrations; bringing the application back up." >&2
      php artisan up || echo "Could not bring the application up automatically; run 'php artisan up' manually." >&2
    else
      echo "Deploy failed during or after migrations; the application remains in maintenance mode." >&2
      echo "Review the failed command and migration state, complete or repair the deploy, then run 'php artisan up'." >&2
    fi
  fi
  exit "$exit_code"
}
trap handle_failure ERR

php artisan down
MAINTENANCE_STARTED=true
git pull --ff-only
composer install --no-dev --optimize-autoloader
if [[ "$SKIP_ASSETS" == false ]]; then
  npm ci
  npm run build
fi
MIGRATION_STARTED=true
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan queue:restart
php artisan up
MAINTENANCE_STARTED=false
trap - ERR
