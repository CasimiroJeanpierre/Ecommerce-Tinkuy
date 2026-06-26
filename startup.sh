#!/bin/bash

LOG="/home/site/startup.log"
echo "=== startup.sh: $(date) ===" >> "$LOG" 2>/dev/null
echo "User: $(whoami)" >> "$LOG" 2>/dev/null

NGINX_CONF="/etc/nginx/sites-enabled/default"

if [ ! -f "$NGINX_CONF" ]; then
    echo "ERROR: $NGINX_CONF not found" >> "$LOG" 2>/dev/null
    exit 0
fi

# Write a complete, known-good nginx config instead of patching with sed
cat > "$NGINX_CONF" << 'NGINX_EOF'
server {
    listen 8080;
    listen [::]:8080;
    root /home/site/wwwroot/public;
    index index.php index.html index.htm;

    error_page 500 502 503 504 /50x.html;
    location = /50x.html {
        root /html/;
    }

    # Serve /public/* URLs (used by views that build paths like $project_root/public/img/...)
    location /public/ {
        alias /home/site/wwwroot/public/;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://www.google.com https://www.gstatic.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: https:; font-src 'self' https://cdn.jsdelivr.net; frame-src https://www.google.com; connect-src 'self';" always;

    location ~ \.php$ {
        fastcgi_split_path_info ^(.+?\.php)(|/.*)$;
        fastcgi_pass 127.0.0.1:9000;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
NGINX_EOF

echo "Wrote new nginx config" >> "$LOG" 2>/dev/null
grep -E "root|try_files|alias|listen" "$NGINX_CONF" >> "$LOG" 2>/dev/null

# Validate config before reloading
nginx -t >> "$LOG" 2>&1
if [ $? -eq 0 ]; then
    nginx -s reload >> "$LOG" 2>/dev/null \
        && echo "nginx reloaded OK" >> "$LOG" 2>/dev/null \
        || echo "nginx not running yet (will start with updated config)" >> "$LOG" 2>/dev/null
else
    echo "ERROR: nginx config invalid, keeping original" >> "$LOG" 2>/dev/null
fi
