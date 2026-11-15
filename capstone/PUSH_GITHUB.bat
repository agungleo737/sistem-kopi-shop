@echo off
cd /d c:\Users\Agungleo
copy /Y c:\Users\Agungleo\capstone\README.md c:\Users\Agungleo\README.md
git add README.md capstone
git commit -m "Add README.md untuk dokumentasi project Versi Kopi POS"
git push origin main
