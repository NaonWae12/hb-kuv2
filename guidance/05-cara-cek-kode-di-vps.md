# Cara Cek & Bandingkan Kode di VPS

Panduan untuk melihat dan membandingkan kode di VPS dengan kode lokal.

## Metode 1: Via SSH (Langsung di Terminal)

### Melihat File di VPS

```bash
# Login ke VPS
ssh -i ~/.ssh/hbku_vps root@202.10.47.245

# Masuk ke folder project
cd /var/www/my_project

# Lihat isi file (contoh: routes/web.php)
cat routes/web.php

# Atau dengan pagination (untuk file besar)
less routes/web.php
# Tekan 'q' untuk keluar dari less

# Lihat beberapa baris pertama
head -n 50 routes/web.php

# Lihat beberapa baris terakhir
tail -n 50 routes/web.php

# Cari teks tertentu dalam file
grep -n "forms.export" routes/web.php
```

### Melihat File View

```bash
# Lihat responses.blade.php
cat resources/views/forms/responses.blade.php

# Atau dengan less untuk file besar
less resources/views/forms/responses.blade.php

# Cari route yang digunakan
grep -n "route.*export" resources/views/forms/responses.blade.php
```

## Metode 2: Download File dari VPS ke Lokal

### Download File ke Lokal

```bash
# Download routes/web.php dari VPS ke folder lokal
scp -i ~/.ssh/hbku_vps root@202.10.47.245:/var/www/my_project/routes/web.php ./routes-web-vps.txt

# Download FormController
scp -i ~/.ssh/hbku_vps root@202.10.47.245:/var/www/my_project/app/Http/Controllers/FormController.php ./FormController-vps.php

# Download responses.blade.php
scp -i ~/.ssh/hbku_vps root@202.10.47.245:/var/www/my_project/resources/views/forms/responses.blade.php ./responses-vps.blade.php

# Download create.blade.php
scp -i ~/.ssh/hbku_vps root@202.10.47.245:/var/www/my_project/resources/views/forms/create.blade.php ./create-vps.blade.php
```

### Bandingkan dengan File Lokal

Setelah download, buka file di editor dan bandingkan dengan file lokal.

## Metode 3: Via VS Code Remote (Jika Terinstall)

Jika kamu punya VS Code dengan extension Remote-SSH:

1. Install extension "Remote - SSH"
2. Connect ke VPS: `ssh -i ~/.ssh/hbku_vps root@202.10.47.245`
3. Buka folder: `/var/www/my_project`
4. Bisa langsung edit dan lihat file seperti di lokal

## Metode 4: Script untuk Download Multiple Files

Buat script untuk download beberapa file sekaligus:

```bash
#!/bin/bash
# save-ke: download-vps-files.sh

VPS_USER="root"
VPS_HOST="202.10.47.245"
VPS_PATH="/var/www/my_project"
SSH_KEY="~/.ssh/hbku_vps"
LOCAL_DIR="./vps-files-backup"

# Buat folder backup
mkdir -p "$LOCAL_DIR"

# Download file-file penting
scp -i $SSH_KEY $VPS_USER@$VPS_HOST:$VPS_PATH/routes/web.php "$LOCAL_DIR/routes-web.php"
scp -i $SSH_KEY $VPS_USER@$VPS_HOST:$VPS_PATH/app/Http/Controllers/FormController.php "$LOCAL_DIR/FormController.php"
scp -i $SSH_KEY $VPS_USER@$VPS_HOST:$VPS_PATH/resources/views/forms/responses.blade.php "$LOCAL_DIR/responses.blade.php"
scp -i $VPS_PATH/resources/views/forms/create.blade.php "$LOCAL_DIR/create.blade.php"

echo "Files downloaded to $LOCAL_DIR"
```

## File-File Penting yang Perlu Dicek

Berdasarkan error yang terjadi, cek file-file berikut:

### 1. Routes
```bash
# Di VPS
cat /var/www/my_project/routes/web.php

# Cek apakah ada route forms.export (seharusnya TIDAK ada)
grep "forms.export" /var/www/my_project/routes/web.php

# Seharusnya ada route ini:
grep "forms.responses.export" /var/www/my_project/routes/web.php
```

### 2. View Files
```bash
# Cek responses.blade.php
cat /var/www/my_project/resources/views/forms/responses.blade.php | grep -n "route.*export"

# Cek create.blade.php
cat /var/www/my_project/resources/views/forms/create.blade.php | grep -n "route.*export"
```

### 3. FormController
```bash
# Cek apakah method exportSummary dan exportIndividual ada
grep -n "function export" /var/www/my_project/app/Http/Controllers/FormController.php
```

## Quick Check Commands

Jalankan command berikut untuk quick check:

```bash
# Login ke VPS
ssh -i ~/.ssh/hbku_vps root@202.10.47.245

# Masuk ke project
cd /var/www/my_project

# 1. Cek route yang ada
php artisan route:list | grep export

# 2. Cek apakah ada forms.export di view
grep -r "forms.export" resources/views/

# 3. Cek compiled views (mungkin masih pakai route lama)
grep -r "forms.export" storage/framework/views/ 2>/dev/null || echo "No compiled views found"

# 4. Cek FormController methods
grep -n "public function export" app/Http/Controllers/FormController.php
```

## Troubleshooting

### Jika Masih Error Route Not Found

1. **Clear semua cache:**
   ```bash
   php artisan optimize:clear
   php artisan view:clear
   rm -rf storage/framework/views/*
   ```

2. **Rebuild cache:**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

3. **Restart PHP-FPM:**
   ```bash
   systemctl restart php8.3-fpm
   ```

### Jika File di VPS Berbeda dengan Lokal

1. **Upload file dari lokal ke VPS:**
   ```bash
   # Dari lokal (Windows dengan WSL)
   scp -i ~/.ssh/hbku_vps routes/web.php root@202.10.47.245:/var/www/my_project/routes/web.php
   ```

2. **Set permissions:**
   ```bash
   # Di VPS
   chown -R www-data:www-data /var/www/my_project
   ```

3. **Clear cache dan restart:**
   ```bash
   php artisan optimize:clear
   php artisan view:cache
   systemctl restart php8.3-fpm
   ```

---

**Last Updated**: 2026-01-05


