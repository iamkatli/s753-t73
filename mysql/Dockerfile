# mysql/Dockerfile
# Use the official MySQL 8.0 image as the base
FROM mysql:8.0

# The official mysql image will automatically execute .sql, .sh, .sql.gz files
# found in /docker-entrypoint-initdb.d/ when the container starts for the
# first time and the data directory is empty.

# Copy your dump.sql file into this directory within the image.
# The source path '../public/dump/dump.sql' is relative to the 'mysql/' build context.
COPY ./public/dump/dump.sql /docker-entrypoint-initdb.d/dump.sql

# You can add other customizations here if needed, like custom my.cnf files:
# COPY custom.cnf /etc/mysql/conf.d/custom.cnf

# The base image already sets the appropriate CMD and EXPOSE for MySQL.