# Tahap 6 — Hardening dan Production Readiness

Dokumen ini mencatat audit, implementasi, pengujian, dan batas kesiapan operasional
Tahap 6. Tahap 7 belum dimulai.

## 1. Tujuan Tahap 6

Memperkeras aplikasi yang dibangun pada Tahap 1–5 tanpa menambah fitur bisnis:
melindungi akun panel, upload privat, lifecycle data, session, HTTP response, queue,
scheduler, logging, serta konfigurasi deployment.

## 2. Baseline sebelum perubahan

Baseline di branch `main` pada commit `53db492`:

- `php artisan migrate:status`: 22 migration berstatus `Ran` setelah MariaDB lokal
  tersedia;
- `php artisan route:list`: 109 route;
- `php artisan test`: 109 test lulus, 671 assertion, 101,68 detik;
- `npm.cmd run build`: 56 modul ditransformasi dan build berhasil;
- `composer validate` dan `composer audit`: valid, tanpa advisory;
- `npm.cmd audit`: 0 vulnerability;
- working tree bersih dan `.env` di-ignore.

## 3. Matriks audit

| Area | Sebelum | Risiko | Setelah implementasi aplikasi |
|---|---|---:|---|
| 2FA panel | Belum ada | Tinggi | Wajib TOTP untuk admin/staff |
| Malware scan | Belum ada | Tinggi | Abstraction ClamAV, karantina, fail-closed |
| Retention | Legacy one-phase | Tinggi | Evaluasi dan two-phase purge |
| Reconciliation | Belum ada | Tinggi | Command report/repair/quarantine eksplisit |
| Session | Database, revocation belum konsisten | Sedang | Revocation saat password/account berubah |
| Header/CSP | Belum terpusat | Sedang | Middleware global dan CSP kompatibel |
| Queue | Sebagian sinkron | Sedang | Notification queued after commit |
| Scheduler | Belum ada maintenance security | Sedang | Tiga task terjadwal dan overlap lock |
| Config production | Manual | Sedang | `.env.example` dan readiness command |
| Authorization | Policy/scoping sudah kuat | Rendah | Diaudit ulang dan regresi tetap diuji |

## 4. Temuan security

Temuan utama adalah akses panel tanpa faktor kedua, tidak adanya keputusan fail-closed
saat scanner gagal, lifecycle penghapusan yang belum dapat dilanjutkan secara aman,
dan tidak adanya pembanding metadata database dengan private storage. Audit juga
menemukan rute lampiran revisi admin/customer perlu handler parameter terpisah.
Seluruh download tetap melewati policy dan storage privat.

## 5. Implementasi 2FA

Laravel Fortify native dipakai untuk TOTP, konfirmasi, challenge, dan recovery code.
Admin/staff aktif dan terverifikasi diarahkan ke `/keamanan/two-factor` sampai setup
terkonfirmasi. Customer tidak diwajibkan 2FA. Login panel yang telah mengaktifkan 2FA
selalu melewati challenge dengan limiter lima percobaan per menit.

Secret disimpan terenkripsi, disembunyikan dari serialisasi, dan hanya ditampilkan
saat setup belum terkonfirmasi. Recovery code hanya ditampilkan satu kali; regenerate,
disable, success, failure, dan penggunaan recovery code diaudit tanpa menyimpan code
atau secret.

## 6. Upload security

`SafePrivateUpload` digunakan lintas consultation, project file, version, dan revision.
Validasi mencakup size, extension allowlist, MIME, null byte, dangerous/double
extension, serta decode gambar. Nama final dan direktori menggunakan UUID; nama asli
hanya metadata yang disanitasi. ZIP/RAR tetap opaque dan tidak diekstrak.

## 7. Malware scanning

`MalwareScannerInterface` memiliki implementasi ClamAV TCP `INSTREAM`, fake khusus
non-production/test, dan unavailable scanner. Alur upload:

1. simpan ke `quarantine/pending`;
2. stream ke scanner dengan timeout;
3. hasil clean dipindah ke path final lalu dihitung checksum;
4. infected dipindah ke `quarantine/infected`;
5. timeout/error/disabled dipindah ke `quarantine/failed` dan ditolak.

