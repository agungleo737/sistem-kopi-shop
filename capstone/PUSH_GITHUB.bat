@echo off
cd /d c:\Users\Agungleo
copy /Y c:\Users\Agungleo\capstone\README.md c:\Users\Agungleo\README.md
git add README.md capstone
git commit -m "Hapus hint akun demo, tambah laporan rekap & menu terlaris, naikkan limit order, dan tambah vercel.json"
git push origin main
