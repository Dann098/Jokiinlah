# Tahap 7 — Audit Final dan Release Readiness

## 1. Tujuan

Tahap ini menutup fase pengembangan aplikasi Jokiinlah dengan audit berbasis bukti
lokal. Audit memverifikasi implementasi Tahap 1–6, memperbaiki hanya masalah yang
terbukti, menjalankan regresi pada SQLite dan MariaDB, serta memisahkan kesiapan
aplikasi dari kesiapan operasional server.

## 2. Scope

Scope mencakup website publik, Customer Portal, panel Filament admin/staff,
authentication, 2FA, authorization, private file, malware scanning, lifecycle data,
database, queue, scheduler, notification, security header, logging, performance smoke,
SEO, accessibility, browser QA, dependency, dokumentasi, dan release gate.

Audit tidak melakukan deployment production, tidak mengubah credential, tidak
memperbaiki data development secara otomatis, dan tidak menguji layanan eksternal
yang belum disediakan.

## 3. Kondisi awal

- Branch awal: `main`.
- Commit awal: `149a3c4` (`chore: harden security and production readiness`).
- Worktree awal bersih.
- Laravel `12.64.0`, PHP `8.2.12`, Composer `2.10.2`.
- Filament `5.7.3`, Livewire `4.3.3`.
- Node.js `24.18.0`, npm `11.16.0`.
- MariaDB `10.4.32`.
- Baseline: 119 route, 23 migration, 130 test, dan 789 assertion.
- Environment lokal sengaja memakai debug, HTTP, scanner nonaktif, dan mail log;
  readiness guard awal gagal pada enam konfigurasi production.

Git menampilkan peringatan ownership ketika dipanggil oleh Composer pada mesin audit.
Audit menggunakan opsi Git per-command `safe.directory` dan tidak mengubah konfigurasi
global pengguna.

## 4. Dokumen yang diperiksa

- `README.md`
- `.env.example`
- `docs/TAHAP-1-ANALISIS-DAN-ARSITEKTUR.md`
- `docs/TAHAP-2-DOMAIN-DAN-AUTHENTICATION.md`
- `docs/TAHAP-2-IMPLEMENTASI.md`
- `docs/TAHAP-3-PUBLIC-WEBSITE.md`
- `docs/TAHAP-4-CUSTOMER-PORTAL.md`
- `docs/TAHAP-5-ADMIN-STAFF-PANEL.md`
- `docs/TAHAP-6-HARDENING-DAN-PRODUCTION-READINESS.md`
- `docs/DEPLOYMENT.md`

`docs/CODEX-NAVIGATION-GUIDE.md` disebut oleh instruksi tooling, tetapi tidak tersedia
di repository.

## 5. Baseline command

Command baseline dan penutupan yang dijalankan:

```powershell
php artisan about
php artisan migrate:status
php artisan route:list
php artisan schedule:list
vendor\bin\pint --test
php artisan test
npm.cmd run build
composer validate --strict
composer audit
npm.cmd audit
php artisan queue:failed
php artisan jokiinlah:readiness
php artisan jokiinlah:retention-evaluate --dry-run --limit=200
php artisan jokiinlah:purge --dry-run --limit=50
php artisan jokiinlah:files-reconcile --limit=1000 --checksum
php artisan optimize
php artisan optimize:clear
git diff --check
```

## 6. Feature Traceability Matrix

