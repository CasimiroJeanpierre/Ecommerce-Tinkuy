#!/bin/bash
# No set -e — exec apache2-foreground MUST always be reached

LOG="/home/site/startup.log"
echo "=== startup.sh: $(date) ===" >> "$LOG" 2>/dev/null
echo "User: $(whoami)" >> "$LOG" 2>/dev/null

VHOST='<VirtualHost *:80>
    DocumentRoot /home/site/wwwroot/public
    <Directory /home/site/wwwroot/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>'

for CONF in \
    "/etc/apache2/sites-available/000-default.conf" \
    "/etc/apache2/sites-enabled/000-default.conf"; do
    echo "$VHOST" > "$CONF" 2>/dev/null \
        && echo "Updated: $CONF" >> "$LOG" 2>/dev/null \
        || echo "Failed:  $CONF" >> "$LOG" 2>/dev/null
done

sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf 2>/dev/null || true

a2ensite 000-default 2>/dev/null || true
a2enmod rewrite   2>/dev/null || true
a2enmod headers   2>/dev/null || true

echo "=== Launching apache2-foreground ===" >> "$LOG" 2>/dev/null
exec apache2-foreground
