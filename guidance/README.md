# Panduan Deploy & Maintenance Hb-ku

Selamat datang di dokumentasi panduan deploy dan maintenance untuk aplikasi **Hb-ku**.

## Daftar Panduan

### 1. [Deploy ke VPS](01-deploy-to-vps.md)
Panduan lengkap untuk mengupload dan deploy project ke VPS untuk pertama kali. Termasuk setup SSH key, backup project lama, clone repository, install dependencies, dan konfigurasi server.

### 2. [Setup Domain & SSL](02-domain-and-ssl.md)
Panduan untuk menghubungkan domain ke VPS dan mengaktifkan HTTPS dengan Let's Encrypt. Termasuk konfigurasi DNS, Nginx, dan sertifikat SSL.

### 3. [Update Major (Perubahan Besar)](03-update-major.md)
Workflow untuk update perubahan besar: development di lokal → testing → commit & push → deploy ke VPS. Termasuk strategi rollback jika terjadi masalah.

### 4. [Update Minor (Perubahan Kecil)](04-update-minor-on-vps.md)
Panduan untuk update perubahan kecil langsung di VPS tanpa perlu push ulang. Contoh: update konfigurasi `.env`, rebuild cache, dll.

## Alur Singkat

```
┌─────────────────────────────────────────────────────────┐
│ 1. Deploy Pertama Kali                                  │
│    → Setup SSH Key                                      │
│    → Backup Project Lama                                │
│    → Clone Repository                                   │
│    → Install Dependencies                               │
│    → Setup Permissions                                 │
└─────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────┐
│ 2. Setup Domain & SSL                                   │
│    → Konfigurasi DNS (A Record)                        │
│    → Update Nginx server_name                           │
│    → Install Certbot                                    │
│    → Aktifkan HTTPS                                     │
└─────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────┐
│ 3. Update Project                                       │
│    ├─ Major Changes → Ikuti panduan 03                  │
│    └─ Minor Changes → Ikuti panduan 04                  │
└─────────────────────────────────────────────────────────┘
```

## Prerequisites

Sebelum mulai, pastikan kamu sudah punya:

- ✅ **Akses VPS** dengan SSH key atau password
- ✅ **Repository GitHub** yang sudah terhubung
- ✅ **Domain** yang sudah dibeli (untuk panduan domain)
- ✅ **Basic knowledge** tentang command line (terminal/SSH)

## Informasi Penting

### Lokasi Project di VPS
- **Path**: `/var/www/my_project`
- **Web Root**: `/var/www/my_project/public`
- **User**: `www-data` (untuk web server)

### Services yang Digunakan
- **Web Server**: Nginx
- **PHP**: PHP 8.3-FPM
- **Node.js**: v18.19.1 (untuk build frontend)
- **Composer**: v2.9.2

### Keamanan
⚠️ **JANGAN** commit file-file berikut ke repository:
- `.env` (berisi credential database, API keys, dll)
- `storage/logs/*.log` (berisi informasi sensitif)
- File backup dengan password/token

## Troubleshooting

Jika mengalami masalah saat deploy atau update, cek:

1. **Log Laravel**: `storage/logs/laravel.log`
2. **Log Nginx**: `/var/log/nginx/error.log`
3. **Status Services**: `systemctl status nginx` dan `systemctl status php8.3-fpm`
4. **Permission**: Pastikan `storage/` dan `bootstrap/cache/` writable oleh `www-data`

## Kontak & Support

Untuk pertanyaan atau masalah teknis, silakan buka issue di repository atau hubungi developer.

---

**Last Updated**: 2026-01-04

