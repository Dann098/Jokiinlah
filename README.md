# Jokiinlah

Aplikasi **Pendampingan Akademik & Digital** berbasis Laravel untuk konsultasi, konten publik, fondasi pengelolaan proyek pelanggan, analisis data, serta pengembangan solusi digital.

## Status implementasi

| Tahap | Status | Ringkasan |
|---|---|---|
| Tahap 1 | Selesai | Analisis produk dan arsitektur modular monolith |
| Tahap 2 | Selesai | Domain, autentikasi, authorization, dan fondasi private file |
| Tahap 3 | Selesai dan terverifikasi | Website publik, landing page, konsultasi guest, SEO, accessibility, dan visual QA |
| Tahap 4 | Belum dimulai | Customer Portal lengkap |
| Tahap 5–6 | Belum dimulai | Panel admin/staff dan hardening lanjutan |

## Website publik

Tahap 3 menyediakan:

- landing page responsif dengan navbar desktop/mobile, hero, layanan, cara kerja, portofolio, testimonial terkontrol, FAQ, CTA, dan footer;
- daftar serta detail layanan aktif;
- daftar serta detail portofolio published;
- daftar serta detail artikel published yang tidak berada di masa depan;
- pencarian, filter kategori, pagination, query-string preservation, dan empty state;
- FAQ aktif dengan accordion accessible;
- form konsultasi guest dengan validasi Bahasa Indonesia, consent, honeypot, rate limiter IP/identitas, dan duplicate-submission protection;
- attachment konsultasi pada private local disk dengan UUID path/name, MIME/extension/size validation, checksum SHA-256, serta cleanup saat transaksi gagal;
- notification database untuk admin aktif dan mail notification yang tidak membatalkan konsultasi jika gagal;
- CTA WhatsApp terpusat dan URL-encoded;
- kebijakan privasi, syarat dan ketentuan, serta ketentuan integritas akademik;
- title, meta description, canonical, Open Graph, Twitter card, JSON-LD, sitemap, robots, dan halaman 404 khusus;
- self-hosted Playfair Display serta Plus Jakarta Sans, animasi ringan, reduced-motion support, dan visible keyboard focus.

Route publik utama:

```text
GET  /
GET  /layanan
GET  /layanan/{service:slug}
GET  /portofolio
GET  /portofolio/{portfolio:slug}
GET  /artikel
GET  /artikel/{article:slug}
GET  /faq
GET  /kontak
POST /konsultasi
GET  /kebijakan-privasi
GET  /syarat-dan-ketentuan
GET  /sitemap.xml
GET  /robots.txt
```

## Persyaratan lokal

- PHP 8.2+ yang kompatibel dengan Laravel 12
- Composer 2
- Node.js dan npm
- MariaDB 10.4+ atau MySQL yang kompatibel
- Ekstensi PHP yang diminta Composer

## Instalasi development

```powershell
git clone https://github.com/Dann098/Jokiinlah.git
cd Jokiinlah
composer install
npm.cmd install
Copy-Item .env.example .env
php artisan key:generate
```

Buat database development `jokiinlah_dev` dengan collation `utf8mb4_unicode_ci`, lalu lengkapi `.env` lokal. Jangan commit `.env` dan jangan gunakan credential production.

Nilai minimum yang wajib diverifikasi:

```dotenv
APP_ENV=local
APP_TIMEZONE=UTC
DISPLAY_TIMEZONE=Asia/Jakarta
DB_CONNECTION=mysql
DB_DATABASE=jokiinlah_dev
FILESYSTEM_DISK=local
```

Jalankan setup non-destruktif:

```powershell
php artisan optimize:clear
php artisan migrate --seed
npm.cmd run build
php artisan test
php artisan serve
```

> **PERINGATAN DESTRUKTIF:** `php artisan migrate:fresh --seed` menghapus seluruh tabel pada database aktif. Gunakan hanya pada database development kosong yang namanya telah diverifikasi, tidak pernah pada staging/production atau database yang berisi data penting. Workflow instalasi normal di atas tidak memerlukan `migrate:fresh`.

## Akun demo development

| Role | Email | Password |
|---|---|---|
| Admin | `admin@example.com` | `Password123!` |
| Staff | `staff@example.com` | `Password123!` |
| Customer | `customer@example.com` | `Password123!` |

**Ganti atau hapus seluruh akun, password, kontak, dan data demo sebelum deployment production.** Seeder demo menolak berjalan saat `APP_ENV=production`.

## Test, build, dan audit

```powershell
vendor\bin\pint --test
php artisan test
php artisan route:list
npm.cmd run build
npm.cmd audit
composer validate --strict
composer audit
```

Test memakai SQLite in-memory dan tidak memodifikasi MariaDB development. Verifikasi terakhir Tahap 3: **74 test lulus dengan 357 assertion**.

## Visual QA

`scripts/visual-qa.mjs` memakai browser Chromium dengan Chrome DevTools Protocol. Jalankan Laravel pada port 8003 dan browser headless dengan remote debugging port 9225, kemudian:

```powershell
$env:QA_BASE_URL='http://127.0.0.1:8003'
$env:QA_DEBUGGER_URL='http://127.0.0.1:9225'
node scripts/visual-qa.mjs
```

Script menguji viewport `360x800`, `390x844`, `768x1024`, `1024x768`, `1366x768`, dan `1440x900`, termasuk menu mobile, detail konten, form error/sukses, halaman legal, empty state, dan 404. Hasil berada di `docs/screenshots/visual-qa-report.json`.

Varian WebP, favicon, dan OG image dapat dibuat ulang dari logo asli dengan `php scripts/generate_brand_assets.php`.

## Keamanan dan integritas akademik

- Dokumen konsultasi dan proyek berada di `storage/app/private`; disk local tidak mempunyai direct serving route.
- Original filename hanya metadata tersanitasi. Physical path dan filename memakai UUID.
- Executable, MIME mismatch, file tanpa extension, dangerous double-extension, dan file oversized ditolak.
- ZIP/RAR disimpan sebagai opaque private attachment; aplikasi tidak mengekstrak, menjalankan, atau membuat preview archive.
- Customer/staff tidak dapat membaca konsultasi awal atau attachment guest.
- Guest consultation tidak membuat akun otomatis.
- Layanan menolak plagiarisme, ghostwriting untuk diserahkan sebagai karya mandiri, fabrikasi/manipulasi data, pemalsuan, pengerjaan ujian, bypass anti-plagiarisme, pelanggaran hak cipta, dan aktivitas ilegal.
- 2FA, malware scanner, dan purge scheduler tetap ditunda ke Tahap 6.

## Dokumentasi

- [Arsitektur final Tahap 1](docs/TAHAP-1-ANALISIS-DAN-ARSITEKTUR.md)
- [Implementasi Tahap 2](docs/TAHAP-2-IMPLEMENTASI.md)
- [Audit penutup Tahap 2](docs/TAHAP-2-DOMAIN-DAN-AUTHENTICATION.md)
- [Penutupan Tahap 3](docs/TAHAP-3-PUBLIC-WEBSITE.md)
- [Panduan deployment](docs/DEPLOYMENT.md)

Logo asli berada di `public/images/logo.jpeg`; varian WebP hanya optimasi asset dan tidak mengganti identitas.
