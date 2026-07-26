# Penutupan Tahap 3 — Website Publik dan Landing Page

**Status teknis:** Selesai dan terverifikasi

**Tanggal audit/penutupan:** 27 Juli 2026

**Branch:** `main`
**Scope:** Tahap 3 saja

## 1. Scope

Tahap 3 menutup implementasi website publik tanpa membuat ulang source yang sudah ada:

- landing page;
- daftar/detail layanan, portofolio, dan artikel;
- FAQ dan kontak;
- konsultasi guest serta attachment private;
- notification admin dan CTA WhatsApp;
- privacy, terms, dan integritas akademik;
- SEO dasar, sitemap, robots, dan structured data;
- responsive design, accessibility, animasi ringan, dan performance dasar;
- automated test, visual QA, dokumentasi, dan release Git.

Scope yang tidak dikerjakan: Customer Portal lengkap, dashboard customer, Filament, panel admin/staff final, membership, quotation, invoice, payment gateway, chat internal, 2FA, purge scheduler, malware scanner, deployment production, dan pekerjaan Tahap 4–6 lainnya.

## 2. Baseline sebelum perbaikan

- Working tree sudah memuat implementasi Tahap 3 lokal yang belum dikomit; seluruh perubahan dipertahankan dan diaudit.
- README masih menyatakan landing page belum dibuat dan repository hanya selesai sampai Tahap 2.
- `docs/TAHAP-3-PUBLIC-WEBSITE.md` belum tersedia.
- Baseline test: **60 passed, 247 assertions**, 0 failed.
- Baseline Vite build: berhasil, 56 modules transformed.
- Baseline Composer audit: tidak ada advisory.
- Baseline npm audit: 0 vulnerability.
- Laporan visual lama berstatus passed tetapi hanya memuat 13 state dan bertanggal 22 Juli 2026.
- MariaDB awalnya belum aktif dan database `jokiinlah_dev` belum ada. Database development kosong dibuat, lalu `php artisan migrate --seed` dijalankan tanpa perintah destructive.
- `.env`, `database/database.sqlite`, dan `public/build` tidak tracked, di-ignore, dan tidak ditemukan dalam histori Git.

## 3. Temuan audit

| ID | Severity | Area | Temuan | Bukti | Tindakan |
|---|---|---|---|---|---|
| T3-01 | High | Portofolio | Detail portofolio published menghasilkan HTTP 500 karena directive Blade bersebelahan tidak terkompilasi benar | Visual QA menerima 500 dan log menunjukkan `unexpected token endif` | View dipecah menjadi struktur Blade eksplisit; test detail published ditambahkan |
| T3-02 | Medium | Rate limiter | Identitas dapat memakai variasi `08`, `8`, dan `62` untuk memperoleh cache key berbeda | Normalisasi limiter tidak sama dengan Form Request | Nomor dinormalisasi sebelum hashing; regression test variasi format ditambahkan |
| T3-03 | Medium | Duplicate submission | Race pada unique fingerprint dapat melempar exception walau request identik sudah tersimpan | Catch lama selalu rethrow | Unique violation mengambil record existing dan mengembalikan request code yang sama; file percobaan dibersihkan |
| T3-04 | Medium | Attachment | Dangerous double-extension dan file tanpa extension belum ditolak eksplisit | Rule hanya bergantung pada MIME/type builder | Pemeriksaan extension client dan intermediate executable extension ditambahkan |
| T3-05 | Medium | Accessibility | Menu mobile belum mengelola fokus, resize, body scroll, dan return focus | Alpine component hanya toggle boolean | Focus masuk/keluar, Escape, resize close, aria label, active state, body lock, dan fallback noscript ditambahkan |
| T3-06 | Medium | Form UX | Error summary tidak fokus dan helper/error tidak dapat direferensikan bersamaan | `aria-describedby` memilih salah satu | Summary focusable dengan link ke field; helper dan error ID digabung |
| T3-07 | Medium | Visual QA | State detail, legal, FAQ, menu terbuka, empty, dan 404 tidak diuji | Report lama hanya 13 state | Harness diperluas menjadi 21 halaman/state dan enam viewport |
| T3-08 | Low | Bahasa | Pesan validation default masih dapat tampil dalam Bahasa Inggris | `name.required` menampilkan default framework | Pesan dan attribute konsultasi dilokalkan ke Bahasa Indonesia |
| T3-09 | Low | SEO/UX | Canonical pagination, noindex pencarian/404, dan public 404 khusus belum lengkap | Audit meta dan browser state | Canonical page ditambahkan, filtered search/404 noindex, dan view 404 dibuat |
| T3-10 | Low | Legal/footer | Terms belum eksplisit membahas pembatalan dan batas tanggung jawab; footer tidak menampilkan WhatsApp saat valid | Audit copy dan footer | Copy legal dilengkapi dan WhatsApp footer memakai builder terpusat |
| T3-11 | Low | Dead config | `ADMIN_NOTIFICATION_EMAIL` dan `PUBLIC_CACHE_SECONDS` tidak dipakai | Pencarian source hanya menemukan deklarasi | Dihapus; cache kompleks tetap ditunda ke Tahap 6 |
| T3-12 | Informational | Git security | File lokal sensitif tersedia di workspace tetapi tidak tracked | `git ls-files`, `git log --all`, `git check-ignore -v` | Tidak ada perubahan histori; ignore tetap dipertahankan |

