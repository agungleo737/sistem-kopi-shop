@echo off
title Versi Kopi - Aktivasi Database MariaDB / MySQL
color 0B
echo =================================================================
echo   VERSI KOPI - PINDAH & SINKRONISASI KE DATABASE MARIADB / MYSQL
echo =================================================================
echo.
echo [1/3] Membuat database "versikopi_db" di MariaDB / MySQL (Port 3306)...
"C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS versikopi_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>nul
if %errorlevel% neq 0 (
    mysql -u root -e "CREATE DATABASE IF NOT EXISTS versikopi_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
)

echo.
echo [2/3] Mengatur file versikopi-api\.env ke MariaDB / MySQL (versikopi_db)...
powershell -NoProfile -Command "$envPath = '%~dp0versikopi-api\.env'; $c = Get-Content $envPath -Raw; $c = $c -replace 'DB_CONNECTION=sqlite', \"DB_CONNECTION=mysql`r`nDB_HOST=127.0.0.1`r`nDB_PORT=3306`r`nDB_DATABASE=versikopi_db`r`nDB_USERNAME=root`r`nDB_PASSWORD=\"; Set-Content $envPath $c -Encoding UTF8"

echo.
echo [3/3] Menjalankan Migrasi Tabel dan Seeder Menu Resmi Versi Kopi...
cd /d "%~dp0versikopi-api"
php artisan config:clear
php artisan migrate --force
php artisan db:seed --force

echo.
echo =================================================================
echo   SELESAI! Database "versikopi_db" sudah aktif di MariaDB/MySQL!
echo   Silakan cek di HeidiSQL / phpMyAdmin.
echo =================================================================