| Area | Implementasi utama | Boundary keamanan | Bukti test/audit | Hasil |
|---|---|---|---|---|
| Landing dan konten publik | Public controllers, Blade, active/published scopes | Draft, future, dan inactive tidak dipublikasi | `PublicWebsiteTest`, 21 browser state | PASS |
| Form konsultasi guest | `CreateConsultation`, request validation | Consent, honeypot, rate limit, idempotency | `ConsultationSubmissionTest` | PASS |
| Registration/login/logout | Fortify actions dan response role-aware | Role server-owned, inactive ditolak, login throttled | `AuthenticationTest` | PASS |
| Verifikasi email | Fortify signed URL | Signed URL dan verified middleware | Integration test signed link | PASS |
| Reset password | Fortify broker/action | Token broker dan password policy | Integration test issued token | PASS |
| 2FA admin/staff | Fortify TOTP, recovery code, middleware panel | Mandatory untuk panel, challenge session-bound | `TwoFactorSecurityTest`, browser Tahap 6 | PASS |
| Dashboard customer | Customer controllers dan scoped query | Customer hanya data miliknya | `CustomerPortalTest` | PASS |
| Private project file | Secure upload, policy, download controller | UUID, MIME, checksum, clean scan, ownership | File/security test groups | PASS |
| Versioning file | `CreateProjectFileVersion` | Version server-generated, old file immutable | Portal/file/domain tests | PASS |
| Revisi customer | `CreateCustomerRevision` | Nested ownership dan internal field protection | `CustomerPortalRevisionTest` | PASS |
| Reminder/appointment | Scoped customer views | Hanya customer-visible dan safe meeting URL | `CustomerPortalTest` | PASS |
| Panel admin | Filament resources/actions | Policy dan admin-only capability | Admin panel/domain/content tests | PASS |
| Panel staff | Assigned-project scope | Direct URL dan child records ikut scope | Panel access/domain/widget tests | PASS |
| Konversi consultation | Transactional domain action | Customer verified/matching, double conversion ditolak | `DomainActionsTest` | PASS |
| Status/progress/payment | Domain actions | Transition, admin override reason, audit log | Domain/admin tests | PASS |
| Notification | Database/mail notification queued | Active admin, after-commit, retries | Submission/production tests | PASS |
| Malware scanning | `SecurePrivateUploadStorage`, scanner abstraction | Pending quarantine, clean-only publish, fail-closed | `MalwareUploadSecurityTest` | PASS |
| Retention/purge | Evaluator dan two-phase purger | Soft delete, eligibility, idempotent retry | Retention tests dan dry-run | PASS |
| Reconciliation | `FileReconciler` | Report-only default, explicit repair/quarantine | Reconciliation tests dan seed gate | PASS |
| SEO/accessibility | Metadata, canonical, sitemap, semantics | Non-public URL tidak masuk sitemap | Public tests dan browser diagnostics | PASS |

## 7. Audit website publik

Semua route publik utama merender dengan benar. Active/published/future scopes,
pagination, search, empty state, legal copy, WhatsApp URL, consultation validation,
404, dan output escaping telah diverifikasi. Browser rerun memeriksa 21 state dan
tidak menemukan overflow, broken asset aktual, console error, atau network error.

## 8. Audit Customer Portal

Route `/dashboard` memerlukan akun aktif, verified, dan role customer. Query,
policy, scoped nested binding, dan controller download menjaga ownership. Upload,
versioning, revision, reminder, appointment, profil, password, empty state, long
filename, dan customer-to-customer 403 diverifikasi melalui 27 browser state dan
automated tests.

## 9. Audit admin/staff

Panel `/admin` memakai policy dan query scope server-side. Admin memperoleh operasi
global sesuai policy; staff hanya assigned project dan tidak memperoleh consultation,
payment, account/content management, setting, atau global log. Evidence Tahap 5
memuat 54 state panel. Audit Tahap 7 juga mengulang login guard, onboarding 2FA,
challenge OTP, dan dashboard staff setelah OTP.

Deklarasi action Filament yang tidak memiliki route/izin create-edit ditemukan pada
beberapa resource read-only. Action tersebut inert karena policy dan route menutupnya;
tidak dihapus agar audit tidak memperluas perubahan kosmetik.

## 10. Audit authentication dan 2FA

Registration selalu menghasilkan customer aktif tetapi belum verified. Inactive user
ditolak dengan pesan generik. Login dibatasi lima percobaan per menit. Reset password
dengan token terbit dan signed email verification kini memiliki integration test.

Admin/staff wajib mengaktifkan 2FA sebelum panel, challenge OTP/recovery code
session-bound, recovery code sekali pakai, dan endpoint challenge rate-limited.
Halaman setup kini mengirim `Cache-Control: no-store, private` agar secret dan recovery
code sekali tampil tidak tersimpan oleh browser/proxy.

## 11. Audit authorization

Policy tersedia untuk seluruh model operasional. Direct URL, nested resource,
download, global search, widget, relation manager, dan child record mengikuti role
serta project scope. Test eksplisit membuktikan customer A tidak mengakses data
customer B dan unassigned staff tidak mengakses project/file.

## 12. Audit file security

File privat berada di `storage/app/private` dan tidak memiliki symlink publik.
Nama fisik/path memakai UUID, original filename hanya metadata yang disanitasi.
MIME, extension, double extension, executable, size, checksum, policy, scan status,
dan physical existence diperiksa. Archive disimpan opaque dan tidak diekstrak.
Download mengirim filename aman, `nosniff`, dan CSP sandbox.

