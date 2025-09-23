FROM php:8.3-apache

# Cài đặt các extension PHP cần thiết (bổ sung nếu cần)
RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable mysqli pdo_mysql

# Bật mod_rewrite (thường cần cho PHP app)
RUN a2enmod rewrite

# Đặt thư mục public làm DocumentRoot
WORKDIR /var/www/html
COPY ./public /var/www/html

# Đặt quyền cho www-data
RUN chown -R www-data:www-data /var/www/html

# Cấu hình Apache để sử dụng public/ làm root
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html\n\
    <Directory /var/www/html>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf
