FROM php:7.2.7-fpm-alpine3.7

RUN apk update; \
    apk upgrade;

RUN docker-php-ext-install mysqli

# Set the working directory to Apache's document root
WORKDIR /var/www/html/

# Copy the application code from the project's public directory
# into Apache's document root.
# '../public' is relative to the 'apache/' build context directory.
COPY ./public/ /var/www/html/
# The '.' means "copy into the current WORKDIR (/usr/local/apache2/htdocs/)"
# Alternatively: COPY ../public /usr/local/apache2/htdocs/

# Ensure correct permissions if Apache needs to write or if specific ownership is required
# RUN chown -R <user>:<group> /usr/local/apache2/htdocs/
