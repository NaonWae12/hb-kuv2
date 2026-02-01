# Panduan Update Minor (Perubahan Kecil)

Panduan untuk update perubahan kecil langsung di VPS tanpa perlu push ulang ke GitHub.

## Kapan Menggunakan Panduan Ini?

Gunakan panduan ini untuk:
- ✅ Update konfigurasi `.env` (APP_NAME, APP_URL, dll)
- ✅ Update konfigurasi kecil
- ✅ Rebuild cache setelah perubahan config
- ✅ Fix permission issues
- ✅ Clear log files

**JANGAN** gunakan untuk:
- ❌ Perubahan kode (pakai [panduan update major](03-update-major.md))
- ❌ Perubahan database structure
- ❌ Perubahan dependencies

## Prerequisites

- ✅ Akses SSH ke VPS
- ✅ Basic knowledge tentang text editor (nano/vim)

## Step 1: Edit File yang Diperlukan

### 1.1 Edit .env

```bash
# Login ke VPS
ssh root@202.10.47.245

# Masuk ke folder project
cd /var/www/my_project

# Edit .env dengan nano
nano .env
```

**Contoh perubahan:**
```env
# Sebelum
APP_NAME=Laravel

# Sesudah
APP_NAME=Hb-ku
```

**Shortcut Nano:**
- `Ctrl + O`: Save
- `Ctrl + X`: Exit
- `Ctrl + W`: Search

### 1.2 Edit File Lain (Jika Perlu)

```bash
# Edit file konfigurasi lain
nano config/app.php
nano config/database.php
# dll
```

## Step 2: Rebuild Caches

Setelah edit `.env` atau config, **selalu** rebuild cache:

```bash
# Clear semua cache
php artisan optimize:clear

# Rebuild config cache (penting setelah edit .env)
php artisan config:cache

# Rebuild route cache (jika ada perubahan route)
php artisan route:cache

# Rebuild view cache (jika ada perubahan view)
php artisan view:cache
```

## Step 3: Rebuild Frontend Assets (Jika Perlu)

Jika ada perubahan di `.env` yang mempengaruhi frontend (misal: `VITE_APP_NAME`):

```bash
# Rebuild frontend
npm run build

# Pastikan permissions benar
chown -R www-data:www-data public/build
```

## Step 4: Reload Services

```bash
# Reload Nginx (jika ada perubahan config nginx)
nginx -t && systemctl reload nginx

# Restart PHP-FPM (jika ada perubahan PHP config)
systemctl restart php8.3-fpm
```

## Step 5: Verifikasi

```bash
# Test website masih berfungsi
curl -I https://hb-ku.site

# Cek log untuk error
tail -n 20 /var/www/my_project/storage/logs/laravel.log
```

## Contoh Kasus: Update APP_NAME

### Scenario
Mengubah title website dari "Laravel" menjadi "Hb-ku".

### Step-by-Step

```bash
# 1. Login ke VPS
ssh root@202.10.47.245

# 2. Edit .env
cd /var/www/my_project
nano .env
# Ubah: APP_NAME=Laravel → APP_NAME=Hb-ku
# Save: Ctrl+O, Exit: Ctrl+X

# 3. Rebuild config cache
php artisan config:clear
php artisan config:cache

# 4. Verifikasi
curl -I https://hb-ku.site/login
```

### Hasil
Title di browser tab akan berubah dari "Laravel" menjadi "Hb-ku" setelah refresh.

## Contoh Kasus: Update APP_URL

### Scenario
Mengubah APP_URL setelah setup domain baru.

### Step-by-Step

```bash
# 1. Edit .env
cd /var/www/my_project
nano .env
# Ubah: APP_URL=http://202.10.47.245 → APP_URL=https://hb-ku.site

# 2. Rebuild config cache
php artisan config:clear
php artisan config:cache

# 3. Verifikasi
php artisan tinker --execute="echo config('app.url');"
```

## Contoh Kasus: Clear Log Files

### Scenario
Log file terlalu besar, perlu di-clear.

### Step-by-Step

```bash
# 1. Backup log lama (opsional)
cd /var/www/my_project
cp storage/logs/laravel.log storage/logs/laravel.log.backup

# 2. Clear log
echo "" > storage/logs/laravel.log

# 3. Fix permissions
chown www-data:www-data storage/logs/laravel.log
chmod 664 storage/logs/laravel.log
```

## Contoh Kasus: Fix Permissions

### Scenario
Website error karena permission issues.

### Step-by-Step

```bash
# 1. Fix ownership
chown -R www-data:www-data /var/www/my_project

# 2. Fix storage permissions
chmod -R ug+rwX /var/www/my_project/storage
chmod -R ug+rwX /var/www/my_project/bootstrap/cache

# 3. Fix log permissions
chmod 664 /var/www/my_project/storage/logs/laravel.log
chown www-data:www-data /var/www/my_project/storage/logs/laravel.log
```

## Catatan Penting

### ⚠️ Risiko Update Minor

1. **Perubahan tidak ter-track di Git**
   - Perubahan langsung di VPS tidak ada di repository
   - Jika VPS crash, perubahan bisa hilang
   - **Solusi**: Setelah update minor, commit perubahan ke Git juga (jika memungkinkan)

2. **Perubahan bisa tertimpa saat update major**
   - Jika pull code baru, perubahan di VPS bisa tertimpa
   - **Solusi**: Dokumentasikan perubahan, atau commit ke Git

3. **Tidak ada version control**
   - Sulit rollback jika ada masalah
   - **Solusi**: Backup file sebelum edit

### ✅ Best Practices

1. **Selalu backup sebelum edit**
   ```bash
   cp .env .env.backup
   ```

2. **Dokumentasikan perubahan**
   - Catat apa yang diubah dan kenapa
   - Jika perlu, commit ke Git setelahnya

3. **Test setelah perubahan**
   - Pastikan website masih berfungsi
   - Cek log untuk error

4. **Gunakan untuk perubahan kecil saja**
   - Untuk perubahan besar, gunakan [panduan update major](03-update-major.md)

## Troubleshooting

### Error: Config cache tidak update

```bash
# Clear semua cache
php artisan optimize:clear

# Rebuild config cache
php artisan config:cache

# Verifikasi
php artisan tinker --execute="echo config('app.name');"
```

### Error: Permission denied saat edit

```bash
# Edit sebagai root (sudah login sebagai root)
# atau gunakan sudo jika perlu
sudo nano .env
```

### Error: Website error setelah perubahan

```bash
# Rollback perubahan
cp .env.backup .env

# Rebuild cache
php artisan config:clear
php artisan config:cache

# Reload services
systemctl reload nginx
systemctl restart php8.3-fpm
```

## Checklist Update Minor

- [ ] File yang akan diubah sudah di-backup
- [ ] Perubahan sudah dilakukan (edit file)
- [ ] Config cache sudah di-rebuild (jika edit .env/config)
- [ ] Frontend assets sudah di-rebuild (jika perlu)
- [ ] Permissions sudah benar (jika ada perubahan)
- [ ] Services sudah di-reload (jika perlu)
- [ ] Website sudah di-test dan berfungsi normal
- [ ] Tidak ada error di log
- [ ] Perubahan sudah didokumentasikan (opsional)

## Next Steps

Setelah update minor berhasil:
- Monitor website untuk beberapa saat
- Jika perlu, commit perubahan ke Git juga
- Dokumentasikan perubahan untuk referensi

---

**Last Updated**: 2026-01-04

