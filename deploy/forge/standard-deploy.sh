#!/usr/bin/env bash
set -euo pipefail

if [[ "${FORGE_DEPLOY_MESSAGE:-}" =~ \[skip[[:space:]]deploy\] ]]; then
    echo "Skipping deploy because commit message contains [skip deploy]."
    exit 0
fi

forge_composer() {
    # Forge may set this to "php8.4 /usr/local/bin/composer".
    ${FORGE_COMPOSER:-composer} "$@"
}

forge_php() {
    "${FORGE_PHP:-php}" "$@"
}

prepare_laravel_runtime_directories() {
    mkdir -p \
        bootstrap/cache \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs
}

cd "$FORGE_SITE_PATH"
git pull origin "$FORGE_SITE_BRANCH"

cd apps/server

forge_composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

if [[ -f package-lock.json ]]; then
    npm ci
    npm run build
else
    echo "No package-lock.json found; skipping frontend asset build."
fi

maintenance_enabled=0
restore_application() {
    if [[ "$maintenance_enabled" -eq 1 ]]; then
        forge_php artisan up || true
    fi
}
trap restore_application EXIT

if forge_php artisan down --retry=60; then
    maintenance_enabled=1
fi

prepare_laravel_runtime_directories
forge_php artisan storage:link || true
forge_php artisan migrate --force
forge_php artisan config:cache
forge_php artisan route:cache
forge_php artisan view:cache
forge_php artisan queue:restart
forge_php artisan up
maintenance_enabled=0
trap - EXIT