Hanya record berstatus `clean` dapat diunduh. Log hanya memuat reason code dan
fingerprint acak, bukan isi atau path sensitif.

## 8. Retention

`jokiinlah:retention-evaluate` mencari record soft-deleted yang melewati
`retention_until`, masih `eligible`, lalu menandainya `pending`. Record aktif dan yang
belum jatuh tempo tidak berubah. Command memakai batch, cache lock, `--limit`, dan
`--dry-run`.

## 9. Two-phase purge

`jokiinlah:purge` memproses hanya state `pending`/`physical_deleted`. Fase pertama
menghapus file (missing file dianggap sudah konsisten) dan menyimpan bukti state.
Fase kedua menghapus record. Failure menyimpan reason code aman agar dapat dicoba
ulang; child project mencegah parent dipurge lebih dahulu. Command idempoten, batch,
locked, dan mendukung `--dry-run`.

## 10. Reconciliation

`jokiinlah:files-reconcile` membandingkan path convention, keberadaan file, size,
scan status, purge state, dan checksum opsional. Orphan hanya dilaporkan secara
default. `--repair-state` terbatas pada state yang dapat dibuktikan dari file hilang;
`--quarantine-orphans` memindah orphan untuk review dan tidak menghapusnya.

## 11. Security headers

Response web menerima `X-Content-Type-Options`, `Referrer-Policy`,
`X-Frame-Options`, `Permissions-Policy`, dan `Cross-Origin-Opener-Policy`. HSTS hanya
dikirim ketika environment production dan request HTTPS. Trusted proxy berasal dari
allowlist `TRUSTED_PROXIES`, bukan wildcard.

Download menyetel filename ter sanitasi, `nosniff`, dan CSP sandbox. Middleware
mempertahankan CSP download yang lebih ketat.

## 12. CSP

CSP default membatasi sumber ke same-origin, menolak object, membatasi base/form dan
frame ancestor, dan tidak memakai wildcard. `unsafe-inline` pada script/style serta
`unsafe-eval` pada script masih diperlukan oleh Blade/Alpine/Livewire/Filament saat
ini. QA dengan browser membuktikan penghapusan `unsafe-eval` mematahkan evaluasi
ekspresi Alpine pada Customer Portal. Ini adalah kelonggaran tersisa yang harus
dipersempit dengan build Alpine CSP serta nonce/hash pada iterasi hardening berikutnya.
Mode report-only dapat diaktifkan sementara melalui environment.

## 13. Session dan cookie

Session database tetap digunakan dan `.env.example` mengaktifkan encryption,
HTTP-only, serta SameSite Lax. Production wajib mengaktifkan secure cookie melalui
HTTPS. Perubahan/reset password dan deaktivasi/unverification akun menghapus session
lain yang relevan. Login melakukan regenerasi session melalui alur Fortify.

## 14. Authorization review

Policy, owner scope, assigned-staff scope, nested scoped binding, download, soft
delete, dan direct URL diaudit ulang. Customer tetap hanya mengakses proyek miliknya;
staff hanya assigned project; consultation admin-only; activity log read-only.
Lampiran infected/pending ditolak meskipun policy mengizinkan record.

## 15. Queue dan scheduler

Queue database dan failed-job storage digunakan; `after_commit` dikonfigurasi.
Scheduler mendaftarkan:

- retention evaluation harian;
- purge harian;
- reconciliation mingguan.

Semua memakai `withoutOverlapping()` dan `onOneServer()`. Server wajib menjalankan
`schedule:run` tiap menit dan queue worker melalui process manager.

## 16. Notification hardening

Notification konsultasi mengimplementasikan `ShouldQueue`, dispatch after commit,
timeout 30 detik, tiga kali percobaan, dan backoff 30/120/300 detik. Payload mail dan
database hanya memuat identifier bisnis yang diperlukan.

## 17. Logging dan error handling

Event 2FA, infected/failed scan, retention, purge, reconciliation, dan private download
dicatat tanpa credential, OTP, recovery code, file content, atau raw private path.
Halaman 419, 423, 429, dan 500 memberi pesan aman tanpa stack trace. Exception upload
berbahaya dikonversi menjadi validation response aman.

