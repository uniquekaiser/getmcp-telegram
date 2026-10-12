FROM wordpress:6.8.3-php8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends libgmp-dev libonig-dev libxml2-dev unzip git && docker-php-ext-install gmp mbstring && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
RUN curl -fsSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp && chmod +x /usr/local/bin/wp
