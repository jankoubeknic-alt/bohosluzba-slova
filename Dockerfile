FROM php:8.3-apache

# Generátor je jediný soubor. Složka cache/ musí být zapisovatelná (stažené stránky).
COPY bohosluzba.php /var/www/html/bohosluzba.php
RUN mkdir -p /var/www/html/cache && chown www-data:www-data /var/www/html/cache
