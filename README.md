# Jokiinlah

Fondasi aplikasi **Pendampingan Akademik & Digital** untuk konsultasi, pengelolaan proyek pelanggan, analisis data, serta pengembangan solusi digital. Repository saat ini telah menyelesaikan Tahap 1 (arsitektur) dan Tahap 2 (domain serta autentikasi).

## Status implementasi

Tahap 2 menyediakan:

- Laravel 12, PHP 8.2, Blade, Tailwind CSS 4, Alpine.js, Vite, dan Fortify;
- autentikasi register, login/logout, reset password, dan verifikasi email;
- role `admin`, `staff`, dan `customer`, akun aktif, middleware, serta policy domain;
- 14 entitas domain, enum status, relasi, factory, migration, dan seeder idempoten;
- konversi konsultasi guest setelah akun customer terverifikasi;
- transisi status proyek normal dan admin override dengan alasan wajib;
- progress manual 0–100 dan audit perubahan;
- private file, UUID physical name, logical `document_uuid`, versioning, soft delete, retention, dan download terotorisasi;
- penyimpanan/proses waktu UTC dan konversi tampilan/input Asia/Jakarta;
- 19 automated tests dengan 59 assertions.

Landing page lengkap, portal customer, dan dashboard admin/Filament belum dibuat karena termasuk Tahap 3–5.

## Persyaratan lokal

- PHP 8.2 atau lebih baru yang kompatibel dengan Laravel 12
- Composer 2
- Node.js dan npm
- MariaDB 10.4+ atau MySQL yang kompatibel
- Ekstensi PHP yang diminta Composer

## Instalasi development

```powershell
git clone https://github.com/Dann098/Jokiinlah.git
cd Jokiinlah
composer install
npm install
Copy-Item .env.example .env
php artisan key:generate
```

Buat database development bernama `jokiinlah_dev` dengan collation `utf8mb4_unicode_ci`. Gunakan user MariaDB khusus yang hanya memiliki hak ke database tersebut, atau konfigurasi XAMPP lokal yang sudah diverifikasi. Isi konfigurasi lokal di `.env`; jangan commit `.env` dan jangan gunakan credential production.

Nilai minimum yang wajib diverifikasi:

```dotenv
APP_ENV=local
APP_TIMEZONE=UTC
DISPLAY_TIMEZONE=Asia/Jakarta
DB_CONNECTION=mysql
DB_DATABASE=jokiinlah_dev
FILESYSTEM_DISK=local
```

Kemudian jalankan:

```powershell
php artisan config:clear
php artisan migrate:fresh --seed
npm run build
php artisan test
php artisan serve
```

`migrate:fresh` menghapus seluruh tabel. Jalankan hanya setelah memastikan environment `local`, koneksi `mysql`, dan database tepat `jokiinlah_dev`. Jangan menjalankannya terhadap production.

## Akun demo

| Role | Email | Password |
|---|---|---|
| Admin | `admin@example.com` | `password` |
| Staff | `staff@example.com` | `password` |
| Customer | `customer@example.com` | `password` |

**Wajib mengganti atau menghapus seluruh password/data demo sebelum deployment production.** Seeder demo menolak berjalan saat `APP_ENV=production`.

## Pengujian

```powershell
php artisan test
php artisan route:list
npm run build
composer audit
```

Test menggunakan SQLite in-memory dan tidak memodifikasi MariaDB development. Validasi integrasi MariaDB tetap dilakukan terpisah melalui safety gate dan `migrate:fresh --seed` pada database development.

## Keamanan dan integritas akademik

- Berkas pelanggan berada di `storage/app/private` dan disk local tidak memiliki route serve langsung.
- Download hanya melalui controller dan policy. ZIP/RAR disimpan sebagai attachment; aplikasi tidak mengekstrak atau menjalankannya.
- Customer tidak dapat menghapus file yang sudah diproses; versi baru dibuat pada logical document yang sama.
- Penghapusan permanen hanya melalui action admin dengan alasan dan activity log.
- Layanan tidak boleh digunakan untuk plagiarisme, fabrikasi data, pemalsuan penelitian, pengerjaan ujian, atau aktivitas lain yang melanggar integritas akademik.
- 2FA untuk admin/staff dijadwalkan pada Tahap 6 dan belum diaktifkan.

## Dokumentasi

- [Arsitektur final Tahap 1](docs/TAHAP-1-ANALISIS-DAN-ARSITEKTUR.md)
- [Laporan implementasi Tahap 2](docs/TAHAP-2-IMPLEMENTASI.md)
- [Panduan deployment](docs/DEPLOYMENT.md)

Logo asli harus ditempatkan di `public/images/logo.jpeg`. Aplikasi tidak membuat placeholder ketika file belum tersedia.
