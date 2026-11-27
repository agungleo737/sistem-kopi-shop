@echo off
cd /d c:\Users\Agungleo
git add -f capstone/versikopi-web/.env.production
git add capstone/PUSH_GITHUB.bat
git commit -m "Update tunnel API URL untuk uji coba Vercel"
git push origin main