Tidak ada temuan Critical yang terbukti.

## 4. Perbaikan yang dilakukan

- Memperbaiki HTTP 500 pada detail portofolio.
- Menyamakan normalisasi rate limiter dengan normalisasi submission.
- Memperkuat duplicate submission dan cleanup attachment pada unique race.
- Menolak MIME mismatch, missing extension, executable, oversized file, dan dangerous double-extension.
- Memastikan checksum failure menghapus file yang sudah tersimpan.
- Menambah test storage failure, database failure, duplicate attachment, payload internal, limiter IP/identitas, notification recipient, dan mail failure.
- Memperbaiki active navigation, `aria-current`, focus management, mobile body lock, resize behavior, dan no-JS navigation fallback.
- Memperbaiki error summary, Bahasa Indonesia, helper text, dan `aria-describedby`.
- Melengkapi footer, legal copy, Article JSON-LD, canonical pagination, robots meta, dan custom 404.
- Menghapus konfigurasi mati yang berada di luar implementasi aktual.
- Memperluas dan menjalankan ulang visual QA.

## 5. Halaman dan route publik

| Method | Route | Nama | Controller | Status |
|---|---|---|---|---|
| GET | `/` | `home` | `HomeController` | 200 |
| GET | `/layanan` | `services.index` | `ServiceController@index` | 200 |
| GET | `/layanan/{service:slug}` | `services.show` | `ServiceController@show` | Active 200; inactive/missing 404 |
| GET | `/portofolio` | `portfolios.index` | `PortfolioController@index` | 200 |
| GET | `/portofolio/{portfolio:slug}` | `portfolios.show` | `PortfolioController@show` | Published 200; draft/missing 404 |
| GET | `/artikel` | `articles.index` | `ArticleController@index` | 200 |
| GET | `/artikel/{article:slug}` | `articles.show` | `ArticleController@show` | Published due 200; draft/future 404 |
| GET | `/faq` | `faq.index` | `FaqController` | 200 |
| GET | `/kontak` | `contact.index` | `ContactController` | 200 |
| POST | `/konsultasi` | `consultations.store` | `ConsultationController@store` | PRG redirect / 422 session errors / 429 |
| GET | `/kebijakan-privasi` | `privacy` | `LegalPageController@privacy` | 200 |
| GET | `/syarat-dan-ketentuan` | `terms` | `LegalPageController@terms` | 200 |
| GET | `/sitemap.xml` | `sitemap` | `SitemapController` | 200 XML |
| GET | `/robots.txt` | `robots` | `SitemapController@robots` | 200 text |

Route `/dashboard` dan `/admin` tetap placeholder/fondasi Tahap 2; tidak dikembangkan menjadi Tahap 4 atau 5.

