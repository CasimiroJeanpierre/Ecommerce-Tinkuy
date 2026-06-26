#!/bin/bash
# deploy-azure.sh — Script completo de despliegue Tinkuy en Azure
# Pegar y ejecutar en Azure Cloud Shell (ya autenticado)
set -e

# ─── CONFIGURACIÓN ────────────────────────────────────────────────────────────
RG="rg-tinkuy"
LOCATION="eastus"
MYSQL_SERVER="mysql-tinkuy"
MYSQL_ADMIN="tinkuy_admin"
MYSQL_PASS="TinkuyDB@2025!"       # <-- cambia si quieres otra contraseña
DB_NAME="tinkuy_db"
APP_PLAN="plan-tinkuy"
APP_NAME="app-tinkuy"             # <-- cambia si el nombre ya está tomado
RUNTIME="PHP|8.2"

# Variables de correo (edita con tus datos SMTP reales)
MAIL_HOST="smtp.tuproveedor.com"
MAIL_PORT="587"
MAIL_USER="correo@tudominio.com"
MAIL_PASS="tu_password_smtp"
MAIL_FROM="noreply@tudominio.com"
MAIL_FROM_NAME="Tinkuy"
# ─────────────────────────────────────────────────────────────────────────────

echo ""
echo "══════════════════════════════════════════"
echo "  DESPLIEGUE TINKUY EN AZURE"
echo "══════════════════════════════════════════"
echo ""

# 1. Resource Group
echo "► [1/7] Creando Resource Group '$RG' en $LOCATION ..."
az group create --name "$RG" --location "$LOCATION" --output none
echo "   ✓ Resource Group creado."

# 2. MySQL Flexible Server
echo ""
echo "► [2/7] Creando MySQL Flexible Server '$MYSQL_SERVER' (esto tarda ~3 min) ..."
az mysql flexible-server create \
  --resource-group "$RG" \
  --name "$MYSQL_SERVER" \
  --location "$LOCATION" \
  --admin-user "$MYSQL_ADMIN" \
  --admin-password "$MYSQL_PASS" \
  --sku-name Standard_B1ms \
  --tier Burstable \
  --storage-size 20 \
  --version 8.0 \
  --public-access 0.0.0.0 \
  --output none
echo "   ✓ Servidor MySQL creado."

# 3. Base de datos
echo ""
echo "► [3/7] Creando base de datos '$DB_NAME' ..."
az mysql flexible-server db create \
  --resource-group "$RG" \
  --server-name "$MYSQL_SERVER" \
  --database-name "$DB_NAME" \
  --output none

# Regla firewall: Azure Services
az mysql flexible-server firewall-rule create \
  --resource-group "$RG" \
  --name "$MYSQL_SERVER" \
  --rule-name "AllowAzureServices" \
  --start-ip-address 0.0.0.0 \
  --end-ip-address 0.0.0.0 \
  --output none

# Regla firewall: IP actual (Cloud Shell)
MY_IP=$(curl -s https://ipv4.icanhazip.com)
az mysql flexible-server firewall-rule create \
  --resource-group "$RG" \
  --name "$MYSQL_SERVER" \
  --rule-name "AllowCloudShellIP" \
  --start-ip-address "$MY_IP" \
  --end-ip-address "$MY_IP" \
  --output none
echo "   ✓ BD y firewall configurados (IP: $MY_IP)."

# 4. Parámetros MySQL
echo ""
echo "► [4/7] Ajustando parámetros de MySQL ..."
az mysql flexible-server parameter set \
  --resource-group "$RG" --server-name "$MYSQL_SERVER" \
  --name require_secure_transport --value OFF --output none

az mysql flexible-server parameter set \
  --resource-group "$RG" --server-name "$MYSQL_SERVER" \
  --name event_scheduler --value ON --output none
echo "   ✓ SSL deshabilitado, event_scheduler activado."

# 5. Importar schema SQL
echo ""
echo "► [5/7] Importando schema tinkuy_db.sql ..."
echo "   Descargando el archivo SQL desde GitHub ..."
curl -s -o /tmp/tinkuy_db.sql \
  "https://raw.githubusercontent.com/CasimiroJeanpierre/Ecommerce-Tinkuy/main/tinkuy_db.sql"

if [ ! -s /tmp/tinkuy_db.sql ]; then
  echo "   ⚠ No se pudo descargar desde main. Intenta importar manualmente:"
  echo "   mysql -h ${MYSQL_SERVER}.mysql.database.azure.com -u ${MYSQL_ADMIN} -p${MYSQL_PASS} --ssl-mode=DISABLED ${DB_NAME} < tinkuy_db.sql"
else
  mysql \
    -h "${MYSQL_SERVER}.mysql.database.azure.com" \
    -u "${MYSQL_ADMIN}" \
    -p"${MYSQL_PASS}" \
    --ssl-mode=DISABLED \
    "$DB_NAME" < /tmp/tinkuy_db.sql
  echo "   ✓ Schema importado correctamente."
fi

# 6. App Service Plan + Web App
echo ""
echo "► [6/7] Creando App Service Plan y Web App ..."
az appservice plan create \
  --name "$APP_PLAN" \
  --resource-group "$RG" \
  --sku B1 \
  --is-linux \
  --location "$LOCATION" \
  --output none

az webapp create \
  --resource-group "$RG" \
  --plan "$APP_PLAN" \
  --name "$APP_NAME" \
  --runtime "$RUNTIME" \
  --output none

az webapp config set \
  --resource-group "$RG" \
  --name "$APP_NAME" \
  --startup-file "startup.sh" \
  --output none

echo "   ✓ Web App '$APP_NAME' creada."

# 7. App Settings (variables de entorno)
echo ""
echo "► [7/7] Configurando variables de entorno en la Web App ..."
az webapp config appsettings set \
  --resource-group "$RG" \
  --name "$APP_NAME" \
  --output none \
  --settings \
    DB_HOST="${MYSQL_SERVER}.mysql.database.azure.com" \
    DB_PORT="3306" \
    DB_USER="${MYSQL_ADMIN}" \
    DB_PASSWORD="${MYSQL_PASS}" \
    DB_NAME="${DB_NAME}" \
    MAIL_HOST="${MAIL_HOST}" \
    MAIL_PORT="${MAIL_PORT}" \
    MAIL_USER="${MAIL_USER}" \
    MAIL_PASS="${MAIL_PASS}" \
    MAIL_FROM="${MAIL_FROM}" \
    MAIL_FROM_NAME="${MAIL_FROM_NAME}"

echo "   ✓ Variables de entorno configuradas."

# ─── RESULTADO FINAL ──────────────────────────────────────────────────────────
echo ""
echo "══════════════════════════════════════════"
echo "  ✅ DESPLIEGUE COMPLETADO"
echo "══════════════════════════════════════════"
echo ""
echo "  Web App URL: https://${APP_NAME}.azurewebsites.net"
echo "  MySQL Host:  ${MYSQL_SERVER}.mysql.database.azure.com"
echo ""
echo "─── PRÓXIMO PASO: GitHub Actions CI/CD ───"
echo ""
echo "Copia el siguiente Publish Profile y agrégalo como secret"
echo "'AZURE_WEBAPP_PUBLISH_PROFILE' en GitHub > Settings > Secrets:"
echo ""
az webapp deployment list-publishing-profiles \
  --resource-group "$RG" \
  --name "$APP_NAME" \
  --xml
echo ""
echo "Luego haz merge de la rama al main y el CI/CD se activa solo."
