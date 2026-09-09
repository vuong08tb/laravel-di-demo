FROM php:8.5-cli

ENV TZ=Asia/Ho_Chi_Minh

WORKDIR /var/www

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    tzdata \
    && ln -snf /usr/share/zoneinfo/$TZ /etc/localtime \
    && echo $TZ > /etc/timezone \
    && echo "date.timezone=${TZ}" > /usr/local/etc/php/conf.d/timezone.ini \
    && docker-php-ext-install \
        pdo_pgsql \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./

RUN composer install \
    --no-interaction \
    --prefer-dist \
    --no-scripts

COPY . .

RUN composer dump-autoload --optimize

# Chay container bang UID/GID 1000 (trung UID cua user tren host) de file
# sinh ra trong bind mount thuoc ve host user, khong bi root chiem quyen.
RUN groupadd -g 1000 app \
    && useradd -u 1000 -g 1000 -m -s /bin/bash app \
    && chown -R 1000:1000 /var/www

USER app

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