## 6. Arsitektur public website

### Controller

Controller public tetap tipis: query active/published, filter/search, pagination, route-model visibility check, dan penyusunan data view. Model scope `active()` serta `published()` menjadi filter konsisten untuk service, FAQ, portfolio, article, dan testimonial.

### Action dan service

- `CreateConsultation`: fingerprint SHA-256 per hour bucket, request code, transaction, UTC deadline, policy versions, retention, duplicate response, dan cleanup.
- `PrivateConsultationAttachment`: private disk, UUID folder/name, sanitized original name, metadata, SHA-256, dan cleanup.
- `WhatsAppUrlBuilder`: nomor dari `SiteSetting`/config, normalisasi Indonesia, HTTPS, dan raw URL encoding.
- `ReadingTime`: estimasi minimum satu menit pada 200 kata/menit.
- `CodeGenerator` dan `DateTimeService`: fondasi Tahap 2 yang dipakai ulang.

### Blade layout dan reusable component

Layout `resources/views/layouts/public.blade.php` memuat SEO head, skip link, navbar, flash state, semantic main, footer, Organization/WebSite JSON-LD, Vite CSS/JS, dan Bahasa Indonesia.

Reusable component mencakup logo, navbar/mobile navigation, footer, button, form input/select/textarea/file/checkbox/error, badge, breadcrumb, cards, FAQ accordion, pagination, empty state, flash alert, WhatsApp, SEO meta, dan structured data.

## 7. Design system, animasi, accessibility, dan performance

- Warna mengikuti navy `#0B1933`, navy sekunder `#162B4D`, rose gold `#D9A18F`, gold `#D6A83D`, cream, white, charcoal, muted, success, dan error.
- Heading memakai Playfair Display; body/interface memakai Plus Jakarta Sans dari package lokal.
- Logo asli `public/images/logo.jpeg` berukuran 640×640, dengan varian WebP tanpa menimpa source.
- Sticky navbar, card, button, form, alert, pagination, legal page, empty state, footer, dan CTA konsisten.
- Animasi hanya reveal, hover lift, transition navbar/accordion, dan dekorasi ringan.
- `prefers-reduced-motion` menonaktifkan animasi/transisi; content reveal tetap terlihat jika JavaScript gagal.
- Skip link, semantic landmarks, satu H1, label eksplisit, focus visible, 44px touch target, `aria-current`, `aria-expanded`, `aria-controls`, `aria-invalid`, `aria-describedby`, `aria-live`, dan error summary tersedia.
- Menu mobile memindahkan fokus ke link pertama, mengembalikan fokus saat Escape, mengunci scroll ketika terbuka, dan menutup saat resize desktop.
- Asset build final: CSS 66.11 kB (gzip 13.56 kB), JS 93.68 kB (gzip 34.37 kB). Seluruh gambar visual QA selesai dimuat tanpa broken image.

## 8. Konsultasi dan keamanan

### Validasi

Field yang diterima: nama, email, nomor WhatsApp, active service, judul, deskripsi, deadline opsional, teknologi opsional, budget opsional, attachment opsional, privacy, academic integrity, dan honeypot.

Email dinormalisasi lowercase; nomor dinormalisasi ke `62`; deadline tanggal-only menjadi 23:59:59 Asia/Jakarta lalu disimpan UTC. Field internal seperti user, role, status, request code, fingerprint, retention, consent timestamp, versions, dan source dibuat server-side.

### Duplicate dan rate limiter

- Fingerprint hanya berupa SHA-256; tidak menyimpan PII mentah.
- Submission identik pada hour bucket yang sama mengembalikan request code existing.
- Unique constraint menjadi safety net race; file percobaan kedua dibersihkan.
- Limiter IP: 10/jam berdasarkan hash IP.
- Limiter identity: 5/jam berdasarkan hash email + nomor ternormalisasi.
- Respons 429 memakai Bahasa Indonesia.

### Honeypot

Field `website` disembunyikan dari visual/screen reader, dikeluarkan dari tab order/autofill, dan payload terisi ditolak.

