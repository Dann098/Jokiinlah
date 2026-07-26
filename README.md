# Jokiinlah

Aplikasi **Pendampingan Akademik & Digital** berbasis Laravel untuk konsultasi,
konten publik, Customer Portal, serta operasional admin dan staff.

## Status implementasi

| Tahap | Status | Ringkasan |
|---|---|---|
| Tahap 1 | Selesai | Analisis produk dan arsitektur modular monolith |
| Tahap 2 | Selesai | Domain, autentikasi, authorization, dan private file |
| Tahap 3 | Selesai dan terverifikasi | Website publik, konsultasi guest, SEO, accessibility, dan visual QA |
| Tahap 4 | Selesai dan terverifikasi | Customer Portal, versioning, revisi, reminder, appointment, profil, dan visual QA |
| Tahap 5 | Selesai dan terverifikasi | Panel Filament admin/staff, scoping operasional, test, dan visual QA |
| Tahap 6 | Belum dimulai | Hardening infrastruktur lanjutan |

## Fitur utama

Website publik menyediakan landing page responsif, layanan, portofolio, artikel, FAQ,
form konsultasi guest, halaman legal, SEO metadata, sitemap, robots, dan WhatsApp CTA.
Scope active/published mencegah konten nonaktif, draft, atau future tampil ke publik.

Customer Portal berada di `/dashboard` dan menyediakan:

- ringkasan serta daftar/detail proyek milik customer;
- status, progress, dan milestone read-only;
- private file, download, upload, dan server-generated versioning;
- pengajuan revisi dengan attachment privat;
- reminder customer-visible, appointment, profil, dan perubahan password;
- policy, ownership query, dan scoped nested binding untuk mencegah IDOR.

Panel Filament berada di `/admin` dan menyediakan:

- dashboard admin dengan consultation, project, revision, deadline, payment manual, dan appointment;
- dashboard staff yang hanya menghitung assigned project;
- Customer, Staff, Consultation, Project, Service, Portfolio, Article, Testimonial, FAQ,
  Site Setting, dan Activity Log Resource;
- relation manager milestone, private file/versioning, revision, reminder, dan appointment;
- status transition, progress, assignment, serta payment status manual admin-only;
- global search, widget, notification, direct URL, dan child record yang mengikuti policy
  serta server-side query scope.

Customer tidak dapat masuk `/admin`. Staff tidak dapat melihat consultation, unassigned
project, payment, user/content management, site setting, atau global activity log.

## Persyaratan lokal

- PHP 8.2+ dengan ekstensi Composer yang dibutuhkan, termasuk `intl`
- Composer 2
- Node.js dan npm
- MariaDB 10.4+ atau MySQL kompatibel

## Instalasi development

```powershell
git clone https://github.com/Dann098/Jokiinlah.git
cd Jokiinlah
composer install
npm.cmd install
Copy-Item .env.example .env
php artisan key:generate
```

Buat database development, lengkapi `.env`, lalu jalankan setup non-destruktif:

```powershell
php artisan optimize:clear
php artisan migrate --seed
npm.cmd run build
php artisan test
php artisan serve
```

Konfigurasi minimum:

```dotenv
APP_ENV=local
APP_TIMEZONE=UTC
DISPLAY_TIMEZONE=Asia/Jakarta
DB_CONNECTION=mysql
FILESYSTEM_DISK=local
```

Jangan commit `.env` atau credential production. Jangan memakai `migrate:fresh` pada
database yang berisi data; instalasi normal tidak memerlukannya.

## Akun demo local/staging

| Role | Email | Password |
|---|---|---|
| Admin | `admin@example.com` | `Password123!` |
| Staff | `staff@example.com` | `Password123!` |
| Customer | `customer@example.com` | `Password123!` |

Seeder demo menolak berjalan pada `APP_ENV=production`. Ganti atau hapus seluruh akun,
password, kontak, testimonial, dan data demo sebelum production.

## Test, build, dan audit

```powershell
vendor\bin\pint --test
php artisan optimize:clear
php artisan migrate:status
php artisan route:list
php artisan test
npm.cmd run build
npm.cmd audit
composer validate --strict
composer audit
```

Verifikasi Tahap 5: **109 test lulus dengan 671 assertion**. Build Vite memproses
56 modul; Composer audit dan npm audit bersih.

## Visual QA

Website publik:

```powershell
$env:QA_BASE_URL='http://127.0.0.1:8003'
$env:QA_DEBUGGER_URL='http://127.0.0.1:9225'
node scripts/visual-qa.mjs
```

Customer Portal:

```powershell
$env:QA_BASE_URL='http://127.0.0.1:8003'
$env:QA_DEBUGGER_URL='http://127.0.0.1:9225'
$env:QA_FOREIGN_PROJECT_ID='2'
node scripts/visual-qa-customer.mjs
```

Panel admin/staff:

```powershell
$env:QA_BASE_URL='http://localhost:8000'
$env:QA_DEBUGGER_URL='http://127.0.0.1:9226'
node scripts/visual-qa-admin-staff.mjs
```

Harness Tahap 5 memeriksa 54 state pada viewport `360x800`, `390x844`, `768x1024`,
`1024x768`, `1366x768`, dan `1440x900`, termasuk validation error, global/table search,
notification, relation manager, mobile navigation, dan unauthorized state. Hasil berada
di `docs/screenshots/tahap-5/visual-qa-report.json`.

## Keamanan dan integritas akademik

- Consultation/project/revision attachment berada pada private local disk.
- Physical path dan stored filename memakai UUID; original filename hanya metadata aman.
- MIME, extension, double extension, executable, ukuran, dan checksum diverifikasi.
- ZIP/RAR disimpan opaque dan tidak diekstrak atau dijalankan.
- Status/progress/assignment/payment memakai action terotorisasi dan activity log.
- Staff hanya assigned project; customer hanya resource miliknya.
- Layanan melarang plagiarisme, fabrikasi data, pemalsuan penelitian, pengerjaan ujian,
  bypass anti-plagiarisme, pelanggaran hak cipta, dan aktivitas ilegal.
- 2FA, malware scanner, purge scheduler, object storage production, dan full CSP ditunda
  ke Tahap 6; Tahap 6 belum dimulai.

## Dokumentasi

- [Arsitektur Tahap 1](docs/TAHAP-1-ANALISIS-DAN-ARSITEKTUR.md)
- [Implementasi Tahap 2](docs/TAHAP-2-IMPLEMENTASI.md)
- [Audit Tahap 2](docs/TAHAP-2-DOMAIN-DAN-AUTHENTICATION.md)
- [Penutupan Tahap 3](docs/TAHAP-3-PUBLIC-WEBSITE.md)
- [Penutupan Tahap 4](docs/TAHAP-4-CUSTOMER-PORTAL.md)
- [Penutupan Tahap 5](docs/TAHAP-5-ADMIN-STAFF-PANEL.md)
- [Panduan deployment](docs/DEPLOYMENT.md)
