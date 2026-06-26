#!/bin/bash

LOG="/home/site/startup.log"
echo "=== startup.sh: $(date) ===" >> "$LOG" 2>/dev/null
echo "User: $(whoami)" >> "$LOG" 2>/dev/null

NGINX_CONF="/etc/nginx/sites-enabled/default"

if [ -f "$NGINX_CONF" ]; then
    # Change DocumentRoot to public/
    sed -i 's|root /home/site/wwwroot;|root /home/site/wwwroot/public;|g' "$NGINX_CONF"

    # Add /public/ alias so URLs like /public/img/Logo.png are served from public/
    if ! grep -q "location /public/" "$NGINX_CONF"; then
        sed -i 's|location / {|location /public/ {\n        alias /home/site/wwwroot/public/;\n    }\n\n    location / {|' "$NGINX_CONF"
    fi

    # Add try_files for PHP routing (needed since .htaccess doesn't work in nginx)
    if ! grep -q "try_files" "$NGINX_CONF"; then
        sed -i '/location \/ {/a\        try_files $uri $uri/ /index.php?$query_string;' "$NGINX_CONF"
    fi

    echo "Updated nginx config:" >> "$LOG" 2>/dev/null
    grep -E "root|try_files" "$NGINX_CONF" >> "$LOG" 2>/dev/null

    # Reload nginx if already running
    nginx -s reload >> "$LOG" 2>/dev/null \
        && echo "nginx reloaded OK" >> "$LOG" 2>/dev/null \
        || echo "nginx not running yet (will start with updated config)" >> "$LOG" 2>/dev/null
else
    echo "ERROR: $NGINX_CONF not found" >> "$LOG" 2>/dev/null
fi
