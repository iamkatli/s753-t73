# In apache/Dockerfile
FROM httpd:2.4.33-alpine

RUN apk update; \
    apk upgrade;

# Copy your custom Apache configuration
COPY ./public/dump/apache_php.conf /usr/local/apache2/conf/apache_php.conf
RUN echo "Include /usr/local/apache2/conf/apache_php.conf" \
    >> /usr/local/apache2/conf/httpd.conf

# Set the working directory to Apache's document root
#WORKDIR /usr/local/apache2/htdocs/

# Copy the application code from the project's public directory
# into Apache's document root.
# '../public' is relative to the 'apache/' build context directory.
#COPY ../public .
# The '.' means "copy into the current WORKDIR (/usr/local/apache2/htdocs/)"
# Alternatively: COPY ../public /usr/local/apache2/htdocs/

# Ensure correct permissions if Apache needs to write or if specific ownership is required
# RUN chown -R <user>:<group> /usr/local/apache2/htdocs/

# (The default CMD for httpd image will usually start Apache)