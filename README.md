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
| Tahap 6 | Implementasi aplikasi selesai | 2FA, upload scanning, retention/purge, reconciliation, security header, dan readiness guard |
| Tahap 7 | Audit final selesai | Full regression SQLite/MariaDB, browser QA, dokumentasi, dan release readiness |

Status release candidate: **STATUS B — READY WITH OPERATIONAL PREREQUISITES**.
Implementasi aplikasi selesai, tetapi ini bukan izin deployment production. Server
masih wajib menyediakan HTTPS, ClamAV, queue worker, scheduler, mail, private storage
terisolasi, enrollment 2FA, logging/monitoring, serta backup dan restore terverifikasi.
Seluruh guard `php artisan jokiinlah:readiness` harus PASS sebelum go-live.

## Stack

- Laravel 12, PHP 8.2+, Fortify, Eloquent, database queue/cache/session;
- Filament 5 dan Livewire 4 untuk panel operasional;
- Blade, Alpine.js, Tailwind CSS 4, dan Vite 6;
- MariaDB 10.4+ atau MySQL kompatibel;
- ClamAV TCP daemon untuk malware scanning production.

Role aplikasi adalah `customer`, `staff`, dan `admin`. Customer hanya mengakses
resource miliknya; staff hanya assigned project; admin memperoleh operasi global
sesuai policy.

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
- ClamAV daemon yang dapat dijangkau aplikasi (wajib untuk production)

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
PRIVATE_FILESYSTEM_DISK=local
MALWARE_SCANNER_ENABLED=false
```

Jangan commit `.env` atau credential production. Jangan memakai `migrate:fresh` pada
database yang berisi data; instalasi normal tidak memerlukannya. Scanner yang nonaktif
bersifat fail-closed: upload akan ditolak, bukan dianggap bersih.

## Akun demo local/staging

| Role | Email | Password |
|---|---|---|
| Admin | `admin@example.com` | `Password123!` |
| Staff | `staff@example.com` | `Password123!` |
| Customer | `customer@example.com` | `Password123!` |

Seeder demo menolak berjalan pada `APP_ENV=production`. Ganti atau hapus seluruh akun,
password, kontak, testimonial, file fixture, dan data demo sebelum production. Seeder
development idempotent dan membuat tiga fixture kecil pada private disk untuk
memverifikasi alur dokumen serta reconciliation; jangan jalankan seeder demo pada
database yang berisi data nyata.

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

Verifikasi akhir Tahap 7: **133 test lulus dengan 799 assertion** pada SQLite dan
MariaDB 10.4.32. Build Vite memproses 56 modul; Composer audit dan npm audit bersih.
Config, route, dan view cache berhasil dibuat lalu dibersihkan kembali untuk
development.

Perintah hardening dan pemeriksaan production:

```powershell
php artisan jokiinlah:retention-evaluate --dry-run
php artisan jokiinlah:purge --dry-run
php artisan jokiinlah:files-reconcile --checksum
php artisan schedule:list
php artisan jokiinlah:readiness
```

`jokiinlah:readiness` harus lulus pada server production sebelum go-live. Perintah
tersebut memeriksa konfigurasi minimum, bukan menggantikan uji koneksi scanner,
worker, cron, filesystem, HTTPS, ataupun simulasi restore.

Jalankan queue worker lokal:

```powershell
php artisan queue:work --queue=default --sleep=3 --tries=3 --timeout=90
```

Jalankan scheduler lokal pada terminal terpisah:

```powershell
php artisan schedule:work
```

Production wajib memakai process manager untuk worker dan cron `schedule:run` setiap
menit. Tiga task maintenance terdaftar: retention harian, purge harian, dan file
reconciliation mingguan.

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

Harness Tahap 6:

```powershell
$env:QA_BASE_URL='http://127.0.0.1:8006'
$env:QA_DEBUGGER_URL='http://127.0.0.1:9230'
$env:QA_PASSWORD='<password akun QA>'
$env:QA_TOTP_SECRET='<secret TOTP akun staff QA>'
node scripts/visual-qa-security.mjs
```

Hasil Tahap 6 memeriksa 15 state pada keenam viewport yang sama, tanpa overflow,
console error, network error, atau asset error. Bukti berada di
`docs/screenshots/tahap-6/visual-qa-report.json`.

Audit Tahap 7 mengulang 21 state publik, 27 state Customer Portal, dan 15 state
security/2FA. Responsive QA mencakup `360x800`, `390x844`, `768x1024`,
`1024x768`, `1280x720`, `1366x768`, `1440x900`, dan `1920x1080`.

## Keamanan dan integritas akademik

- Consultation/project/revision attachment berada pada private local disk.
- Physical path dan stored filename memakai UUID; original filename hanya metadata aman.
- MIME, extension, double extension, executable, ukuran, dan checksum diverifikasi.
- Upload masuk karantina sementara, dipindai melalui abstraction ClamAV, dan hanya
  hasil `clean` yang dipindah ke jalur final; infected dan scan failure fail-closed.
- ZIP/RAR disimpan opaque dan tidak diekstrak atau dijalankan.
- Status/progress/assignment/payment memakai action terotorisasi dan activity log.
- Staff hanya assigned project; customer hanya resource miliknya.
- Admin/staff wajib 2FA TOTP beserta recovery code; customer tidak diwajibkan.
- Retention memakai evaluasi dan two-phase purge idempoten; reconciliation hanya
  melaporkan secara default dan tidak menghapus orphan otomatis.
- Layanan melarang plagiarisme, fabrikasi data, pemalsuan penelitian, pengerjaan ujian,
  bypass anti-plagiarisme, pelanggaran hak cipta, dan aktivitas ilegal.
- CSP kompatibel Filament, header keamanan, session revocation, queue after-commit,
  scheduler, dan readiness guard telah diterapkan pada Tahap 6.
- Halaman setup/recovery 2FA mengirim `Cache-Control: no-store, private`.

## Deployment dan security disclosure

Gunakan [Deployment Checklist](docs/DEPLOYMENT-CHECKLIST.md) dan
[panduan deployment](docs/DEPLOYMENT.md). Ringkasan gate:

1. test, lint, build, dependency audit, backup, dan restore drill harus lulus;
2. environment production, HTTPS, secure cookie, private storage, mail, worker,
   scheduler, ClamAV, logging, dan monitoring harus diverifikasi;
3. jalankan migration non-destruktif, production cache, queue restart, dan readiness;
4. lakukan smoke test auth, 2FA, portal, panel, upload/scan/download, queue, dan cron;
5. rollback memakai release artifact serta backup terverifikasi, bukan force push
   atau `git reset --hard`.

Jangan membuka detail kerentanan atau data customer di issue publik. Laporkan masalah
ke maintainer repository melalui kanal privat yang disepakati, sertakan langkah
reproduksi minimal tanpa credential, token, private file, atau data pribadi. Segera
rotasi secret bila diduga terekspos.

## Dokumentasi

- [Arsitektur Tahap 1](docs/TAHAP-1-ANALISIS-DAN-ARSITEKTUR.md)
- [Implementasi Tahap 2](docs/TAHAP-2-IMPLEMENTASI.md)
- [Audit Tahap 2](docs/TAHAP-2-DOMAIN-DAN-AUTHENTICATION.md)
- [Penutupan Tahap 3](docs/TAHAP-3-PUBLIC-WEBSITE.md)
- [Penutupan Tahap 4](docs/TAHAP-4-CUSTOMER-PORTAL.md)
- [Penutupan Tahap 5](docs/TAHAP-5-ADMIN-STAFF-PANEL.md)
- [Hardening dan production readiness Tahap 6](docs/TAHAP-6-HARDENING-DAN-PRODUCTION-READINESS.md)
- [Audit final dan release readiness Tahap 7](docs/TAHAP-7-AUDIT-FINAL-DAN-RELEASE-READINESS.md)
- [Panduan deployment](docs/DEPLOYMENT.md)
- [Deployment checklist](docs/DEPLOYMENT-CHECKLIST.md)
- [Release notes v1.0.0-rc.1](docs/RELEASE-NOTES.md)