## 13. Audit malware scanner

Upload masuk `quarantine/pending`, dipindai streaming, lalu hanya hasil `clean` yang
dipindah ke area final. `infected` dan scanner error dipindah ke quarantine dan
ditolak secara fail-closed. Automated tests membuktikan tiga status. Browser QA
memakai fake scanner non-production untuk state infected. Koneksi ClamAV nyata
tetap prerequisite server.

## 14. Audit retention dan purge

Evaluator hanya memilih soft-deleted record yang melewati `retention_until` dan
berstatus eligible. Purger menghapus fisik lebih dahulu, menyimpan state antara,
kemudian menghapus record; batch dapat dicoba ulang. Dry-run lokal menemukan nol
record dan nol kegagalan. Apply production harus diawali backup dan review dry-run.

## 15. Audit reconciliation

Reconciliation memeriksa path convention, existence, size, clean scan, purge state,
checksum, dan orphan. Default hanya melaporkan. Repair state dan quarantine orphan
memerlukan flag eksplisit.

Audit menemukan demo seeder lama membuat tiga metadata clean tanpa file fisik dan dua
path tidak sesuai. Source seeder diperbaiki untuk membuat fixture konsisten dan test
idempotensi kini mewajibkan `mismatches=0`. Database development lama masih
melaporkan lima mismatch dan sengaja tidak diubah tanpa backup.

Pada database uji bersih, tiga record seed lulus checksum dengan nol mismatch. Scan
orphan lintas seluruh storage melihat empat file milik database development karena
kedua environment memakai root storage yang sama. File tersebut tidak dihapus.
Production wajib memakai database dan root/bucket storage yang terisolasi per
environment.

## 16. Audit database dan MariaDB

- 23 migration dapat dijalankan dari database kosong.
- Full suite lulus pada SQLite dan MariaDB.
- Database MariaDB audit memakai 25 tabel InnoDB, 20 foreign key, dan 95 indeks.
- Pemeriksaan read-only tidak menemukan duplicate code/version, multiple project per
  consultation, missing customer, assignment invalid, ataupun kombinasi status lifecycle
  yang tidak sah.
- Demo seeder idempotent dan menolak berjalan pada production.
- Tidak ada migration baru pada Tahap 7.

Database sementara audit dihapus setelah nama dan scope diverifikasi. Database
development tidak di-drop, truncate, atau dimigrasi ulang.

## 17. Audit queue dan scheduler

Queue memakai database, failed job storage aktif, dan tidak ada failed job saat audit.
Tiga schedule terdaftar: retention harian, purge harian, reconciliation mingguan.
Worker process manager dan cron satu menit belum dapat diverifikasi lokal dan menjadi
prerequisite production.

## 18. Audit notification

Notification consultation dikirim kepada admin aktif, disimpan di database, memakai
queue after-commit, tiga percobaan, timeout 30 detik, dan backoff `30/120/300`.
Kegagalan mail tidak membatalkan consultation. SMTP/provider production belum
tersedia pada environment audit.

## 19. Audit security headers dan CSP

Respons mengirim `nosniff`, referrer policy, frame protection, permissions policy,
COOP, dan CSP. HSTS hanya aktif untuk request HTTPS pada environment production.
CSP tidak memakai wildcard, tetapi masih memerlukan `unsafe-inline` dan `unsafe-eval`
untuk kompatibilitas Filament/Livewire/Alpine. Ini known limitation yang harus
dikurangi melalui nonce/hash setelah kompatibel dengan stack UI.

## 20. Audit logging dan error handling

Perubahan sensitif, download, status, override, progress, password, 2FA, upload
terinfeksi, purge, dan reconciliation memiliki activity/security logging. IP disimpan
sebagai fingerprint hash. Error upload/storage gagal aman dan rollback/cleanup
dibuktikan oleh test. Log aggregation serta alert production belum dipasang.

## 21. Audit performance

Query customer/staff menggunakan scope, eager loading, pagination, aggregate, dan
indeks pada foreign key/status utama. Smoke test lima request per endpoint pada
Laravel development server mencatat median lokal sekitar 365–421 ms untuk home,
layanan, login, dan sitemap. Angka ini bukan benchmark production; load test,
APM, cache hit rate, dan slow query log harus dilakukan di staging.

## 22. Audit SEO

Title, description, canonical, robots, sitemap XML, structured data, active/published
scope, satu H1, dan noindex pada error page diverifikasi. Sitemap tidak memuat admin,
customer, draft, future, atau inactive content.

