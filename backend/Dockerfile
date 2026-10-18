# Imagem da API para o Render (ou qualquer serviço que rode Docker).
# FrankenPHP serve a pasta public/ com PHP 8.3, sem nginx nem php-fpm à parte.
FROM dunglas/frankenphp:1-php8.3-bookworm

RUN install-php-extensions pdo_pgsql intl opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    SERVER_NAME=":10000"

EXPOSE 10000

# Atualiza o banco e os dados da pousada a cada deploy, depois sobe o servidor.
CMD ["sh", "docker/start.sh"]
