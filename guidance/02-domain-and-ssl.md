# Panduan Setup Domain & SSL

Panduan lengkap untuk menghubungkan domain ke VPS dan mengaktifkan HTTPS dengan Let's Encrypt.

## Prerequisites

- ✅ Domain sudah dibeli (contoh: `hb-ku.site`)
- ✅ VPS sudah aktif dan project sudah di-deploy
- ✅ Akses ke panel DNS registrar (contoh: Rumahweb)
- ✅ Akses SSH ke VPS

## Step 1: Konfigurasi DNS di Registrar

### Di Panel DNS Registrar (Rumahweb)

1. Login ke panel registrar domain kamu
2. Buka menu **"Manajemen DNS"** atau **"DNS Management"**
3. Tambahkan **A Record** baru:
   - **Domain/Host**: `@` (atau kosongkan untuk root domain)
   - **TTL**: `14400` (default, atau lebih kecil untuk update cepat)
   - **Tipe**: `A`
   - **IP atau Hostname**: `202.10.47.245` (IP VPS kamu)

4. (Opsional) Tambahkan **A Record** untuk `www`:
   - **Domain/Host**: `www`
   - **Tipe**: `A`
   - **IP atau Hostname**: `202.10.47.245`

### Verifikasi DNS

Tunggu beberapa menit (5-30 menit, kadang sampai 24 jam), lalu verifikasi:

```bash
# Dari local machine atau VPS
nslookup hb-ku.site
# atau
dig hb-ku.site

# Harus return IP: 202.10.47.245
```

## Step 2: Update Nginx Configuration

### Edit File Nginx

```bash
# Login ke VPS
ssh root@202.10.47.245

# Edit file konfigurasi
nano /etc/nginx/sites-available/hb-ku
```

### Update server_name

Ubah baris `server_name` dari IP menjadi domain:

```nginx
# Sebelum:
server_name 202.10.47.245;

# Sesudah:
server_name hb-ku.site;
```

Jika mau support `www` juga:

```nginx
server_name hb-ku.site www.hb-ku.site;
```

### Test & Reload Nginx

```bash
# Test konfigurasi
nginx -t

# Reload Nginx
systemctl reload nginx
```

## Step 3: Install Certbot

Certbot adalah tool untuk mendapatkan sertifikat SSL gratis dari Let's Encrypt.

```bash
# Update package list
apt-get update

# Install certbot dan plugin nginx
apt-get install -y certbot python3-certbot-nginx
```

## Step 4: Dapatkan Sertifikat SSL

### Untuk Root Domain Saja

```bash
# Jalankan certbot dengan mode non-interactive
certbot --nginx -d hb-ku.site \
  --non-interactive \
  --agree-tos \
  -m admin@hb-ku.site \
  --redirect
```

**Penjelasan parameter:**
- `--nginx`: Gunakan plugin Nginx (otomatis konfigurasi)
- `-d hb-ku.site`: Domain yang akan di-cover
- `--non-interactive`: Tidak perlu input manual
- `--agree-tos`: Setuju dengan terms of service
- `-m admin@hb-ku.site`: Email untuk notifikasi (ganti dengan email kamu)
- `--redirect`: Otomatis redirect HTTP ke HTTPS

### Untuk Root + WWW

```bash
certbot --nginx -d hb-ku.site -d www.hb-ku.site \
  --non-interactive \
  --agree-tos \
  -m admin@hb-ku.site \
  --redirect
```

### Output yang Diharapkan

```
Successfully received certificate.
Certificate is saved at: /etc/letsencrypt/live/hb-ku.site/fullchain.pem
Key is saved at:         /etc/letsencrypt/live/hb-ku.site/privkey.pem
This certificate expires on 2026-04-04.
Successfully deployed certificate for hb-ku.site to /etc/nginx/sites-enabled/hb-ku
Congratulations! You have successfully enabled HTTPS on https://hb-ku.site
```

## Step 5: Verifikasi HTTPS

### Test dari VPS

```bash
# Test HTTP redirect
curl -I http://hb-ku.site
# Harus return: HTTP/1.1 301 Moved Permanently

# Test HTTPS
curl -I https://hb-ku.site
# Harus return: HTTP/1.1 200 OK
```

### Test dari Browser

1. Buka browser
2. Akses: `https://hb-ku.site`
3. Pastikan ada **gembok hijau** di address bar
4. Pastikan tidak ada warning SSL

## Step 6: Update APP_URL di Laravel

Update `.env` di VPS:

```bash
# Edit .env
nano /var/www/my_project/.env

# Update APP_URL
APP_URL=https://hb-ku.site

# Clear dan rebuild config cache
cd /var/www/my_project
php artisan config:clear
php artisan config:cache
```

## Auto-Renewal SSL Certificate

Certbot otomatis setup timer untuk renew sertifikat. Cek status:

```bash
# Cek timer status
systemctl status certbot.timer

# Test renewal (dry run)
certbot renew --dry-run
```

Sertifikat Let's Encrypt berlaku **90 hari** dan akan auto-renew sebelum expire.

## Troubleshooting

### Error: DNS belum propagate

```bash
# Cek DNS dari berbagai server
dig @8.8.8.8 hb-ku.site
dig @1.1.1.1 hb-ku.site

# Tunggu sampai semua return IP yang benar
```

### Error: Certbot gagal verifikasi domain

Pastikan:
- DNS sudah benar dan propagate
- Port 80 dan 443 terbuka di firewall
- Nginx sudah running dan bisa diakses via HTTP

```bash
# Cek firewall
ufw status

# Buka port jika perlu
ufw allow 80/tcp
ufw allow 443/tcp
```

### Error: Nginx config error setelah certbot

```bash
# Cek konfigurasi
nginx -t

# Lihat error detail
cat /var/log/nginx/error.log | tail -n 50
```

### Certificate Expired

```bash
# Manual renew
certbot renew

# Reload nginx setelah renew
systemctl reload nginx
```

## Checklist Setup Domain & SSL

- [ ] DNS A Record sudah ditambahkan di registrar
- [ ] DNS sudah propagate (nslookup return IP benar)
- [ ] Nginx `server_name` sudah diupdate ke domain
- [ ] Nginx sudah di-reload
- [ ] Certbot sudah terinstall
- [ ] SSL certificate sudah didapatkan
- [ ] HTTP → HTTPS redirect sudah aktif
- [ ] Website bisa diakses via HTTPS
- [ ] `APP_URL` di `.env` sudah diupdate
- [ ] Config cache sudah di-rebuild

## Next Steps

Setelah domain & SSL aktif, lanjut ke:
- [Panduan Update Major](03-update-major.md) - untuk update perubahan besar
- [Panduan Update Minor](04-update-minor-on-vps.md) - untuk update kecil

---

**Last Updated**: 2026-01-04

