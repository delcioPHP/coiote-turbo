# Coiote Turbo

<p>
    <a href="https://packagist.org/packages/cabanga/coiote-turbo"><img alt="Latest Stable Version" src="https://img.shields.io/packagist/v/cabanga/coiote-turbo.svg?style=for-the-badge"></a>
    <a href="https://packagist.org/packages/cabanga/coiote-turbo"><img alt="Total Downloads" src="https://img.shields.io/packagist/dt/cabanga/coiote-turbo.svg?style=for-the-badge"></a>
    <a href="https://github.com//delcioPHP/coiote-turbo/blob/main/LICENSE.md"><img alt="License" src="https://img.shields.io/packagist/l/cabanga/coiote-turbo.svg?style=for-the-badge"></a>
</p>

A fast, lightweight, and high-performance application server for Laravel, powered by Swoole. Coiote Turbo dramatically reduces latency and boosts your application's capacity by keeping the framework bootstrapped in memory.

## About Coiote Turbo

Traditional PHP applications running under PHP-FPM have to bootstrap the entire Laravel framework on every single request. This process, while fast, introduces a small amount of latency that adds up under heavy load.

Coiote Turbo solves this by running your Laravel application on top of a powerful, event-driven Swoole server. The framework is booted only once, when the server starts, and then stays resident in memory to process thousands of requests per second with minimal overhead.

This package provides an elegant and robust way to manage the Swoole server, including integrated supervisors for your queue workers and scheduled tasks, transforming your Laravel application into a long-running, high-performance service.

## Key Features

- **High-Performance HTTP Server**: Serves your application directly via Swoole, eliminating the need for Nginx or Apache.
- **Drastically Reduced Latency**: By keeping the framework in memory, response times are significantly faster.
- **Integrated Queue Worker**: A multi-process queue worker supervisor, managed directly by Coiote Turbo.
- **Integrated Scheduler**: A dedicated process to run your Laravel scheduled tasks reliably.
- **Simple Configuration**: A single configuration file to manage server settings.
- **Easy-to-Use Commands**: Familiar `artisan` commands to start, stop, and monitor your server.

## Requirements

- PHP `^8.2`
- Laravel Framework `^10.0 || ^11.0 || ^12`
- PHP Extension: `swoole` (version `^5.1` recommended)
- PHP Extension: `pcntl` (usually included in CLI builds, required for worker management)


## Shell script to install Laravel dependencies with Swoole (Ubuntu/Debian)

```bash

#!/bin/bash

# Update system packages
sudo apt update

# Add ondrej/php PPA if PHP 8.2 is not available
if ! apt-cache show php8.2 >/dev/null 2>&1; then
  echo "Adding PHP 8.2 PPA..."
  sudo apt install -y software-properties-common
  sudo add-apt-repository -y ppa:ondrej/php
  sudo apt update
fi


# Install PHP 8.2 and common Laravel extensions
sudo apt install -y php8.2 php8.2-cli php8.2-common php8.2-mbstring php8.2-xml php8.2-curl php8.2-mysql php8.2-sqlite3 php8.2-zip php8.2-bcmath php8.2-intl php8.2-opcache php8.2-readline php-pear php-dev unzip curl git zlib1g-dev

# Install Composer (if not present)
if ! command -v composer &> /dev/null; then
  curl -sS https://getcomposer.org/installer | php
  sudo mv composer.phar /usr/local/bin/composer
fi

# Ensure PECL is available
sudo apt install -y php-pear php-dev

# Uninstall any existing Swoole
sudo pecl uninstall swoole || true

# Install Swoole 5.1.1 with zlib and OpenSSL support
sudo pecl install swoole-5.1.1 --enable-openssl --with-zlib

# Add Swoole to php.ini if not already present
PHP_INI=$(php --ini | grep "Loaded Configuration" | awk '{print $NF}')
if ! grep -q "extension=swoole" "$PHP_INI"; then
  echo "extension=swoole" | sudo tee -a "$PHP_INI"
fi

# Confirm Swoole + zlib is active
php --ri swoole | grep zlib


```

## Installation (Coiote Turbo)

You can install the package via Composer:

```bash
composer require cabanga/coiote-turbo
```

**Note**: You must have the `swoole` and `pcntl` PHP extensions installed on your server. You can usually install Swoole by running `pecl install swoole`.


## Configuration

To publish the configuration file, run the following Artisan command. The configuration will be placed in `config/coioteTurbo.php`.

```bash
php artisan vendor:publish --provider="Cabanga\CoioteTurbo\CoioteTurboServiceProvider" --tag="config"
```

This file allows you to configure the server's host, port, number of workers, PID file location, and more.

## Usage

Coiote Turbo is controlled via simple Artisan commands. You will run these commands on your production server, preferably managed by a process manager like `Supervisor` or `systemd`.

#### Starting the HTTP Server

To start the main HTTP server and your application workers, run:

```bash
php artisan coiote:start
```

You can specify the number of application workers with the `--workers` option:

```bash
# Start the server
php artisan coiote:start
```

#### Running Queue Workers

To start the dedicated queue worker pool, run the `coiote:work` command. This will start a supervisor that manages multiple queue processes.

```bash
# Start a worker pool with 8 processes for the default queue
php artisan coiote:work

# Specify queues and connection
php artisan coiote:work --workers=4 --queue=high,default --connection=redis
```


#### Running the Scheduler

To start the scheduler, run the `turbo:schedule` command. This will start a single, long-running process that dispatches your scheduled jobs every minute.


```bash
php artisan coiote:schedule
```


#### Checking Server Status

To check if the main server is running, use the `status` command:

```bash
php artisan coiote:status
```

## Setup  (Docker)

## Step 1: Copy Deployment Files

Copy the deploy folder (https://github.com/delcioPHP/coiote-turbo/tree/main/deploy) into the root of your Laravel project 
and move the Dockerfile to the root project:

## Step 2: Verify Project Structure

Your project structure should look like this:

```
your-laravel-project/
├── deploy/
│   ├── server.conf
│   └── start.sh
├── Dockerfile
├── composer.json
├── composer.lock
└── ... 
```

---

## Step 3: Configure Server

Edit `deploy/server.conf` to customize how Coiote Turbo starts.

```bash
# Start the HTTP server with 2 worker processes
command:start=php artisan coiote:start

# Start the queue worker with high and default priority queues using Redis
# command:queue=php artisan coiote:work --queue=high,default --connection=redis

# Start the queue worker in default mode
# command:queue=php artisan coiote:work

# Start the task scheduler process
# command:schedule=php artisan coiote:schedule
```

Uncomment or adjust commands based on the desired process:
- `start`: HTTP server
- `queue`: queue worker
- `schedule`: scheduler

---

### Build the Docker Image

```bash
# Build with default PHP version (8.2)
docker build -t your-app-name .
docker run -p 9000:9000 your-app-name
```

## License

The Coiote Turbo is open-source software licensed under the MIT license.