# Panduan Deployment

Dokumen ini adalah baseline deployment. Deployment production penuh dilakukan setelah seluruh tahap aplikasi selesai.

## Persiapan

- Gunakan server PHP dan database yang kompatibel dengan versi pada `composer.lock`.
- Buat database serta user production dengan prinsip least privilege.
- Buat `.env` langsung di server; jangan menyalin credential ke source code atau remote Git.
- Set `APP_ENV=production`, `APP_DEBUG=false`, URL HTTPS, timezone aplikasi `UTC`, dan display timezone `Asia/Jakarta`.
- Konfigurasikan mailer, queue, cache, session, WhatsApp contact, ClamAV, trusted
  proxy, serta admin notification menggunakan secret manager/platform environment.
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
php artisan jokiinlah:readiness
```

Jangan menjalankan `migrate:fresh` atau demo seeder pada production. Jalankan worker:

```powershell
php artisan queue:work --queue=default --sleep=3 --tries=3 --timeout=90
```

Kelola worker memakai Supervisor, systemd, atau process manager platform. Tambahkan
cron scheduler satu menit sekali:

```cron
* * * * * cd /path/to/jokiinlah && php artisan schedule:run >> /dev/null 2>&1
```

## Storage

- `storage/app/private` harus writable oleh user aplikasi tetapi tidak dapat diakses web server secara langsung.
- Disk local harus tetap `serve=false`.
- Jangan membuat symlink dari private storage ke public.
- Gunakan controller download dan policy untuk setiap akses.
- Pastikan `PRIVATE_FILESYSTEM_DISK` menunjuk disk privat. Untuk S3/object storage,
  gunakan bucket private, least-privilege IAM, enkripsi at-rest, dan lifecycle yang
  tidak bertentangan dengan status purge aplikasi.
- Aktifkan ClamAV TCP daemon, batasi akses jaringannya hanya dari aplikasi, lalu isi
  `MALWARE_SCANNER_ENABLED=true`, driver `clamav`, host, port, dan timeout.
- Jalankan evaluasi retention harian, purge harian, dan reconciliation mingguan dari
  scheduler. Uji `--dry-run` sebelum eksekusi apply pertama.
- ZIP/RAR tidak boleh diekstrak atau dijalankan otomatis.

## Keamanan sebelum go-live

- Ganti/hapus semua akun dan password demo.
- Verifikasi `APP_DEBUG=false` dan tidak ada credential pada Git history.
- Aktifkan HTTPS dan isi `SESSION_SECURE_COOKIE=true`; HSTS hanya dikirim aplikasi
  pada environment production melalui request HTTPS yang dipercaya.
- Setiap akun admin/staff wajib menyelesaikan setup 2FA dan menyimpan recovery code
  secara offline sebelum panel dapat digunakan.
- Konfigurasikan log aggregation dan alert untuk authentication, force delete, download, status, progress, dan override.
- Uji customer A tidak dapat membaca proyek/file customer B dan staff hanya dapat membaca proyek yang ditugaskan.
- Jalankan `php artisan test`, `composer audit`, `npm audit`, dan smoke test setelah deploy.
- Pastikan `php artisan jokiinlah:readiness` lulus, `/up` sehat, queue tidak menumpuk,
  failed job dipantau, scheduler berjalan, dan scanner benar-benar merespons.

## Operasional retention dan reconciliation

```powershell
php artisan jokiinlah:retention-evaluate --dry-run --limit=100
php artisan jokiinlah:retention-evaluate --limit=100
php artisan jokiinlah:purge --dry-run --limit=50
php artisan jokiinlah:purge --limit=50
php artisan jokiinlah:files-reconcile --limit=500 --checksum
php artisan jokiinlah:files-reconcile --limit=500 --repair-state
php artisan jokiinlah:files-reconcile --limit=500 --quarantine-orphans
```

Command memakai lock dan batch, aman dicoba ulang, dan hanya operator terpercaya yang
boleh menjalankan mode apply. Reconciliation tidak menghapus orphan; opsi eksplisit
`--quarantine-orphans` hanya memindahkannya ke karantina untuk review.

## Backup dan restore

- Backup database dan private object/files pada jadwal yang sesuai RPO/RTO.
- Enkripsi backup, pisahkan credential, terapkan retention, dan simpan salinan
  off-site/beda failure domain.
- Sertakan database dan private files dari titik waktu konsisten.
- Lakukan restore drill berkala ke environment terisolasi; validasi migrasi, checksum
  file, akses policy, serta login 2FA.
- Catat waktu restore aktual, bukti integritas, operator, dan hasil drill. Backup yang
  belum pernah dipulihkan tidak dianggap terverifikasi.

## Rollback

Sebelum migration production, buat backup database dan verifikasi restore. Gunakan
deployment release directory serta rollback aplikasi ke release sebelumnya. Jangan
menggunakan `git reset --hard` sebagai prosedur deployment. Rollback schema hanya
dilakukan jika migration memiliki `down()` yang aman dan backup terverifikasi.
