ARG PHP_VERSION=8.2
FROM php:${PHP_VERSION}-cli-slim

ARG UID=1000
ARG GID=1000
ARG TIMEZONE=Africa/Luanda
ARG INSTALL_MYSQL=true
ARG INSTALL_PGSQL=true
ARG INSTALL_SQLITE=true

ENV TZ=${TIMEZONE}
ENV USER=laravel

# System dependencies and PHP build tools
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip curl ca-certificates \
    libcurl4-openssl-dev libssl-dev libzip-dev \
    libonig-dev libxml2-dev libreadline-dev \
    libpng-dev libzstd-dev libbrotli-dev \
    libicu-dev libsqlite3-dev libpq-dev \
    libjpeg-dev libfreetype6-dev libevent-dev \
    libaio-dev liburing-dev zlib1g-dev \
    php-pear php-dev pkg-config gcc g++ make autoconf tzdata \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

# Create non-root user
RUN groupadd -g ${GID} ${USER} && \
    useradd -u ${UID} -g ${USER} -m ${USER}

# Install PHP extensions required by Laravel
RUN docker-php-ext-configure gd --with-jpeg --with-freetype && \
    docker-php-ext-install -j$(nproc) \
    zip pdo pdo_mysql pdo_pgsql pdo_sqlite \
    bcmath curl mbstring xml pcntl sockets \
    exif gd intl opcache

# Install Swoole
RUN printf "\n" | pecl install swoole-5.1.1 && \
    echo "extension=swoole.so" > /usr/local/etc/php/conf.d/swoole.ini

# Install Composer globally
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Optional: Install database clients
RUN if [ "$INSTALL_MYSQL" = "true" ]; then \
    apt-get update && apt-get install -y default-mysql-client; fi

RUN if [ "$INSTALL_PGSQL" = "true" ]; then \
    apt-get update && apt-get install -y postgresql-client; fi

WORKDIR /var/www
COPY . .

# Composer install optimized
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Set permissions
RUN chown -R ${USER}:${USER} /var/www
USER ${USER}

# Startup script
COPY deploy/start.sh /usr/local/bin/start
RUN chmod +x /usr/local/bin/start

EXPOSE 9000
CMD ["start"]
