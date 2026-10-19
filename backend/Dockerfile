# Imagem da API para o Render (ou qualquer serviço que rode Docker).
# FrankenPHP serve a pasta public/ com PHP 8.3, sem nginx nem php-fpm à parte.
FROM dunglas/frankenphp:1-php8.3-bookworm

# gd e exif: fotos enviadas pelo painel são reduzidas e endireitadas no servidor.
RUN install-php-extensions pdo_pgsql intl opcache zip gd exif \
    # O binário vem com permissão para abrir portas baixas; o Render não aceita isso e a porta é 10000.
    # Copiar o arquivo descarta essa permissão.
    && cp /usr/local/bin/frankenphp /tmp/frankenphp && mv /tmp/frankenphp /usr/local/bin/frankenphp \
    && chmod +x /usr/local/bin/frankenphp

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    # Fotos de celular passam dos 2 MB do padrão do PHP.
    && printf 'upload_max_filesize = 12M\npost_max_size = 14M\nmemory_limit = 256M\n' > "$PHP_INI_DIR/conf.d/uploads.ini"

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    SERVER_NAME=":10000"

EXPOSE 10000

# Atualiza o banco e os dados da pousada a cada deploy, depois sobe o servidor.
CMD ["sh", "docker/start.sh"]
