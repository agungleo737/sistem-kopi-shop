@echo off
cd /d c:\Users\Agungleo
copy /Y c:\Users\Agungleo\capstone\README.md c:\Users\Agungleo\README.md
git add README.md capstone
git commit -m "Fix opsi gula dan ukuran M/L makanan serta layout responsif Desktop, iPad, dan HP"
git push origin main
