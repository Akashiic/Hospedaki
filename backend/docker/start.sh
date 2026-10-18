#!/bin/sh
set -e

php artisan config:cache
php artisan route:cache
php artisan migrate --force
# O seed só cria ou atualiza a pousada de exemplo; não apaga pedidos nem muda a senha do dono.
if [ "${SEED_ON_DEPLOY:-true}" = "true" ]; then
  php artisan db:seed --force
fi

exec frankenphp run --config /etc/frankenphp/Caddyfile
