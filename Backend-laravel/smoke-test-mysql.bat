@echo off
REM Smoke test contra MySQL local (XAMPP en Windows).
REM
REM Uso desde Backend-laravel:
REM   .\smoke-test-mysql.bat
REM
REM Asegurate de tener XAMPP corriendo con MySQL activo.

setlocal

set DB_NAME=erp_contable_smoke
set DB_USER=root
set DB_PASS=

echo ==================================================
echo   SMOKE TEST: Validacion contra MySQL local
echo ==================================================
echo.

REM Path tipico de mysql.exe en XAMPP
set MYSQL_EXE=C:\xampp\mysql\bin\mysql.exe
set MYSQLADMIN_EXE=C:\xampp\mysql\bin\mysqladmin.exe

if not exist "%MYSQL_EXE%" (
    echo ERROR: No se encuentra mysql.exe en %MYSQL_EXE%
    echo Verifica la ruta de instalacion de XAMPP.
    exit /b 1
)

echo [1/5] Verificando que MySQL este corriendo...
"%MYSQLADMIN_EXE%" -h 127.0.0.1 -u %DB_USER% ping >nul 2>&1
if errorlevel 1 (
    echo ERROR: MySQL no responde. Encende XAMPP MySQL.
    exit /b 1
)
echo       MySQL OK
echo.

echo [2/5] Creando BD de prueba '%DB_NAME%'...
"%MYSQL_EXE%" -h 127.0.0.1 -u %DB_USER% -e "DROP DATABASE IF EXISTS %DB_NAME%; CREATE DATABASE %DB_NAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if errorlevel 1 (
    echo ERROR creando BD
    exit /b 1
)
echo       BD creada con utf8mb4_unicode_ci
echo.

echo [3/5] Aplicando migraciones contra MySQL...
set DB_CONNECTION=mysql
set DB_HOST=127.0.0.1
set DB_PORT=3306
set DB_DATABASE=%DB_NAME%
set DB_USERNAME=%DB_USER%
set DB_PASSWORD=%DB_PASS%
php artisan migrate --force
if errorlevel 1 (
    echo ERROR en migraciones
    exit /b 1
)
echo.

echo [4/5] Corriendo suite completa de tests contra MySQL...
php artisan test
if errorlevel 1 (
    echo ERROR en tests
    exit /b 1
)
echo.

echo [5/5] Limpiando BD de prueba...
"%MYSQL_EXE%" -h 127.0.0.1 -u %DB_USER% -e "DROP DATABASE %DB_NAME%;"
echo       OK
echo.

echo ==================================================
echo   SMOKE TEST COMPLETADO. Sistema listo para deploy.
echo ==================================================

endlocal
