# Test-only build context MUST be the filtered scratch copy from run-tests.ps1.
# Digests verified against Docker Hub linux/amd64 manifests, 2026-11-07.
FROM composer:2@sha256:07cb23c4c9d528524a8b9a67c403d6ad7fef2268651eb1448e3874d40f28b789 AS composer
FROM php:8.2-cli-bookworm@sha256:26b388d187a5e592ef5e02c14d0f92dd9c761894ff0a5fe338183196471c4b4e AS php-dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip ca-certificates chromium fonts-dejavu-core libzip-dev libpng-dev \
    libjpeg62-turbo-dev libfreetype6-dev libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 pdo_mysql zip gd intl bcmath pcntl \
    && rm -rf /var/lib/apt/lists/*
# Pinned PHP 8.2.35 builds mbstring/pdo_sqlite in; dom/xml/xmlwriter are
# enabled by default (no corresponding --disable flags in image history).
# Verify both inherited and custom modules BEFORE any Composer installation.
RUN php -r '$required = ["dom","xml","xmlwriter","mbstring","pdo_mysql","pdo_sqlite","zip","gd","intl","bcmath","pcntl","ctype","filter","hash","openssl","session","tokenizer","fileinfo","iconv","libxml","pcre","Phar","json","SimpleXML"]; foreach ($required as $extension) { if (!extension_loaded($extension)) { fwrite(STDERR, "PHP_REQUIRED_EXTENSION_MISSING:".$extension.PHP_EOL); exit(44); } } sort($required); echo "PHP_REQUIRED_EXTENSIONS_VERIFIED:".implode(",", $required).PHP_EOL;'
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY composer.json composer.lock ./
# Generated inside the image only. Export derived categories/package names,
# never arbitrary Composer messages, credentials, URLs or environment values.
COPY <<'PHP' /usr/local/bin/ci-composer-diagnostics.php
<?php
$stage = $argv[1] ?? 'unknown';
$text = substr((string) file_get_contents($argv[2]), 0, 262144);
$categories = [];
foreach ([
    'missing_extension' => '/(?:requires? ext-|extension .*missing|missing.*ext-)/i',
    'php_version_mismatch' => '/(?:requires? php|php version.*(?:does not|not satisfy|incompatible))/i',
    'lock_inconsistency' => '/(?:lock file.*(?:not up to date|not contain|out of date)|not present in the lock file|not.*satisfy.*constraint)/i',
    'dependency_platform_error' => '/(?:could not be resolved|not.*compatible|platform|requirements|problem \d+)/i',
    'schema_validation' => '/(?:valid.*json|schema|invalid|error)/i',
    'download_or_transport' => '/(?:curl error|failed to download|could not resolve|connection|timed out)/i',
] as $category => $pattern) {
    if (preg_match($pattern, $text)) { $categories[] = $category; }
}
preg_match_all('/\bext-([a-zA-Z0-9_]+)\b/', $text, $extensions);
preg_match_all('/(?:^|\s|\[)([a-z0-9_.-]+\/[a-z0-9_.-]+)(?=\s|\]|:|$)/m', $text, $packages);
preg_match_all('/requires? php\s+([0-9.*^~<>=!|, -]+)/i', $text, $constraints);
$safe = static fn (array $values): array => array_slice(array_values(array_unique($values)), 0, 40);
echo 'COMPOSER_DIAGNOSTIC:'.json_encode([
    'stage' => $stage, 'php_version' => PHP_VERSION,
    'categories' => $categories ?: ['unclassified_composer_failure'],
    'mentioned_extensions' => $safe($extensions[1]),
    'mentioned_packages' => $safe($packages[1]),
    'php_constraints' => $safe(array_map('trim', $constraints[1])),
    'raw_messages_exported' => false,
], JSON_UNESCAPED_SLASHES).PHP_EOL;
PHP
# Public dependency-fetch network is build-only. Never boot Laravel here.
# Normal validation (including lock consistency) is a HARD gate, not strict mode.
RUN composer validate --no-check-publish --no-interaction --no-ansi > /tmp/validate.log 2>&1 \
    || { echo COMPOSER_VALIDATION_FAILED; php /usr/local/bin/ci-composer-diagnostics.php validate /tmp/validate.log; exit 40; }
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts --no-ansi > /tmp/install.log 2>&1 \
    || { echo COMPOSER_INSTALL_FAILED; php /usr/local/bin/ci-composer-diagnostics.php install /tmp/install.log; exit 41; }
RUN composer check-platform-reqs --no-interaction > /tmp/build-platform.log 2>&1 \
    || { echo COMPOSER_PLATFORM_FAILED; php /usr/local/bin/ci-composer-diagnostics.php platform /tmp/build-platform.log; exit 42; }

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
    && cp phpunit.mysql.xml phpunit.xml \
    && mkdir -p storage/framework/testing storage/framework/cache/data \
       storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod +x tools/testing/run-baseline.sh
RUN for file in tools/testing/*.php; do php -l "$file" > /dev/null || exit 43; done
RUN sh -n tools/testing/run-baseline.sh \
    && for file in app/Http/Controllers/RetiredNotificationChannelController.php app/Services/Support/NotificationService.php app/Services/WhatsApp/N8nWhatsAppClient.php app/Services/WhatsApp/WhatsAppNotificationService.php app/Services/WhatsApp/WhatsAppOtpService.php app/Jobs/DispatchWhatsAppNotification.php app/Jobs/DispatchWhatsAppOtp.php config/services.php routes/web.php tests/Feature/Mobile/Phase6/NotificationChannelPolicyTest.php tests/Feature/PhoneStateCompatibilityTest.php; do php -l "$file" > /dev/null || exit 43; done
RUN find app routes tests -name '*.php' -print0 | xargs -0 -n1 php -l > /tmp/phase7-php-lint.log 2>&1
CMD ["sh", "tools/testing/run-baseline.sh"]
