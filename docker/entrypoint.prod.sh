#!/usr/bin/env bash
# =============================================================================
# Entrypoint PROD : prépare l'app puis lance FrankenPHP.
# - Attend que Postgres soit prêt
# - Joue les migrations Doctrine
# - Warmup du cache (avec les VRAIES variables d'env runtime)
# - Lance la commande passée (par défaut : frankenphp)
# =============================================================================

set -euo pipefail

echo "[entrypoint] Démarrage Épi-Café (env=$APP_ENV)"

# ---- 1) Attente Postgres ----------------------------------------------------
DB_HOST="${DATABASE_HOST:-database}"
DB_PORT="${DATABASE_PORT:-5432}"

echo "[entrypoint] Attente de Postgres sur ${DB_HOST}:${DB_PORT}..."
until pg_isready -h "$DB_HOST" -p "$DB_PORT" -U "${POSTGRES_USER:-epicafe_prod}" >/dev/null 2>&1; do
    sleep 1
done
echo "[entrypoint] Postgres OK"

# ---- 2) Migrations Doctrine -------------------------------------------------
echo "[entrypoint] Exécution des migrations..."
php bin/console doctrine:migrations:migrate \
    --no-interaction \
    --allow-no-migration \
    --env=prod

# ---- 3) Warmup du cache -----------------------------------------------------
# (le cache:clear --no-warmup a déjà été fait au build avec dummy env;
# ici on rebuild proprement avec les vraies env vars)
echo "[entrypoint] Warmup du cache prod..."
php bin/console cache:clear --no-debug --env=prod
php bin/console cache:warmup --no-debug --env=prod

# ---- 4) Permissions sur var/ (cache + log) ----------------------------------
chown -R www-data:www-data var || true

# ---- 5) Lance la commande (FrankenPHP par défaut) ---------------------------
echo "[entrypoint] Démarrage du serveur web..."
exec "$@"
