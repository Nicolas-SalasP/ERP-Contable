#!/usr/bin/env bash
#
# Smoke test contra MySQL local (XAMPP).
#
# Este script valida que el sistema corra correctamente contra MySQL
# antes de pushear a `main` y desplegar a produccion.
#
# Uso:
#   1. Asegurate de tener XAMPP corriendo con MySQL en puerto 3306
#   2. Crea una BD de prueba: erp_contable_smoke
#   3. Ejecuta este script desde Backend-laravel/
#
# Lo que hace:
#   - Aplica todas las migraciones contra MySQL real
#   - Corre la suite completa contra MySQL
#   - Limpia la BD al terminar
#

set -e

DB_NAME="erp_contable_smoke"
DB_USER="root"
DB_PASS=""  # Por defecto en XAMPP root no tiene password

echo "=================================================="
echo "  SMOKE TEST: Validacion contra MySQL local"
echo "=================================================="
echo ""

# Verificar que MySQL este vivo
echo "[1/5] Verificando que MySQL este corriendo..."
if ! mysqladmin -h 127.0.0.1 -P 3306 -u "$DB_USER" ${DB_PASS:+-p$DB_PASS} ping >/dev/null 2>&1; then
    echo "ERROR: MySQL no responde en 127.0.0.1:3306"
    echo "       Asegurate de tener XAMPP encendido con MySQL activo."
    exit 1
fi
echo "      MySQL OK"

# Crear BD de prueba (drop si ya existia)
echo ""
echo "[2/5] Creando BD de prueba '$DB_NAME'..."
mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" ${DB_PASS:+-p$DB_PASS} -e "
DROP DATABASE IF EXISTS $DB_NAME;
CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
"
echo "      BD creada con utf8mb4_unicode_ci"

# Correr migraciones
echo ""
echo "[3/5] Aplicando migraciones contra MySQL..."
DB_CONNECTION=mysql \
DB_HOST=127.0.0.1 \
DB_PORT=3306 \
DB_DATABASE="$DB_NAME" \
DB_USERNAME="$DB_USER" \
DB_PASSWORD="$DB_PASS" \
php artisan migrate --force

# Correr suite de tests
echo ""
echo "[4/5] Corriendo suite completa de tests contra MySQL..."
DB_CONNECTION=mysql \
DB_HOST=127.0.0.1 \
DB_PORT=3306 \
DB_DATABASE="$DB_NAME" \
DB_USERNAME="$DB_USER" \
DB_PASSWORD="$DB_PASS" \
php artisan test

# Limpiar
echo ""
echo "[5/5] Limpiando BD de prueba..."
mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" ${DB_PASS:+-p$DB_PASS} -e "DROP DATABASE $DB_NAME;"
echo "      OK"

echo ""
echo "=================================================="
echo "  SMOKE TEST COMPLETADO. Sistema listo para deploy."
echo "=================================================="
