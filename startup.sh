#!/bin/bash
set -e

# Overwrite Apache config to serve from public/ with AllowOverride All
cat > /etc/apache2/sites-available/000-default.conf << 'EOF'
<VirtualHost *:80>
    DocumentRoot /home/site/wwwroot/public

    <Directory /home/site/wwwroot/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

# Enable the site and required modules
a2ensite 000-default 2>/dev/null || true
a2enmod rewrite 2>/dev/null || true
a2enmod headers 2>/dev/null || true

apache2-foreground
