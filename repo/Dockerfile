FROM php:8.4-fpm AS base

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    zlib1g-dev \
    unzip \
    curl \
    git \
    openssl \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        bcmath \
        gd \
        zip \
        pcntl \
        intl \
        opcache

# Install Composer from official image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Node.js 20 LTS
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www

# Copy all source files
COPY . .

# Install PHP dependencies (single pass — ensures vendor/bin/ links exist)
RUN composer install --no-interaction --optimize-autoloader

# Install Node dependencies and build frontend assets
RUN npm ci && npm run build

# Set permissions on storage and bootstrap/cache
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Copy infra scripts to /usr/local/bin/ and make executable
COPY infra/app/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY infra/app/bootstrap-secrets.sh /usr/local/bin/bootstrap-secrets.sh
COPY infra/app/bootstrap-https.sh /usr/local/bin/bootstrap-https.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/bootstrap-secrets.sh \
    && chmod +x /usr/local/bin/bootstrap-https.sh

EXPOSE 9000
