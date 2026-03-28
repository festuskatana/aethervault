FROM php:8.1-apache

# Install extensions for both MySQL and PostgreSQL support
RUN apt-get update \
    && apt-get install -y libpq-dev \
    && docker-php-ext-install mysqli pdo pdo_mysql pdo_pgsql pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache rewrite
RUN a2enmod rewrite

# Copy project files
COPY . /var/www/html/

# Set permissions (important for uploads)
RUN chmod -R 777 /var/www/html/
