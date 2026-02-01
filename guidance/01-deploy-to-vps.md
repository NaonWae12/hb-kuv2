# Panduan Deploy ke VPS

Panduan lengkap untuk mengupload dan deploy project **Hb-ku** ke VPS untuk pertama kali.

## Prerequisites

Sebelum memulai, pastikan:

- ✅ VPS sudah aktif dan bisa diakses via SSH
- ✅ IP VPS: `202.10.47.245` (atau IP VPS kamu)
- ✅ Username SSH: `root` (atau user lain yang punya akses sudo)
- ✅ Repository GitHub sudah siap: `https://github.com/NaonWae12/hb-kuv2.git`
- ✅ Branch yang akan di-deploy: `on-progress` (atau branch lain)

## Step 1: Setup SSH Key (Opsional tapi Recommended)

Untuk akses VPS tanpa password, setup SSH key:

### Di Local Machine (Windows dengan WSL)

```bash
# Generate SSH key
ssh-keygen -t ed25519 -f ~/.ssh/hbku_vps -N "" -C "hbku-deploy"

# Tampilkan public key
cat ~/.ssh/hbku_vps.pub
```

### Di VPS

Copy public key yang muncul, lalu:

```bash
# Login ke VPS dengan password
ssh root@202.10.47.245

# Setup authorized_keys
mkdir -p ~/.ssh
chmod 700 ~/.ssh
echo 'PASTE_PUBLIC_KEY_DISINI' >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

### Test SSH Key

```bash
# Dari local machine
ssh -i ~/.ssh/hbku_vps root@202.10.47.245
```

Jika berhasil login tanpa password, SSH key sudah aktif.

## Step 2: Backup Project Lama (Jika Ada)

Jika di VPS sudah ada project lama, backup dulu:

```bash
# Login ke VPS
ssh root@202.10.47.245

# Buat backup folder dengan timestamp
TIMESTAMP=$(date +%F_%H%M%S)
BACKUP_DIR="/var/www/my_project_backup_$TIMESTAMP"
OLD_DIR="/var/www/my_project_old_$TIMESTAMP"

# Buat folder backup
mkdir -p "$BACKUP_DIR"

# Backup file penting
if [ -f /var/www/my_project/.env ]; then
    cp /var/www/my_project/.env "$BACKUP_DIR/.env"
fi

if [ -d /var/www/my_project/storage ]; then
    tar -czf "$BACKUP_DIR/storage.tgz" -C /var/www/my_project storage
fi

if [ -d /var/www/my_project/public/storage ]; then
    tar -czf "$BACKUP_DIR/public_storage.tgz" -C /var/www/my_project public/storage
fi

# Pindahkan project lama (jika ada)
if [ -d /var/www/my_project ]; then
    mv /var/www/my_project "$OLD_DIR"
fi
```

## Step 3: Clone Repository

```bash
# Masuk ke /var/www
cd /var/www

# Clone repository
git clone --branch on-progress --single-branch https://github.com/NaonWae12/hb-kuv2.git my_project

# Masuk ke folder project
cd my_project
```

## Step 4: Restore File Konfigurasi

Jika ada backup `.env`, restore:

```bash
# Restore .env dari backup (jika ada)
if [ -f /var/www/my_project_backup_*/\.env ]; then
    cp /var/www/my_project_backup_*/.env .env
else
    # Copy dari .env.example dan edit manual
    cp .env.example .env
    nano .env  # Edit sesuai kebutuhan
fi
```

**Penting**: Edit `.env` dan pastikan:
- `APP_NAME=Hb-ku`
- `APP_URL=https://hb-ku.site` (atau domain kamu)
- Database credentials sesuai VPS
- `APP_KEY` sudah ada (jika belum, jalankan `php artisan key:generate`)

## Step 5: Install Dependencies

### Install PHP Dependencies (Composer)

```bash
# Install composer dependencies (production mode)
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-interaction --no-dev --prefer-dist --optimize-autoloader
```

### Install Node Dependencies & Build Frontend

```bash
# Install npm dependencies
npm install

# Build frontend assets
npm run build
```

## Step 6: Setup Permissions

```bash
# Set ownership ke www-data
chown -R www-data:www-data /var/www/my_project

# Set permissions untuk storage dan cache
mkdir -p storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
```

## Step 7: Setup Laravel

```bash
# Generate APP_KEY jika belum ada
php artisan key:generate --force

# Create storage link
php artisan storage:link

# Run migrations
php artisan migrate --force

# Clear dan rebuild caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Step 8: Konfigurasi Nginx

Pastikan Nginx sudah dikonfigurasi dengan benar:

```bash
# Cek file konfigurasi
cat /etc/nginx/sites-available/hb-ku
```

Pastikan ada:
- `server_name` sesuai domain (atau IP untuk sementara)
- `root /var/www/my_project/public;`
- PHP-FPM socket: `unix:/run/php/php8.3-fpm.sock`

### Test & Reload Nginx

```bash
# Test konfigurasi
nginx -t

# Reload Nginx
systemctl reload nginx
# atau
systemctl restart nginx
```

## Step 9: Restart Services

```bash
# Restart PHP-FPM
systemctl restart php8.3-fpm

# Cek status services
systemctl status nginx
systemctl status php8.3-fpm
```

## Step 10: Verifikasi Deploy

### Test dari VPS

```bash
# Test HTTP response
curl -I http://127.0.0.1/

# Test login page
curl -I http://127.0.0.1/login
```

### Test dari Browser

Buka browser dan akses:
- `http://202.10.47.245` (atau IP VPS kamu)
- `http://202.10.47.245/login`

## Troubleshooting

### Error: Permission Denied

```bash
# Fix permissions
chown -R www-data:www-data /var/www/my_project
chmod -R ug+rwX /var/www/my_project/storage
chmod -R ug+rwX /var/www/my_project/bootstrap/cache
```

### Error: Composer Install Gagal

```bash
# Clear composer cache
composer clear-cache

# Install ulang
composer install --no-interaction --no-dev --prefer-dist --optimize-autoloader
```

### Error: NPM Build Gagal

```bash
# Clear npm cache
npm cache clean --force

# Install ulang
rm -rf node_modules package-lock.json
npm install
npm run build
```

### Error: Database Connection

Cek file `.env`:
- `DB_HOST` harus benar
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` harus sesuai
- Pastikan database sudah dibuat di MySQL

### Cek Log untuk Error

```bash
# Laravel log
tail -f /var/www/my_project/storage/logs/laravel.log

# Nginx error log
tail -f /var/log/nginx/error.log

# PHP-FPM error log
tail -f /var/log/php8.3-fpm.log
```

## Checklist Deploy

- [ ] SSH key sudah setup (opsional)
- [ ] Project lama sudah di-backup
- [ ] Repository sudah di-clone
- [ ] `.env` sudah dikonfigurasi dengan benar
- [ ] Composer dependencies terinstall
- [ ] NPM dependencies terinstall & assets ter-build
- [ ] Permissions sudah benar (www-data)
- [ ] Migrations sudah dijalankan
- [ ] Caches sudah di-rebuild
- [ ] Nginx sudah dikonfigurasi & reload
- [ ] PHP-FPM sudah restart
- [ ] Website bisa diakses dari browser

## Next Steps

Setelah deploy berhasil, lanjut ke:
- [Panduan Setup Domain & SSL](02-domain-and-ssl.md)

---

**Last Updated**: 2026-01-04