## 23. Audit accessibility

Dokumen memakai bahasa Indonesia, skip link, satu H1, label form, error association,
ARIA state untuk menu/FAQ/progress, focus return pada mobile menu, target minimum,
empty/error states, dan kontrol tanpa duplicate ID. Browser diagnostics customer
tidak menemukan kontrol tanpa label. Audit ini bukan sertifikasi WCAG penuh; screen
reader dan contrast automation dedicated tetap direkomendasikan di staging.

## 24. Hasil automated test

| Target | Hasil |
|---|---|
| SQLite | 133 passed, 799 assertions |
| MariaDB 10.4.32 | 133 passed, 799 assertions |
| Pint | PASS |
| Migration fresh + seed | PASS |
| Production cache compile | PASS |

## 25. Hasil browser QA

- Public: 21 state, PASS.
- Customer Portal: 27 state, PASS.
- Security/2FA/error: 15 state, PASS.
- Evidence panel Tahap 5: 54 state, PASS.
- Upload clean dan infected dilakukan pada database QA terisolasi.
- File QA, process QA, dan database QA dibersihkan setelah pengujian.

## 26. Hasil responsive QA

Viewport minimum yang diverifikasi: `360x800`, `390x844`, `768x1024`,
`1024x768`, `1280x720`, `1366x768`, `1440x900`, dan `1920x1080`.
Tidak ditemukan horizontal overflow. Gambar logo lazy-load di bawah fold pada
dua viewport tambahan tidak diklasifikasikan broken karena belum diminta browser.

## 27. Console dan network findings

Tidak ada runtime exception, console error, network error tak terduga, atau asset
error pada report browser. Respons 403, 404, dan 429 merupakan skenario yang
disengaja dan divalidasi.

## 28. Backup dan restore

Runbook telah mendefinisikan backup database dan private file pada titik konsisten,
enkripsi, off-site copy, RPO/RTO, serta restore ke environment terisolasi. Tidak ada
backup production atau target restore pada mesin audit, sehingga restore drill
berstatus BLOCKED dan menghalangi deployment production.

## 29. Production readiness checklist

| Area | Status | Catatan |
|---|---|---|
| Application code/config guard | PASS | Readiness command tersedia dan fail-closed |
| Environment production | BLOCKED | `.env` production belum disediakan |
| Database dan migration | PASS | Fresh MariaDB dan full suite lulus |
| HTTPS/HSTS/trusted proxy | BLOCKED | Harus dibuktikan di server |
| Cookie/session | BLOCKED | Secure cookie lokal masih false; session semantics lulus |
| Authentication | PASS | Registration, login, verify, reset, logout guard teruji |
| 2FA | BLOCKED | Implementasi PASS; enrollment akun production belum ada |
| Authorization/IDOR | PASS | Policy dan cross-tenant tests lulus |
| Private storage | BLOCKED | Implementasi PASS; permission/encryption/isolasi server belum diverifikasi |
| Malware scanner | BLOCKED | Fail-closed PASS; ClamAV nyata belum terhubung |
| Queue | BLOCKED | Config PASS; worker/process manager belum diverifikasi |
| Scheduler | BLOCKED | Schedule PASS; cron belum diverifikasi |
| Mail | BLOCKED | Masih memakai mail log lokal |
| Retention/purge/reconciliation | PASS | Test dan dry-run lulus; apply perlu backup |
| Logging/error handling | BLOCKED | Implementasi PASS; aggregation/alert server belum ada |
| Backup/restore | BLOCKED | Restore drill belum dilakukan |
| Health check | PASS | Route `/up` tersedia; monitoring eksternal belum ada |
| Dependency | PASS | Composer/npm audit bersih |
| Automated test/build | PASS | 133/799 pada dua database; Vite PASS |
| Browser/SEO/accessibility | PASS | Evidence di atas; audit manual lanjutan direkomendasikan |
| Git | PASS | Diff check bersih; hasil commit/push dilaporkan pada handoff |
| Dokumentasi | PASS | Dokumen Tahap 7, checklist, release notes, README |

Semua BLOCKED di atas adalah prerequisite operasional berprioritas tinggi dan
menghalangi deployment production, tetapi bukan blocker release candidate source.

## 30. Risiko tersisa

