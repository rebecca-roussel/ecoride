FROM php:8.3-fpm

# Dépendances système
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        intl \
        zip \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb

# Composer (outil de gestion des dépendances PHP)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Nettoyage
RUN apt-get clean && rm -rf /var/lib/apt/lists/*
RUN echo "date.timezone = Europe/Paris" > /usr/local/etc/php/conf.d/timezone.ini

WORKDIR /var/www/html

