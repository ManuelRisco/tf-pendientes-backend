FROM php:8.2-apache

# Habilitar mod_rewrite de Apache para que funcione el enrutador
RUN a2enmod rewrite

# Forzar el uso del MPM correcto para evitar conflicto en Railway
RUN a2dismod mpm_event mpm_worker || true && a2enmod mpm_prefork

# Instalar extensiones PDO y MySQL para PHP
RUN docker-php-ext-install pdo pdo_mysql

# Configurar Apache para que permita sobrescribir configuraciones (.htaccess)
RUN echo "<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>" > /etc/apache2/conf-available/allow-override.conf && \
    a2enconf allow-override

# Copiar todo el código del backend al directorio de Apache
COPY . /var/www/html/

# Dar permisos a la carpeta por si acaso
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Exponer el puerto 80 (Railway lo usa por defecto para HTTP)
EXPOSE 80