### Attachment private

- Disk `local` berakar pada `storage/app/private` dengan `serve=false`.
- Folder dan physical filename memakai UUID.
- Original filename hanya metadata tersanitasi; CR/LF/path component dihapus.
- Extension, MIME, size, no-extension, double-extension berbahaya, executable, dan oversized file diuji.
- Checksum SHA-256, MIME, size, path, serta original name disimpan.
- ZIP/RAR tidak diekstrak, dipreview, atau dieksekusi.
- Database failure membersihkan file; storage failure tidak membuat record.
- Tidak ada route download attachment konsultasi untuk guest.

### Notification

Hanya admin aktif menerima notification. Database dan mail dipanggil terpisah dengan sengaja agar database notification tetap dibuat dan mail failure tidak membatalkan konsultasi. Staff, customer, dan admin nonaktif tidak menerima. Attachment tidak dikirim melalui email.

### XSS dan IDOR

Search query, title, article content, FAQ answer, portfolio/service content, metadata, dan structured data di-escape. JSON-LD memakai JSON hex flags. Draft/inactive/future content menerima 404. Consultation attachment tidak memiliki public route; consultation policy tetap admin-only.

## 9. WhatsApp, privacy, terms, dan integritas akademik

CTA hanya tampil jika nomor valid. Seluruh link memakai HTTPS, encoded message, `target="_blank"`, dan `rel="noopener noreferrer"`.

Privacy menjelaskan data, tujuan, consent, private storage, access, retention, email/WhatsApp, hak pengguna, kontak, dan version. Terms menjelaskan scope, tanggung jawab pelanggan, integritas akademik, perubahan/pembatalan, timeline/payment manual, dokumen, serta batas tanggung jawab. Versi aktif disimpan bersama consultation.

Website menolak plagiarisme, ghostwriting yang diserahkan sebagai karya mandiri, fabrikasi/manipulasi data, pemalsuan sitasi/identitas/dokumen, ujian/kuis terawasi, bypass anti-plagiarisme, pelanggaran hak cipta, dan aktivitas ilegal. Tidak ada jaminan nilai, kelulusan, publikasi, atau hasil tertentu.

## 10. SEO, sitemap, dan robots

- Title dan meta description unik per halaman.
- Canonical mempertahankan nomor page pagination.
- Search result dinyatakan `noindex,nofollow`; non-production selalu noindex.
- Open Graph, Twitter card, favicon, dan default OG image tersedia.
- Organization, WebSite, Service, Article, dan BreadcrumbList JSON-LD diuji sebagai JSON valid dengan URL absolut.
- Sitemap hanya memuat route public, active service, published portfolio, dan article published yang waktunya tidak future.
- Sitemap tidak memuat auth, dashboard, admin, project files, consultation POST, attachment, draft, inactive, atau future content.
- Robots production mengizinkan halaman publik dan menautkan sitemap; environment selain production menolak indexing.
- Custom 404 memiliki satu H1 dan `noindex,nofollow`.

## 11. Automated test

Command final:

```powershell
php artisan test
```

Hasil aktual:

- **74 passed**
- **357 assertions**
- **0 failed**
- **0 skipped**
- **Duration 5.60s**

Coverage fungsional mencakup Tahap 2 dan Tahap 3: authentication, authorization, file isolation/versioning, domain actions, seeder guard, active/published content, search/filter/pagination, future article, testimonial demo rules, FAQ, consultation, deadline UTC, consent versions, protected payload, attachment private, MIME/double-extension, checksum, cleanup, duplicate submission, rate limiter, notification, WhatsApp, sitemap, robots, XSS, structured data, accessibility markup, dan 404.

## 12. Build dan dependency audit

| Command | Hasil |
|---|---|
| `vendor\bin\pint` | Lulus; satu file diformat, tidak ada error |
| `php artisan optimize:clear` | Lulus |
| `php artisan migrate:status` | 20 migration berstatus Ran |
| `php artisan route:list` | 37 route terdaftar |
| `npm.cmd run build` | Lulus; 56 modules transformed |
| `npm.cmd audit` | 0 vulnerabilities |
| `composer validate --strict` | `composer.json` valid |
| `composer audit` | Tidak ada security advisory |
| `git diff --check` | Lulus |

