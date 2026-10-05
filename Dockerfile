ARG PHP_VERSION=8.5
 
FROM php:${PHP_VERSION}-apache
 
# Install PDO MySQL
RUN docker-php-ext-install pdo pdo_mysql
 
# Enable Apache mod_rewrite
RUN a2enmod rewrite
 
# Allow .htaccess overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
 
# Copy app files
COPY . /var/www/html/
 
# Fix permissions
RUN chown -R www-data:www-data /var/www/html \
&& chmod -R 755 /var/www/html
 
# Point Apache document root to public/
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
 
RUN sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/000-default.conf \
&& sed -ri "s!<Directory /var/www/>!<Directory ${APACHE_DOCUMENT_ROOT}/>!" /etc/apache2/apache2.conf

EXPOSE 80

CMD ["sh", "-c", "php /var/www/html/lava migration run && exec apache2-foreground"]