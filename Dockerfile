FROM php:8.2-apache

# Install ekstensi MySQLi dan PDO MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable mysqli pdo_mysql

# Enable ModRewrite Apache (untuk .htaccess)
RUN a2enmod rewrite

# Salin seluruh isi proyek ke folder web Apache
COPY . /var/www/html/

EXPOSE 80
