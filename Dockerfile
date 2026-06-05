FROM php:8.4-cli-bookworm

# Instalamos herramientas base y librerías de Firebird.
# firebird-dev incluye headers y libfbclient necesarios para compilar pdo_firebird.
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    libzip-dev \
    libsqlite3-dev \
    firebird-dev \
    && docker-php-ext-configure pdo_firebird --with-pdo-firebird=/usr \
    && docker-php-ext-install pdo_firebird pdo_mysql pdo_sqlite zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer oficial
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
