FROM php:8.4-cli-bookworm

RUN apt-get update && apt-get install -y \
        git \
        unzip \
        curl \
        ca-certificates \
        libzip-dev \
        libpq-dev \
        libicu-dev \
        poppler-utils \
    && docker-php-ext-install pdo_pgsql pgsql zip intl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

EXPOSE 8000 5173