## 13. Visual QA

Browser: Microsoft Edge Chromium headless melalui Chrome DevTools Protocol.
Viewport: `360x800`, `390x844`, `768x1024`, `1024x768`, `1366x768`, `1440x900`.

Hasil `docs/screenshots/visual-qa-report.json`: **passed**, 21 halaman/state, 0 horizontal overflow, 0 broken image, 0 console error, dan 0 network error.

Screenshot:

1. `tahap-3-home-desktop.png`
2. `tahap-3-home-mobile.png`
3. `tahap-3-mobile-menu-open.png`
4. `tahap-3-home-360.png`
5. `tahap-3-home-tablet-768.png`
6. `tahap-3-home-tablet-1024.png`
7. `tahap-3-home-1366.png`
8. `tahap-3-services.png`
9. `tahap-3-service-detail.png`
10. `tahap-3-portfolio.png`
11. `tahap-3-portfolio-detail.png`
12. `tahap-3-articles.png`
13. `tahap-3-article-detail.png`
14. `tahap-3-faq.png`
15. `tahap-3-contact.png`
16. `tahap-3-privacy.png`
17. `tahap-3-terms.png`
18. `tahap-3-empty-search.png`
19. `tahap-3-content-404.png`
20. `tahap-3-validation-error.png`
21. `tahap-3-success-state.png`

Pagination visual tidak muncul karena seeder menyediakan 8 service dengan page size 9. Perilaku pagination dan query-string preservation diverifikasi melalui automated test.

## 14. File dibuat dan diubah

File utama yang dibuat:

- controller pada `app/Http/Controllers/Public/`;
- `app/Actions/Consultations/CreateConsultation.php`;
- `app/Http/Requests/StoreConsultationRequest.php`;
- `app/Notifications/NewConsultationNotification.php`;
- service attachment, reading time, dan WhatsApp;
- migration `2026_07_22_020000_add_public_submission_fields_to_consultations_table.php`;
- public layout, public view, reusable components, custom 404;
- asset logo WebP/favicon/OG;
- test Tahap 3 dan `ReadingTimeTest`;
- `scripts/generate_brand_assets.php` dan `scripts/visual-qa.mjs`;
- screenshot/report visual QA;
- dokumen ini.

File utama yang diubah: `.env.example`, `README.md`, model consultation, provider, config Jokiinlah, site setting seeder, package files, CSS/JS, route web, dan `public/robots.txt` statis dihapus karena response robots kini dinamis.

Migration baru bersifat additive: academic integrity consent timestamp, attachment checksum, dan unique nullable submission fingerprint. Migration lama tidak diedit.

## 15. Risiko tersisa dan pekerjaan ditunda

- Identitas badan/pengelola, alamat korespondensi, email privacy resmi, retensi final, kontak final, SMTP, dan review legal masih harus dikonfirmasi sebelum production.
- Allowlist/MIME/checksum bukan antivirus. Malware scanning asynchronous tetap Tahap 6.
- 2FA admin/staff dan automatic purge scheduler tetap Tahap 6.
- Customer Portal lengkap tetap Tahap 4.
- Panel Filament/admin/staff final tetap Tahap 5.
- Tidak ada deployment production pada Tahap 3.

## 16. Git dan Definition of Done

Metadata release:

- Branch: `main`
- Commit utama: `628f4179b638`
- Commit message: `feat: finalize and audit public website phase`
- Push: berhasil ke `origin/main`
- Working tree final: bersih

Definition of Done terpenuhi: landing/public content, konsultasi, private attachment, notification, limiter, honeypot, WhatsApp, privacy/terms/integrity, SEO, sitemap/robots/JSON-LD, XSS/IDOR controls, accessibility, responsive UI, build, dependency audit, automated test, visual QA, commit, dan push lulus. `origin/main` menerima commit tanpa force push dan Tahap 4 tidak dimulai.
