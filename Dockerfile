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

# Railway inyecta la variable $PORT, así que configuramos Apache para que escuche en ese puerto.
# Además forzamos la desactivación de los MPM problemáticos justo antes de arrancar.
CMD sed -i "s/80/${PORT:-80}/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf && \
    a2dismod mpm_event mpm_worker 2>/dev/null || true && \
    a2enmod mpm_prefork 2>/dev/null || true && \
    apache2-foreground
