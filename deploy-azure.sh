#!/bin/bash
# deploy-azure.sh — Despliegue Tinkuy en Azure App Service
# MySQL externo (TiDB Serverless / PlanetScale / cualquier MySQL 8.0 compatible)
# Ejecutar en Azure Cloud Shell (ya autenticado)
set -e

# ─── CONFIGURACIÓN AZURE ──────────────────────────────────────────────────────
RG="rg-tinkuy"
LOCATION="eastus"
APP_PLAN="plan-tinkuy"
APP_NAME="app-tinkuy"       # cambia si el nombre ya está tomado en Azure
RUNTIME="PHP|8.2"

# ─── CREDENCIALES MYSQL EXTERNO ───────────────────────────────────────────────
# Rellena con los datos de tu servicio MySQL (TiDB / PlanetScale / otro)
DB_HOST="<host-de-tu-mysql>"       # ej: gateway01.us-east-1.prod.aws.tidbcloud.com
DB_PORT="4000"                      # TiDB usa 4000; MySQL estándar usa 3306
DB_USER="<usuario>"
DB_PASSWORD="<contraseña>"
DB_NAME="tinkuy_db"

# ─── VARIABLES DE CORREO SMTP ─────────────────────────────────────────────────
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
echo "  (MySQL externo)"
echo "══════════════════════════════════════════"
echo ""

# Validar que se rellenaron las credenciales
if [[ "$DB_HOST" == "<host-de-tu-mysql>" ]]; then
  echo "❌ ERROR: Edita el script y rellena DB_HOST, DB_USER y DB_PASSWORD"
  echo "   con los datos de tu MySQL externo (TiDB / PlanetScale)."
  exit 1
fi

# 1. Resource Group
echo "► [1/4] Creando Resource Group '$RG' en $LOCATION ..."
az group create --name "$RG" --location "$LOCATION" --output none
echo "   ✓ Resource Group listo."

# 2. App Service Plan (Linux B1)
echo ""
echo "► [2/4] Creando App Service Plan '$APP_PLAN' (Linux B1) ..."
az appservice plan create \
  --name "$APP_PLAN" \
  --resource-group "$RG" \
  --sku B1 \
  --is-linux \
  --location "$LOCATION" \
  --output none
echo "   ✓ Plan creado."

# 3. Web App PHP 8.2 + startup script
echo ""
echo "► [3/4] Creando Web App '$APP_NAME' con PHP 8.2 ..."
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
echo "   ✓ Web App creada."

# 4. App Settings (variables de entorno)
echo ""
echo "► [4/4] Configurando variables de entorno ..."
az webapp config appsettings set \
  --resource-group "$RG" \
  --name "$APP_NAME" \
  --output none \
  --settings \
    DB_HOST="${DB_HOST}" \
    DB_PORT="${DB_PORT}" \
    DB_USER="${DB_USER}" \
    DB_PASSWORD="${DB_PASSWORD}" \
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
echo "  ✅ INFRAESTRUCTURA LISTA"
echo "══════════════════════════════════════════"
echo ""
echo "  Web App URL : https://${APP_NAME}.azurewebsites.net"
echo "  MySQL Host  : ${DB_HOST}"
echo ""
echo "─── PRÓXIMO PASO: Configura GitHub Actions ───"
echo ""
echo "Copia el XML de abajo y agrégalo en GitHub como secret"
echo "'AZURE_WEBAPP_PUBLISH_PROFILE' (Settings → Secrets → Actions):"
echo ""
az webapp deployment list-publishing-profiles \
  --resource-group "$RG" \
  --name "$APP_NAME" \
  --xml
echo ""
echo "Luego haz merge de 'claude/tinkuy-azure-deployment-aapw0q' a 'main'"
echo "y GitHub Actions desplegará automáticamente."
