@echo off
cd /d c:\Users\Agungleo
git add capstone/versikopi-web/vercel.json capstone/PUSH_GITHUB.bat
git commit -m "Proxy /api di vercel.json ke tunnel Laravel untuk uji coba"
git push origin main
