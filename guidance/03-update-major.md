# Panduan Update Major (Perubahan Besar)

Workflow untuk update perubahan besar: development di lokal → testing → commit & push → deploy ke VPS.

## Kapan Menggunakan Panduan Ini?

Gunakan panduan ini untuk:
- ✅ Perubahan kode (PHP, JavaScript, Blade templates)
- ✅ Perubahan database (migrations baru)
- ✅ Perubahan dependencies (composer.json, package.json)
- ✅ Perubahan struktur file/folder
- ✅ Update Laravel framework atau package besar

**JANGAN** gunakan untuk:
- ❌ Update `.env` (pakai [panduan update minor](04-update-minor-on-vps.md))
- ❌ Update konfigurasi kecil (pakai panduan update minor)

## Prerequisites

- ✅ Repository GitHub sudah terhubung
- ✅ Branch yang akan di-deploy sudah jelas
- ✅ Perubahan sudah di-test di lokal
- ✅ Akses SSH ke VPS

## Step 1: Development & Testing di Lokal

### 1.1 Buat Branch Baru (Opsional)

```bash
# Di local machine
git checkout -b feature/nama-fitur
# atau
git checkout -b fix/nama-bug
```

### 1.2 Development

Lakukan perubahan sesuai kebutuhan:
- Edit kode
- Tambah migration jika perlu
- Update dependencies jika perlu

### 1.3 Testing di Lokal

```bash
# Run migrations
php artisan migrate

# Clear caches
php artisan optimize:clear

# Test aplikasi
# - Buka browser, test fitur yang diubah
# - Cek tidak ada error di console/log
```

### 1.4 Commit & Push

```bash
# Stage perubahan
git add .

# Commit dengan message yang jelas
git commit -m "Add: fitur export Word dengan styling border"

# Push ke GitHub
git push origin feature/nama-fitur
# atau push ke branch utama
git push origin on-progress
```

## Step 2: Deploy ke VPS

### 2.1 Backup Current Deploy (Opsional tapi Recommended)

```bash
# Login ke VPS
ssh root@202.10.47.245

# Buat backup cepat
cd /var/www
TIMESTAMP=$(date +%F_%H%M%S)
cp -r my_project "my_project_backup_$TIMESTAMP"
```

### 2.2 Pull Latest Code

```bash
# Masuk ke folder project
cd /var/www/my_project

# Pull latest code dari GitHub
git pull origin on-progress
# atau branch lain
git pull origin feature/nama-fitur
```

### 2.3 Install Dependencies

#### PHP Dependencies (Composer)

```bash
# Install/update composer dependencies
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-interaction --no-dev --prefer-dist --optimize-autoloader
```

#### Node Dependencies & Build Frontend

```bash
# Install npm dependencies
npm install

# Build frontend assets
npm run build
```

### 2.4 Run Migrations

```bash
# Run migrations (jika ada migration baru)
php artisan migrate --force
```

**Penting**: `--force` diperlukan di production untuk skip konfirmasi.

### 2.5 Rebuild Caches

```bash
# Clear semua cache
php artisan optimize:clear

# Rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 2.6 Fix Permissions

```bash
# Pastikan permissions benar
chown -R www-data:www-data /var/www/my_project
chmod -R ug+rwX /var/www/my_project/storage
chmod -R ug+rwX /var/www/my_project/bootstrap/cache
```

### 2.7 Reload Services

```bash
# Reload Nginx
nginx -t && systemctl reload nginx

# Restart PHP-FPM
systemctl restart php8.3-fpm
```

## Step 3: Verifikasi Deploy

### 3.1 Smoke Test

```bash
# Test HTTP response
curl -I https://hb-ku.site

# Test login page
curl -I https://hb-ku.site/login
```

### 3.2 Cek Log

```bash
# Cek Laravel log untuk error
tail -n 50 /var/www/my_project/storage/logs/laravel.log

# Cek Nginx error log
tail -n 20 /var/log/nginx/error.log
```

### 3.3 Test Manual di Browser

1. Buka `https://hb-ku.site`
2. Test fitur yang diubah
3. Cek tidak ada error di browser console
4. Cek tidak ada error di Laravel log

## Rollback Strategy (Jika Ada Masalah)

### Quick Rollback ke Backup

```bash
# Stop services sementara (opsional)
systemctl stop nginx

# Restore dari backup
cd /var/www
rm -rf my_project
mv my_project_backup_TIMESTAMP my_project

# Restart services
systemctl start nginx
systemctl restart php8.3-fpm
```

### Rollback via Git

```bash
# Masuk ke folder project
cd /var/www/my_project

# Cek commit history
git log --oneline -10

# Rollback ke commit sebelumnya
git reset --hard COMMIT_HASH_SEBELUMNYA

# Rebuild caches
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Reload services
systemctl reload nginx
systemctl restart php8.3-fpm
```

## Best Practices

### 1. Test Sebelum Deploy

**Selalu** test di lokal dulu sebelum push ke production:
- Test semua fitur yang diubah
- Test edge cases
- Cek tidak ada breaking changes

### 2. Commit Message yang Jelas

```bash
# Good
git commit -m "Add: export Word dengan styling border dan Cambria font"
git commit -m "Fix: migration form_text_formatting untuk fresh install"
git commit -m "Update: composer dependencies ke versi terbaru"

# Bad
git commit -m "update"
git commit -m "fix"
```

### 3. Branch Strategy

- **`main`/`master`**: Production-ready code
- **`on-progress`**: Development branch (current)
- **`feature/*`**: Feature branches
- **`fix/*`**: Bug fix branches

### 4. Database Migrations

- **Selalu** test migration di lokal dulu
- **Jangan** hapus migration yang sudah di-deploy
- **Gunakan** `--force` di production (tidak ada konfirmasi)

### 5. Dependencies Update

- **Composer**: Update satu per satu, test setelah setiap update
- **NPM**: Update dengan hati-hati, test build setelah update

## Troubleshooting

### Error: Git Pull Conflict

```bash
# Stash perubahan lokal (jika ada)
git stash

# Pull ulang
git pull origin on-progress

# Apply stash (jika perlu)
git stash pop
```

### Error: Migration Gagal

```bash
# Cek error detail
php artisan migrate --force

# Rollback migration terakhir
php artisan migrate:rollback

# Fix migration file, lalu run lagi
php artisan migrate --force
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

# Remove node_modules dan install ulang
rm -rf node_modules package-lock.json
npm install
npm run build
```

### Error: Permission Denied

```bash
# Fix permissions
chown -R www-data:www-data /var/www/my_project
chmod -R ug+rwX /var/www/my_project/storage
chmod -R ug+rwX /var/www/my_project/bootstrap/cache
```

## Checklist Update Major

- [ ] Perubahan sudah di-test di lokal
- [ ] Code sudah di-commit dengan message jelas
- [ ] Code sudah di-push ke GitHub
- [ ] Backup VPS sudah dibuat (opsional)
- [ ] Code sudah di-pull di VPS
- [ ] Composer dependencies ter-update
- [ ] NPM dependencies ter-update & assets ter-build
- [ ] Migrations sudah dijalankan (jika ada)
- [ ] Caches sudah di-rebuild
- [ ] Permissions sudah benar
- [ ] Services sudah di-reload
- [ ] Website sudah di-test dan berfungsi normal
- [ ] Tidak ada error di log

## Next Steps

Setelah update major berhasil:
- Monitor log untuk beberapa jam pertama
- Test semua fitur penting
- Jika ada masalah, gunakan rollback strategy

---

**Last Updated**: 2026-01-04