| ID | Risiko | Dampak | Kemungkinan | Prioritas | Mitigasi | Blocker |
|---|---|---|---|---|---|---|
| R-01 | Environment/HTTPS/cookie production belum tersedia | Session/traffic tidak aman | Tinggi | Kritis | Provision server dan wajibkan readiness PASS | Ya |
| R-02 | ClamAV nyata belum diuji | Upload production ditolak fail-closed | Tinggi | Kritis | Sediakan daemon, network ACL, smoke clean/infected/failure | Ya |
| R-03 | Worker, cron, SMTP belum diverifikasi | Notification dan maintenance tertunda | Tinggi | Tinggi | Process manager, cron, provider mail, monitoring | Ya |
| R-04 | Backup belum pernah direstore | Kehilangan data tidak dapat dipulihkan terjamin | Sedang | Kritis | Backup konsisten dan restore drill terisolasi | Ya |
| R-05 | Akun admin/staff production belum enroll 2FA | Panel tidak dapat digunakan atau akun belum aman | Tinggi | Tinggi | Enrollment dan simpan recovery code offline | Ya |
| R-06 | CSP masih memakai unsafe-inline/eval | Perlindungan XSS lebih lemah | Sedang | Sedang | Migrasi nonce/hash ketika Filament stack mendukung | Tidak |
| R-07 | Data seed development lama memiliki lima mismatch | Noise pada reconciliation lokal | Tinggi | Sedang | Backup lalu reseed/repair manual terverifikasi | Tidak |
| R-08 | Shared storage antar database QA memicu false orphan | Operator dapat salah menilai file | Sedang | Tinggi | Root/bucket unik per environment | Ya untuk deploy |
| R-09 | Belum ada staging load test/APM | Bottleneck production belum terukur | Sedang | Sedang | Load test, slow query log, APM, alert | Tidak |
| R-10 | Git ownership warning pada mesin audit | Tool Composer menampilkan warning | Tinggi | Rendah | Perbaiki ownership/safe.directory oleh pemilik mesin | Tidak |

## 31. Perbaikan yang dilakukan

1. Menambahkan `Cache-Control: no-store, private` pada halaman setup/recovery 2FA.
2. Menambahkan regression test untuk cache header sensitif.
3. Menormalkan path, physical fixture, size, checksum, dan scanned timestamp demo seed.
4. Menambahkan gate reconciliation nol mismatch pada test idempotensi seeder.
5. Menambahkan integration test password reset menggunakan token yang diterbitkan.
6. Menambahkan integration test signed email verification.

## 32. File yang dibuat atau diubah

- `app/Http/Controllers/TwoFactorSecurityController.php`
- `database/seeders/ProjectSeeder.php`
- `tests/Feature/AuthenticationTest.php`
- `tests/Feature/DatabaseSeederTest.php`
- `tests/Feature/Security/TwoFactorSecurityTest.php`
- `README.md`
- `docs/TAHAP-7-AUDIT-FINAL-DAN-RELEASE-READINESS.md`
- `docs/DEPLOYMENT-CHECKLIST.md`
- `docs/RELEASE-NOTES.md`

## 33. Migration baru

Tidak ada migration baru. Seluruh 23 migration lama lulus dari database kosong.

## 34. Hasil build

Vite `6.4.3` memproses 56 modul. Output utama:

- CSS 93.57 kB (gzip 17.67 kB).
- JavaScript 94.20 kB (gzip 34.44 kB).

## 35. Dependency audit

- `composer validate --strict`: PASS.
- `composer audit`: tidak ada advisory.
- `npm audit`: 0 vulnerability.

## 36. Commit hash

Baseline audit: `149a3c4`. Hash commit penutupan bersifat self-referential terhadap
isi dokumen ini dan dicatat pada output Git/final handoff setelah commit dibuat.

## 37. Status push

Diisi oleh hasil command release setelah dokumen, test, dan diff final lolos. Tidak
ada force push dan tidak ada GitHub Release otomatis.

## 38. Status akhir aplikasi

**STATUS B — READY WITH OPERATIONAL PREREQUISITES**

Implementasi aplikasi dan release candidate source selesai. Status ini tidak berarti
siap deployment production. Semua BLOCKED pada checklist wajib ditutup dan
`php artisan jokiinlah:readiness` wajib PASS di server target.

## 39. Penutupan tahap pengembangan

Tahap pengembangan fitur yang didefinisikan pada Tahap 1–6 dinyatakan ditutup.
Pekerjaan berikutnya adalah provisioning, staging verification, restore drill,
operational acceptance, dan deployment terkontrol; bukan penambahan fitur bisnis
tanpa scope baru.