## 18. Cache dan performance

Command maintenance memakai batch/limit dan lock. Checksum penuh bersifat opsional
karena mahal. Production menggunakan config/route/view cache setelah environment final
tersedia. Tidak ada cache authorization lintas pengguna atau cache file privat.

## 19. Production configuration

`.env.example` dikelompokkan untuk application, database, session, cache, queue, mail,
private filesystem, object storage, malware scanner, proxy/header, logging, dan
integrasi. Tidak ada secret nyata. `jokiinlah:readiness` gagal bila environment/debug,
HTTPS URL, secure cookie, queue/failed jobs, scanner, private disk, atau mailer minimum
belum aman.

## 20. Backup dan restore readiness

Production harus membackup database dan private files pada titik konsisten, terenkripsi,
off-site/beda failure domain, memiliki retention, monitoring, serta credential terpisah.
Restore drill wajib dilakukan berkala ke environment terisolasi dan memvalidasi
checksum, policy download, migrasi, serta login 2FA. Target RPO/RTO ditentukan operator
berdasarkan kebutuhan bisnis dan dibuktikan dengan durasi restore aktual.

## 21. Automated test

Test Tahap 6 berada di `tests/Feature/Security` dan mencakup:

- kewajiban 2FA role panel, customer tanpa kewajiban, TOTP, failure, limiter, recovery;
- clean/infected/unavailable scanner dan karantina fail-closed;
- retention dry-run/apply, purge idempoten, reconciliation/checksum/orphan/repair;
- security headers, HSTS, cookie, CSP, scheduler, readiness, dan notification queue.

Suite lama tetap mencakup allowlist, MIME mismatch, archive opaque, private download,
IDOR, inactive account, Filament staff scoping, demo seeder guard, dan private
storage. Hasil final diisi pada bagian 29.

## 22. Visual QA

Browser QA Tahap 6 menargetkan login admin/staff/customer, setup/challenge/recovery
2FA, upload/validation, panel Filament, serta halaman error aman. Viewport wajib:
`360x800`, `390x844`, `768x1024`, `1024x768`, `1366x768`, dan `1440x900`.
Hasil mesin dan screenshot disimpan di `docs/screenshots/tahap-6`.

## 23. File yang dibuat atau diubah

Kelompok perubahan utama:

- 2FA: controller, middleware panel, response, listener, Fortify provider/config,
  model/factory user, dan dua view auth;
- file security: contract/value object/scanner, centralized upload storage, rule,
  download controller, action, dan relation manager;
- lifecycle: enum, evaluator, purger, reconciler, empat Artisan command, scheduler;
- platform: security/trusted-proxy middleware, session service, notification,
  bootstrap, filesystem/queue/CORS/security config, error views;
- test: empat suite `tests/Feature/Security` dan penyesuaian dua regression test;
- operasional: `.env.example`, `README.md`, `docs/DEPLOYMENT.md`, dan dokumen ini.

## 24. Migration baru

`2026_07_30_000100_add_security_and_lifecycle_fields.php` menambahkan field native
Fortify 2FA, metadata scan, state/timestamp/failure purge, dan index evaluasi retention.
Migration bersifat additive dan backfill metadata file lama sebagai clean untuk
menjaga kompatibilitas data yang telah dipercaya sebelum Tahap 6.

## 25. Command operasional

| Command | Fungsi dan opsi | Repeat/dry-run | Operator/risiko |
|---|---|---|---|
| `jokiinlah:retention-evaluate --limit=100 [--dry-run]` | eligible → pending | Idempoten; ya | Operator; menambah kandidat purge |
| `jokiinlah:purge --limit=50 [--dry-run]` | hapus fisik lalu record | Idempoten; ya | Operator senior; destructive pada mode apply |
| `jokiinlah:files-reconcile --limit=500 [--checksum] [--repair-state] [--quarantine-orphans]` | audit DB/storage | Aman ulang; default report-only | Operator; repair/quarantine eksplisit |
| `jokiinlah:readiness` | guard config minimum | Aman ulang; tidak relevan | Deploy operator; tidak mengubah state |
| `schedule:list` | inspeksi jadwal | Aman ulang | Operator |
| `queue:work --queue=default --sleep=3 --tries=3 --timeout=90` | proses job | Long-running | Process manager, bukan request web |

