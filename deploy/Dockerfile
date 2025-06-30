# ============================
# Stage 1: Build Dependencies
# ============================
ARG PHP_VERSION=8.2
FROM php:${PHP_VERSION}-cli-buster AS builder

LABEL maintainer="Délcio Cabanga <delcio_cabanga@outlook.com>"

ARG TIMEZONE=Africa/Luanda
ENV TZ=${TIMEZONE}

# Timezone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime \
    && echo $TZ > /etc/timezone

# System dependencies and PHP build tools
RUN apt-get update && \
    apt-get upgrade -y && \
    pecl channel-update pecl.php.net && \
    apt-get install -yqq --no-install-recommends --show-progress \
        apt-utils \
        gnupg \
        git \
        curl \
        wget \
        libcurl4-openssl-dev \
        ca-certificates \
        libmemcached-dev \
        libz-dev \
        libbrotli-dev \
        libpq-dev \
        libjpeg-dev \
        libpng-dev \
        libfreetype6-dev \
        libwebp-dev \
        libssl-dev \
        libmcrypt-dev \
        libonig-dev \
        libzip-dev \
        libsqlite3-dev \
        libxml2-dev \
        libargon2-1 \
        libidn2-0 \
        libpcre2-8-0 \
        libpcre3 \
        libzstd1 \
        zip unzip && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# GD fix: configure with required libs
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp && \
    docker-php-ext-install -j$(nproc) \
        zip pdo pdo_mysql pdo_pgsql pdo_sqlite \
        bcmath curl mbstring xml pcntl sockets \
        exif gd intl opcache

# Install Swoole
RUN printf "yes\nyes\nyes\nyes\n" | pecl install swoole-5.1.1 && \
    echo "extension=swoole.so" > /usr/local/etc/php/conf.d/swoole.ini

# Install Composer globally
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory for dependencies installation
WORKDIR /var/www

# Copy composer files first (for better layer caching)
COPY composer.json composer.lock ./

# Install Composer dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev --no-scripts

# ============================
# Stage 2: Production Runtime
# ============================
FROM php:${PHP_VERSION}-cli-buster AS production

LABEL maintainer="Délcio Cabanga <delcio_cabanga@outlook.com>"

ARG UID=1000
ARG GID=1000
ARG USERNAME_FOR_APP=laravel
ARG TIMEZONE=Africa/Luanda
ARG INSTALL_MYSQL=true
ARG INSTALL_PGSQL=true
ARG INSTALL_SQLITE=true

ENV TZ=${TIMEZONE}
ENV USER=$USERNAME_FOR_APP
ENV PATH="/home/${USERNAME_FOR_APP}/.composer/vendor/bin:${PATH}"

# Timezone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime \
    && echo $TZ > /etc/timezone

# Install only runtime dependencies
RUN apt-get update && \
    apt-get upgrade -y && \
    apt-get install -yqq --no-install-recommends --show-progress \
        gosu \
        git \
        curl \
        ca-certificates \
        supervisor \
        libmemcached11 \
        libpq5 \
        libjpeg62-turbo \
        libpng16-16 \
        libfreetype6 \
        libwebp6 \
        libonig5 \
        libzip4 \
        libsqlite3-0 \
        libxml2 \
        libargon2-1 \
        libidn2-0 \
        libpcre2-8-0 \
        libpcre3 \
        libzstd1 \
        libbrotli1 \
        libssl1.1 \
        tzdata \
        procps \
        zip unzip && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Create non-root user
RUN groupadd -g ${GID} ${USER} && \
    useradd -u ${UID} -g ${USER} -m ${USER} -s /bin/bash

# Copy PHP extensions and configurations from builder
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Copy Composer from builder
COPY --from=builder /usr/local/bin/composer /usr/local/bin/composer

# Optional: Install database clients
RUN if [ "$INSTALL_MYSQL" = "true" ]; then \
    apt-get update && apt-get install -y default-mysql-client && \
    apt-get clean && rm -rf /var/lib/apt/lists/*; fi

RUN if [ "$INSTALL_PGSQL" = "true" ]; then \
    apt-get update && apt-get install -y postgresql-client && \
    apt-get clean && rm -rf /var/lib/apt/lists/*; fi

# Set working directory
WORKDIR /var/www

# Copy Composer dependencies from builder stage
COPY --from=builder /var/www/vendor ./vendor

# Copy the rest of the application files
COPY . .

# Create Laravel directories and set permissions
RUN mkdir -p storage/logs storage/framework/{cache,sessions,views} bootstrap/cache && \
    chown -R ${USER}:${USER} /var/www && \
    chmod -R 755 storage bootstrap/cache

# Startup script
COPY deploy/start.sh /usr/local/bin/start
RUN chmod +x /usr/local/bin/start && chown ${USER}:${USER} /usr/local/bin/start

# Switch to non-root user
USER ${USER}

EXPOSE 9000
CMD ["start"]