## 40. Addendum upload gambar portofolio

Pengelolaan gambar pada resource Portfolio telah dipindahkan dari input path manual
ke `FileUpload` Filament. Thumbnail disimpan pada
`public:portfolios/thumbnails`, galeri pada `public:portfolios/gallery`, dan database
tetap menyimpan relative path. Format yang diterima dibatasi ke JPG, PNG, dan WebP
dengan ukuran maksimum 4 MB per berkas; nama fisik selalu UUID.

Resolver terpusat mempertahankan kompatibilitas untuk path public lama
`images/portfolios/...` dan prefix `storage/portfolios/...`, menolak path traversal,
URL eksternal, MIME/ekstensi berbahaya, serta memberikan fallback aman untuk file
yang hilang. Observer cleanup berjalan setelah commit, mempertahankan file yang
masih direferensikan record atau field lain, dan tidak menghapus legacy asset.
Tidak ada migration baru dan private project storage tidak diubah. Pengelolaan
gambar Article kemudian disatukan pada addendum berikutnya.

Verifikasi addendum:

- Full suite: **149 test, 920 assertion, seluruhnya PASS**.
- Vite 6.4.3: 56 modul, build production PASS.
- `composer validate --strict`, `composer audit`, dan `npm audit`: PASS, 0 advisory.
- Cache lifecycle (`config`, `route`, `view`, lalu `optimize:clear`): PASS.
- `route:list`: 119 route berhasil dimuat.
- Browser QA create/edit/list/detail/fallback: upload tiga gambar, remove menjadi
  dua, replace thumbnail, preview storage, serta fallback seluruhnya PASS.
- Viewport 360x800, 390x844, 768x1024, 1366x768, dan 1440x900: tidak ada
  horizontal overflow, broken image, console error, atau response gagal.
- Kontrol drag-and-drop FilePond tampil; persistensi urutan galeri dan batas delapan
  gambar dibuktikan oleh integration test. Gesture drag sintetis headless tidak
  dipakai sebagai satu-satunya bukti karena tidak stabil.

File utama addendum:

- `app/Services/PublicImageStorage.php`
- `app/Observers/PublicImageObserver.php`
- `app/Models/Portfolio.php`
- `app/Filament/Resources/Portfolios/Schemas/PortfolioForm.php`
- `app/Filament/Resources/Portfolios/Schemas/PortfolioInfolist.php`
- `app/Filament/Resources/Portfolios/Tables/PortfoliosTable.php`
- `resources/views/components/portfolio-card.blade.php`
- `resources/views/public/portfolios/show.blade.php`
- `tests/Feature/PortfolioImageManagementTest.php`
- `tests/Feature/PortfolioImageCleanupTest.php`

## 41. Addendum upload seluruh gambar konten publik

Audit lanjutan menemukan tiga field path manual lain pada panel: thumbnail Artikel,
gambar Layanan, dan foto Testimoni. Seluruhnya telah diganti dengan `FileUpload`
yang memakai komponen bersama `PublicImageUpload`. Field `icon` pada Layanan tetap
berupa teks karena nilainya adalah nama ikon UI, bukan path berkas. Upload dokumen
proyek dan lampiran tetap memakai storage private yang sudah tersedia.

Direktori managed:

- Artikel: `public:articles/thumbnails`
- Portfolio: `public:portfolios/thumbnails` dan `public:portfolios/gallery`
- Layanan: `public:services/images`
- Testimoni: `public:testimonials/photos`

Semua resource memakai whitelist JPG/PNG/WebP, maksimum 4 MB, nama UUID, URL
same-origin, preview admin, resolver legacy `images/...`, serta cleanup observer
setelah commit. File tidak dihapus jika masih direferensikan field atau resource
lain. Gambar yang tidak ada, berbahaya, atau berada di luar direktori managed tidak
dirender ke halaman publik.

Verifikasi addendum seluruh konten:

- Full suite: **160 test, 983 assertion, seluruhnya PASS**.
- Targeted image suite: **27 test, 184 assertion, seluruhnya PASS**.
- Browser QA Artikel/Layanan/Testimoni: tiga upload mencapai status
  `processing-complete`; tidak ada lagi label atau input path manual.
- Viewport admin 360, 390, 768, 1366, dan 1440 px: tidak ada horizontal overflow.
- Browser console dan network error: nihil.
- Tidak ada migration baru.
