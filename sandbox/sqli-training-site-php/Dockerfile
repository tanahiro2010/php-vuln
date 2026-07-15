# Hifumi Secure Shop -- PHP + MySQL training image (Apache).
#
# Shared image for both the vulnerable and secure training apps.
# docker-compose.yml runs it twice with a different DOCROOT env var
# so the same Apache config serves either app_vulnerable.php or
# app_secure.php as its front controller.

FROM php:8.2-apache

# PDO MySQL is all we need. No composer, no framework -- kept minimal
# so there is nothing between the training code and the SQLi lessons
# it teaches.
RUN docker-php-ext-install pdo pdo_mysql

# Install our shared VirtualHost as the default site.
COPY apache/training.conf /etc/apache2/sites-available/000-default.conf

# Apache config uses ${DOCROOT}; make sure the variable is exported to
# every Apache child by adding it to envvars sourced by apachectl.
RUN echo 'export DOCROOT=${DOCROOT}' >> /etc/apache2/envvars

# App files live outside /var/www/html so they can't be reached
# accidentally through the default doc root.
WORKDIR /var/www/training
COPY . /var/www/training/

# No CMD here -- docker-compose.yml supplies the command per service
# (init_db.php first, then apache2-foreground).
