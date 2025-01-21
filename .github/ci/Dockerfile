FROM php:8.3-cli

WORKDIR "/app"

COPY .github/ci/php.ini /usr/local/etc/php/conf.d/zzz-custom.ini
COPY --chmod=0755 .github/ci/install-composer /tmp/install-composer
COPY . /app

RUN curl -o- https://deb.nodesource.com/setup_20.x | bash - && \
    apt update -qqy && \
    apt install -qqy \
      git \
      unzip \
      libicu-dev \
      libicu72 \
      libcurl4-openssl-dev \
      libonig-dev \
      libfcgi-bin \
      libxml2-dev \
      zlib1g-dev \
      libpng-dev \
      7zip \
      libzip-dev \
      libjpeg-dev \
      libfreetype-dev \
      && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install pdo_mysql intl iconv bcmath mbstring soap gd zip opcache pcntl && \
    pecl install xdebug && \
    docker-php-ext-enable xdebug && \
    /tmp/install-composer && \
    rm -f /tmp/install-composer && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* && \
    composer install -no --no-plugins
