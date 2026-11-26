@echo off
cd /d c:\Users\Agungleo
git add -f capstone/versikopi-web/.env.production
git add capstone/versikopi-web/vercel.json capstone/PUSH_GITHUB.bat
git commit -m "Set VITE_API_URL production sementara untuk uji coba Vercel"
git push origin main
