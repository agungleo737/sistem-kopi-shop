@echo off
cd /d c:\Users\Agungleo
copy /Y c:\Users\Agungleo\capstone\README.md c:\Users\Agungleo\README.md
git add README.md capstone
git commit -m "Update Versi Kopi POS: Kelola Akun, Void Order, Export CSV, Lupa Password, Hapus Menu/Bahan, dan Perbaikan Keamanan"
git push origin main
