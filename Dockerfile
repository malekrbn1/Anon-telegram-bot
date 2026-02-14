FROM php:8.2-apache

# غیرفعال کردن تمام MPMهای اضافی
RUN a2dismod mpm_event || true
RUN a2dismod mpm_worker || true

# فعال کردن فقط mpm_prefork (سازگار با PHP)
RUN a2enmod mpm_prefork

# نصب اکستنشن‌های لازم
RUN docker-php-ext-install mysqli

# کپی کردن سورس
COPY . /var/www/html/

EXPOSE 80