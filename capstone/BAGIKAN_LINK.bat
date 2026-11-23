@echo off
title Versi Kopi - Bagikan Link Publik (Beda Jaringan / Internet)
color 0A
echo =====================================================================
echo   VERSI KOPI - AKSES WEB DARI BEDA JARINGAN (WIFI / PAKET DATA HP)
echo =====================================================================
echo.
echo [1/4] Build versi ringan (production) agar cepat dibuka lewat internet...
cd /d "%~dp0versikopi-web"
call npm run build

echo.
echo [2/4] Menjalankan Server Backend Laravel (Port 8000)...
start "Versi Kopi API (Jangan Ditutup)" cmd /k "cd /d %~dp0versikopi-api && php artisan serve --host=127.0.0.1 --port=8000"

echo [3/4] Menjalankan Server Frontend Web Production (Port 4173)...
start "Versi Kopi Web (Jangan Ditutup)" cmd /k "cd /d %~dp0versikopi-web && npm run preview -- --host --port 4173"

timeout /t 3 /nobreak >nul

echo.
echo [4/4] Membuat Link Publik Internet (Bisa dibuka dari jaringan mana saja)...
echo ---------------------------------------------------------------------
echo   Copy link https://xxxx.lhr.life yang muncul di bawah ini
echo   lalu kirim ke teman Anda (bisa login Admin maupun Kasir):
echo ---------------------------------------------------------------------
echo.
ssh -o StrictHostKeyChecking=no -R 80:localhost:4173 nokey@localhost.run
pause
