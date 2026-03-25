# ==========================================
# POS WMS Backend - Production Dockerfile
# Laravel 13.x + PHP 8.3 + Nginx
# ==========================================

# ------------------------------------------
# Stage 1: Composer Dependencies
# ------------------------------------------
FROM php:8.3-cli AS composer

WORKDIR /var/www/html

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY composer.json composer.lock ./

RUN composer install --no-dev --no-interaction --no-plugins --no-scripts --prefer-dist

# ------------------------------------------
# Stage 2: Frontend Assets Build
# ------------------------------------------
FROM node:20-alpine AS frontend

WORKDIR /var/www/html

COPY package.json package-lock.json* ./

RUN npm ci --only=production || npm install --only=production

COPY . .

# Build frontend assets
RUN npm run build

# ------------------------------------------
# Stage 3: Production Image
# ------------------------------------------
FROM php:8.3-fpm-alpine AS production

# Add labels for better maintainability
LABEL maintainer="POS WMS Team"
LABEL description="POS WMS Backend API - Laravel 13.x"
LABEL version="1.0.0"

WORKDIR /var/www/html

# Install system dependencies
RUN apk add --no-cache \
    supervisor \
    nginx \
    sqlite \
    sqlite-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    icu-dev \
    oniguruma-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    bash \
    curl \
    shadow \
    # PostgreSQL support (optional)
    postgresql-dev \
    # MySQL/MariaDB support (optional)
    mariadb-dev

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
    gd \
    pdo_mysql \
    pdo_pgsql \
    pdo_sqlite \
    intl \
    opcache \
    exif \
    bcmath \
    soap \
    xml \
    tokenizer

# Install Redis extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# Copy composer from builder stage
COPY --from=composer /var/www/html/vendor ./vendor
COPY --from=composer /var/www/html/vendor/composer/autoload_files.php ./vendor/composer/autoload_files.php

# Copy frontend assets from builder stage
COPY --from=frontend /var/www/html/public/build ./public/build

# Copy application files
COPY . .

# Copy optimized composer autoload (skip dev files)
COPY --from=composer /var/www/html/vendor/composer/autoload_classmap.php ./vendor/composer/autoload_classmap.php
COPY --from=composer /var/www/html/vendor/composer/autoload_psr4.php ./vendor/composer/autoload_psr4.php

# Create system user for running the app
RUN addgroup -g 1000 -S appgroup && \
    adduser -u 1000 -S appuser -G appgroup

# Set ownership and permissions
RUN chown -R appuser:appgroup /var/www/html && \
    chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache

# Copy Nginx configuration
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Copy Supervisor configuration
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy PHP-FPM configuration
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Create necessary directories
RUN mkdir -p /var/log/nginx /var/log/supervisor /run/nginx && \
    chown -R appuser:appgroup /var/log/nginx /var/log/supervisor /run/nginx

# Expose port 80
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD wget --no-verbose --tries=1 --spider http://localhost:80/api/health || exit 1

# Switch to non-root user
USER appuser

# Start Supervisor (manages Nginx and PHP-FPM)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
