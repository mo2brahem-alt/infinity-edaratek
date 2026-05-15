FROM php:8.2-cli

# تثبيت الحزم الأساسية بالإضافة إلى Node.js و npm
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    curl \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && docker-php-ext-install pdo_mysql zip

# تثبيت مدير الحزم Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# تحديد مجلد العمل
WORKDIR /app
COPY . .

# تحميل مكتبات لارافيل (Backend)
RUN composer install --no-dev --optimize-autoloader

# تحميل وبناء ملفات Vue 3 (Frontend)
RUN npm install
RUN npm run build

# إعطاء صلاحيات لمجلد التخزين
RUN chmod -R 775 storage bootstrap/cache

# تشغيل المشروع
CMD php artisan serve --host=0.0.0.0 --port=${PORT:-10000}
