# Base image
FROM serversideup/php:8.5-frankenphp

# Disable SSL (will be handled by proxy).
ENV SSL_MODE=off

# Enable opcache.
ENV PHP_OPCACHE_ENABLE=1

# Set timezone.
ENV PHP_DATE_TIMEZONE=Europe/Berlin

# Set custom healthcheck path (from Laravel).
ENV HEALTHCHECK_PATH=/up

# Run as www-data user.
USER www-data

# Copy application code, skipping files based on .dockerignore.
COPY --chown=www-data:www-data . /var/www/html

# Install Composer dependencies.
RUN composer install --optimize-autoloader --no-dev --no-scripts
