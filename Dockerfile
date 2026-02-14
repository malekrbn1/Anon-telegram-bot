FROM php:8.2-cli

WORKDIR /app

COPY . /app

# اگر اکستنشنی لازم داری، اینجا نصب کن (اختیاری)
# RUN docker-php-ext-install mysqli

# Railway متغیر PORT رو ست می‌کنه، ما هم از همون استفاده می‌کنیم
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8000} index.php"]