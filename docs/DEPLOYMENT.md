# Panduan Deployment

Dokumen ini adalah baseline deployment. Deployment production penuh dilakukan setelah seluruh tahap aplikasi selesai.

## Persiapan

- Gunakan server PHP dan database yang kompatibel dengan versi pada `composer.lock`.
- Buat database serta user production dengan prinsip least privilege.
- Buat `.env` langsung di server; jangan menyalin credential ke source code atau remote Git.
- Set `APP_ENV=production`, `APP_DEBUG=false`, URL HTTPS, timezone aplikasi `UTC`, dan display timezone `Asia/Jakarta`.
- Konfigurasikan mailer, queue, cache, session, WhatsApp contact, dan admin notification menggunakan secret manager/platform environment.
- Pastikan web root menunjuk ke direktori `public`, bukan root repository.

## Build dan deploy

```powershell
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
```

Jangan menjalankan `migrate:fresh` atau demo seeder pada production. Jalankan worker queue dan scheduler melalui process manager yang sesuai platform.

## Storage

- `storage/app/private` harus writable oleh user aplikasi tetapi tidak dapat diakses web server secara langsung.
- Disk local harus tetap `serve=false`.
- Jangan membuat symlink dari private storage ke public.
- Gunakan controller download dan policy untuk setiap akses.
- Tambahkan backup terenkripsi, malware scanning, serta kebijakan purge retention pada Tahap 6.
- ZIP/RAR tidak boleh diekstrak atau dijalankan otomatis.

## Keamanan sebelum go-live

- Ganti/hapus semua akun dan password demo.
- Verifikasi `APP_DEBUG=false` dan tidak ada credential pada Git history.
- Aktifkan HTTPS, secure cookie, HSTS, rate limiting, dan header keamanan.
- Aktifkan 2FA admin/staff setelah implementasi Tahap 6.
- Konfigurasikan log aggregation dan alert untuk authentication, force delete, download, status, progress, dan override.
- Uji customer A tidak dapat membaca proyek/file customer B dan staff hanya dapat membaca proyek yang ditugaskan.
- Jalankan `php artisan test`, `composer audit`, `npm audit`, dan smoke test setelah deploy.

## Rollback

Sebelum migration production, buat backup database dan verifikasi restore. Gunakan deployment release directory serta rollback aplikasi ke release sebelumnya. Jangan menggunakan `git reset --hard` sebagai prosedur deployment. Rollback schema hanya dilakukan jika migration memiliki `down()` yang aman dan backup terverifikasi.
