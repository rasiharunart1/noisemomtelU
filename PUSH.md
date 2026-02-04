# Quick Push Guide

## Manual Push (Run in Git Bash or Terminal)

```bash
cd "c:\Users\ACER\Documents\PROYEK\noisemon telu\noisemomtelU"

# Stage all changes
git add .

# Review what will be committed
git status

# Commit with descriptive message
git commit -m "feat: add scheduled recordings, auto-archive logs with bulk download, configurable offline threshold, and supervisor setup

- Add scheduled recording feature with interval-based triggering
- Implement auto-archive logs with configurable time and CSV export
- Add bulk download for archived logs (ZIP format)
- Make device offline threshold configurable via Settings
- Add supervisor setup script for production deployment
- Add comprehensive deployment documentation
- Fix migration bootstrap error with try-catch in console.php"

# Push to GitHub
git push
```

## After Push - Deploy to Production

SSH to server and run:

```bash
cd /www/wwwroot/noisemonlab/noisemomtelU
git pull origin main
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
chmod -R 775 storage bootstrap/cache
sudo bash setup-supervisor.sh  # First time only
supervisorctl restart all
```

Done! 🚀
