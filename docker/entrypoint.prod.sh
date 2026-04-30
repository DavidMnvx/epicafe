#!/usr/bin/env bash
# =============================================================================
# Entrypoint PROD : prépare l'app puis lance FrankenPHP.
# Tout est fait au runtime avec les vraies env vars (APP_SECRET, DATABASE_URL).
#
# Étapes :
#  1. Attente de Postgres
#  2. Symfony auto-scripts (assets:install, importmap:install)
#  3. Compilation des assets (asset-map:compile)
#  4. Migrations Doctrine
#  5. Cache warmup
#  6. Lance FrankenPHP
# =============================================================================

set -euo pipefail

echo "[entrypoint] === Démarrage Épi-Café (env=${APP_ENV:-?}) ==="

# ---- 1) Attente Postgres ----------------------------------------------------
DB_HOST="${DATABASE_HOST:-database}"
DB_PORT="${DATABASE_PORT:-5432}"
DB_USER="${POSTGRES_USER:-epicafe_prod}"

echo "[entrypoint] Attente de Postgres sur ${DB_HOST}:${DB_PORT} (user=${DB_USER})..."
TRIES=0
until pg_isready -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" >/dev/null 2>&1; do
    TRIES=$((TRIES + 1))
    if [ $TRIES -gt 60 ]; then
        echo "[entrypoint] ❌ Postgres injoignable après 60s — abort"
        exit 1
    fi
    sleep 1
done
echo "[entrypoint] ✅ Postgres OK"

# ---- 2) Symfony auto-scripts (assets:install, importmap:install, etc.) ------
echo "[entrypoint] Exécution des post-install scripts Composer..."
composer run-script post-install-cmd --no-interaction || {
    echo "[entrypoint] ⚠ post-install-cmd a échoué, on continue (peut être OK)"
}

# ---- 3) Compilation des assets (Asset Mapper) -------------------------------
echo "[entrypoint] Compilation des assets..."
php bin/console asset-map:compile --no-debug --env=prod

# ---- 4) Migrations Doctrine -------------------------------------------------
echo "[entrypoint] Exécution des migrations..."
php bin/console doctrine:migrations:migrate \
    --no-interaction \
    --allow-no-migration \
    --env=prod

# ---- 5) Warmup du cache prod ------------------------------------------------
echo "[entrypoint] Warmup du cache prod..."
php bin/console cache:clear --no-debug --env=prod
php bin/console cache:warmup --no-debug --env=prod

# ---- 6) Permissions sur var/ et public/uploads ------------------------------
chown -R www-data:www-data var public/uploads 2>/dev/null || true

# ---- 7) Lance la commande passée (par défaut : frankenphp) ------------------
echo "[entrypoint] === Démarrage du serveur web ==="
exec "$@"
