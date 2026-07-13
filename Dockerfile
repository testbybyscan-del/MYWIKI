FROM php:8.2-apache

# Установка системных зависимостей
RUN apt-get update && apt-get install -y \
    libonig-dev \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

# Установка PHP-расширений и включение mod_rewrite
RUN docker-php-ext-install mbstring pdo_pgsql \
    && a2enmod rewrite

# Копирование всех файлов приложения
COPY . /var/www/html/

# Настройка прав: все файлы принадлежат www-data, папка pages доступна для записи,
# а логи будем писать в /tmp (вне веб-доступа)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/pages \
    && rm -f /var/www/html/wiki_debug.log  # удаляем старый лог, если есть

# Создаём пустой файл для логов в /tmp (с правами www-data)
RUN touch /tmp/wiki_debug.log \
    && chown www-data:www-data /tmp/wiki_debug.log \
    && chmod 666 /tmp/wiki_debug.log

# Настраиваем PHP на запись ошибок в syslog (для продакшена)
RUN echo "error_log = syslog" >> /usr/local/etc/php/php.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/php.ini \
    && echo "display_errors = Off" >> /usr/local/etc/php/php.ini \
    && echo "display_startup_errors = Off" >> /usr/local/etc/php/php.ini

# Открываем порт 80
EXPOSE 80

CMD ["apache2-foreground"]
