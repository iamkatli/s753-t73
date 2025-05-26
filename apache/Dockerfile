# In apache/Dockerfile
FROM httpd:2.4.33-alpine

RUN apk update; \
    apk upgrade;

# Copy your custom Apache configuration
COPY apache_php.conf /usr/local/apache2/conf/apache_php.conf
RUN echo "Include /usr/local/apache2/conf/apache_php.conf" \
    >> /usr/local/apache2/conf/httpd.conf

# Create the DocumentRoot directory if it might not exist (though /var/www/html is common)
# and ensure the Apache user ('daemon' for httpd:alpine) owns it.
RUN mkdir -p /var/www/html/ && chown -R daemon:daemon /var/www/html/

# Set the working directory to make the COPY command simpler
WORKDIR /var/www/html/

# Copy the application code from your project's php/public directory
# into Apache's document root (/var/www/html/).
# The path '../php/public' is relative to the 'apache/' build context directory.
COPY public /var/www/html/
# The '.' means "copy everything from ../php/public into the current WORKDIR (/var/www/html/)"

# Ensure the copied files are also owned by the Apache user
RUN chown -R daemon:daemon /var/www/html/

# The default CMD for the httpd image will usually start Apache.