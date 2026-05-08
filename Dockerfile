# Используем официальный образ с PHP и Apache
FROM php:8.2-apache

# Включаем mod_rewrite для Apache
RUN a2enmod rewrite

# Копируем все файлы из папки с ботом в папку на сервере
COPY . /var/www/html/

# Даем права на запись, чтобы бот мог сохранять данные в db.json
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html