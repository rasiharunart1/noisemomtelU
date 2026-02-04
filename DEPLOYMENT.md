# Deployment Guide - Noisemon Lab

## 🚀 Deployment ke Production Server

### 1. Pull Update dari GitHub
```bash
cd /www/wwwroot/noisemonlab/noisemomtelU
git pull origin main
```

### 2. Install Dependencies
```bash
composer install --no-dev --optimize-autoloader
npm install && npm run build
```

### 3. Clear Cache & Run Migrations
```bash
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
```

### 4. Fix Permissions
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 5. Restart Services
```bash
supervisorctl restart all
```

---

## 🔧 Setup Background Services (First Time)

### Setup Supervisor (MQTT Listener & Scheduler)

**Copy script ke server:**
```bash
scp setup-supervisor.sh root@your-server:/www/wwwroot/noisemonlab/noisemomtelU/
```

**Di server, jalankan:**
```bash
cd /www/wwwroot/noisemonlab/noisemomtelU
chmod +x setup-supervisor.sh
sudo bash setup-supervisor.sh
```

Script akan otomatis:
- ✅ Install Supervisor (jika belum)
- ✅ Setup MQTT Listener sebagai background service
- ✅ Setup Laravel Scheduler
- ✅ Start semua services

---

## 📊 Monitoring & Management

### Cek Status Services
```bash
supervisorctl status
```

### Restart Services
```bash
# Restart MQTT Listener
supervisorctl restart laravel-mqtt-listener

# Restart Scheduler
supervisorctl restart laravel-scheduler

# Restart semua
supervisorctl restart all
```

### View Logs Real-Time
```bash
# MQTT Listener logs
tail -f storage/logs/mqtt-listener.log

# Scheduler logs
tail -f storage/logs/scheduler.log

# Laravel application logs
tail -f storage/logs/laravel.log
```

### Stop Services
```bash
supervisorctl stop laravel-mqtt-listener
supervisorctl stop laravel-scheduler
```

---

## 🐛 Troubleshooting

### MQTT Listener tidak connect
```bash
# Cek log
tail -n 100 storage/logs/mqtt-listener.log

# Cek MQTT settings di database
php artisan tinker --execute="dump(App\Models\Setting::pluck('value', 'key'));"

# Restart service
supervisorctl restart laravel-mqtt-listener
```

### Scheduler tidak jalan
```bash
# Test manual
php artisan schedule:run

# Cek log
tail -n 50 storage/logs/scheduler.log

# Restart
supervisorctl restart laravel-scheduler
```

### Migration Error
```bash
# Fake migration yang duplicate
php artisan migrate --fake --path=database/migrations/FILENAME.php

# Skip dan lanjut ke next
php artisan migrate --force
```

---

## 📝 Quick Deployment Checklist

- [ ] `git pull origin main`
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `npm install && npm run build`
- [ ] `php artisan optimize:clear`
- [ ] `php artisan migrate --force`
- [ ] `php artisan optimize`
- [ ] `chmod -R 775 storage bootstrap/cache`
- [ ] `supervisorctl restart all`
- [ ] ✅ Test website
- [ ] ✅ Cek `supervisorctl status`

---

## 🔐 Environment Variables

Edit `.env` di server untuk production:
```bash
APP_ENV=production
APP_DEBUG=false
APP_URL=https://diklat.mdpower.io

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_DATABASE=noisemon
DB_USERNAME=postgres
DB_PASSWORD=your_password

MQTT_HOST=your-broker.hivemq.cloud
MQTT_PORT=8883
MQTT_USERNAME=your_username
MQTT_PASSWORD=your_password
```

**Setelah edit `.env`, selalu jalankan:**
```bash
php artisan config:cache
```
