#!/bin/bash
# startup.sh — Configura Apache en Azure App Service para servir desde public/
# Se ejecuta antes de que Apache arranque (configurado en "Startup Command" de la Web App).

set -e

echo "=== Tinkuy: configurando Apache en Azure App Service ==="

APACHE_CONF="/etc/apache2/sites-enabled/000-default.conf"
APACHE_CONF_AVAILABLE="/etc/apache2/sites-available/000-default.conf"

# Usar el archivo disponible si el enabled no existe
[ -f "$APACHE_CONF" ] || APACHE_CONF="$APACHE_CONF_AVAILABLE"

if [ ! -f "$APACHE_CONF" ]; then
    echo "ERROR: No se encontró la configuración de Apache en $APACHE_CONF"
    exit 1
fi

echo "Actualizando DocumentRoot a /home/site/wwwroot/public ..."

# Cambiar DocumentRoot de wwwroot a wwwroot/public
sed -i 's|DocumentRoot /home/site/wwwroot$|DocumentRoot /home/site/wwwroot/public|g' "$APACHE_CONF"
sed -i 's|DocumentRoot /home/site/wwwroot"|DocumentRoot /home/site/wwwroot/public"|g' "$APACHE_CONF"

# Inyectar bloque Directory para habilitar AllowOverride (necesario para .htaccess)
if ! grep -q "wwwroot/public" "$APACHE_CONF"; then
    cat >> "$APACHE_CONF" << 'APACHE_BLOCK'

<Directory /home/site/wwwroot/public>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
APACHE_BLOCK
fi

# Habilitar módulos requeridos por el proyecto
a2enmod rewrite 2>/dev/null || true
a2enmod headers 2>/dev/null || true

echo "=== Configuración completada. Iniciando Apache ==="
apache2-foreground
