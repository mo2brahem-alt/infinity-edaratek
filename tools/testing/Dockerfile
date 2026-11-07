# Test-only build context MUST be the filtered scratch copy from run-tests.ps1.
# Digests verified against Docker Hub linux/amd64 manifests, 2026-11-07.
FROM composer:2@sha256:07cb23c4c9d528524a8b9a67c403d6ad7fef2268651eb1448e3874d40f28b789 AS composer
FROM php:8.2-cli-bookworm@sha256:26b388d187a5e592ef5e02c14d0f92dd9c761894ff0a5fe338183196471c4b4e AS php-dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip ca-certificates libonig-dev libxml2-dev libzip-dev libpng-dev \
    libjpeg62-turbo-dev libfreetype6-dev libsqlite3-dev libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 pdo_mysql pdo_sqlite mbstring zip gd intl bcmath pcntl \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY composer.json composer.lock ./
# Public dependency-fetch network is build-only. Never boot Laravel here.
# Normal validation (including lock consistency) is a HARD gate, not strict mode.
RUN composer validate --no-check-publish --no-interaction --no-ansi > /tmp/validate.log 2>&1 \
    || { echo COMPOSER_VALIDATION_FAILED; exit 40; }
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts --no-ansi > /tmp/install.log 2>&1 \
    || { echo COMPOSER_INSTALL_FAILED; exit 41; }
RUN composer check-platform-reqs --no-interaction --format=json > /tmp/build-platform.json \
    || { echo COMPOSER_PLATFORM_FAILED; exit 42; }

# Vite 7.3.1 / plugin-vue lock requirements: Node ^20.19.0 || >=22.12.0.
# This immutable linux/amd64 image reports Node 22.23.4; no npm auth is supplied.
FROM node:22-bookworm-slim@sha256:aaa4b5baf1ecff3b283486bb1f90405cdda225a3e6f79660d2c559fa510a7292 AS frontend
WORKDIR /ui
ENV CI=true
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY resources ./resources
COPY vite.config.js postcss.config.js tailwind.config.js jsconfig.json ./
# Match Tailwind's existing vendor Blade content glob without host vendor files.
COPY --from=php-dependencies /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build && test -s public/build/manifest.json \
    && node -p process.versions.node > /ui/frontend-node-version.txt

FROM php-dependencies AS runtime
COPY . .
COPY --from=frontend /ui/public/build ./public/build
COPY --from=frontend /ui/frontend-node-version.txt /opt/frontend-node-version.txt
# Dedicated test bootstrap modifies only the image's disposable test superclass.
RUN cp tools/testing/IsolatedTestCase.php tests/TestCase.php \
    && mkdir -p storage/framework/testing storage/framework/cache/data \
       storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod +x tools/testing/run-baseline.sh
RUN for file in tools/testing/*.php; do php -l "$file" > /dev/null || exit 43; done
CMD ["sh", "tools/testing/run-baseline.sh"]