Output maintenance berupa tabel count tanpa nama/path file sensitif. Jalankan dry-run
dan review log sebelum apply pertama.

## 26. Environment variable baru

`SESSION_ENCRYPT`, `SESSION_SECURE_COOKIE`, `SESSION_HTTP_ONLY`,
`SESSION_SAME_SITE`, `QUEUE_AFTER_COMMIT`, `QUEUE_FAILED_DRIVER`,
`PRIVATE_FILESYSTEM_DISK`, `MALWARE_SCANNER_ENABLED`,
`MALWARE_SCANNER_DRIVER/HOST/PORT/TIMEOUT`, `TRUSTED_PROXIES`,
`CORS_ALLOWED_ORIGINS`, `SECURITY_HSTS_MAX_AGE`, `CSP_REPORT_ONLY`,
`LOG_DAILY_DAYS`, dan credential object storage yang sudah disediakan template.
Semua nilai contoh non-secret. `MALWARE_SCANNER_FAKE_STATUS` sengaja tidak ditaruh
di template production; variabel itu hanya dipakai harness QA dengan driver fake
yang ditolak pada environment production.

## 27. Risiko tersisa

- CSP masih memerlukan `unsafe-inline` dan `unsafe-eval` untuk kompatibilitas
  Alpine/Livewire/Filament;
- backfill file lama mempercayai status historis dan sebaiknya dipindai ulang offline;
- availability ClamAV, queue, cron, mail, storage, dan backup tidak dapat dibuktikan
  dari development machine;
- rotasi key/credential, WAF/rate limit edge, log aggregation, alerting, RPO/RTO,
  capacity, serta restore drill adalah tanggung jawab environment production.

## 28. Hal yang masih membutuhkan konfigurasi server

HTTPS/TLS dan redirect, trusted proxy spesifik, secure cookie, ClamAV terisolasi,
private local/object storage, IAM/encryption/lifecycle, queue worker dan monitor failed
jobs, scheduler cron, SMTP transactional, log aggregation/alert, backup terenkripsi,
restore drill, secret manager, serta aktivasi 2FA setiap admin/staff.

**Implementasi aplikasi selesai, tetapi deployment production masih membutuhkan
konfigurasi ClamAV, HTTPS, queue worker, scheduler, backup, dan environment
production. Aplikasi belum production-ready sampai seluruh bukti operasional tersebut
lulus.**

## 29. Hasil test, build, dan audit

Hasil final sebelum commit:

- Pint: lulus tanpa perubahan tersisa;
- test: 130 test lulus, 789 assertion, 56,89 detik;
- route/migration/schedule: 119 route; 23 migration `Ran`; tiga task maintenance
  terdaftar;
- Vite production build: berhasil, 56 modul ditransformasi dalam 1,40 detik;
- Composer validate/audit: `composer.json` valid dan tidak ada security advisory;
- npm audit: 0 vulnerability;
- config/route/view cache: ketiganya berhasil, lalu `optimize:clear` berhasil;
- readiness lokal: sengaja gagal pada enam guard (environment, debug, HTTPS URL,
  secure cookie, scanner, dan mail), sehingga deployment production ditunda;
- visual QA: 15 state, enam viewport, 0 overflow, 0 console error, 0 network error,
  dan 0 asset error.

## 30. Commit hash

Commit menggunakan pesan wajib `chore: harden security and production readiness`.
SHA final dicatat pada laporan akhir karena SHA sebuah commit tidak dapat dimasukkan
ke isi commit yang sama tanpa mengubah SHA tersebut.

## 31. Status push

Status push ke `origin/main` dicatat pada laporan akhir setelah push benar-benar
selesai. Dokumen tidak mengklaim push sebelum operasi tersebut terjadi.

## 32. Batas tahap

Tahap 6 hanya mengubah hardening dan readiness. Tidak ada fitur bisnis Tahap 7 yang
dimulai atau diimplementasikan.
