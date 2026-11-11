FROM php:8.4-cli-alpine

# Install pre-built runtime libraries and tools without building from source
RUN apk add --no-cache \
    postgresql-client \
    postgresql-libs \
    libzip \
    libpng \
    libjpeg-turbo \
    freetype \
    icu-libs \
    git \
    unzip

# Install build dependencies, compile extensions, and remove build dependencies immediately
RUN apk add --no-cache --virtual .build-deps \
        postgresql-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        icu-dev \
        linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        bcmath \
        gd \
        zip \
        pcntl \
        intl \
    && apk del .build-deps

# Copy composer from official image
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

EXPOSE 8000 5173

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
