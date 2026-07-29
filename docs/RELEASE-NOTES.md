# Release Notes — v1.0.0-rc.1

## Ringkasan

Jokiinlah v1.0.0-rc.1 adalah release candidate aplikasi Laravel untuk pendampingan
akademik dan pengembangan digital. Release mencakup website publik, konsultasi guest,
Customer Portal, panel operasional admin/staff, private file lifecycle, dan hardening
production.

Status: **STATUS B — READY WITH OPERATIONAL PREREQUISITES**. Source release candidate
telah lulus audit; deployment production belum boleh dilakukan sebelum checklist
operasional ditutup.

## Fitur utama

- Website publik: layanan, portofolio, artikel, FAQ, legal, SEO, sitemap, robots, dan konsultasi.
- Customer Portal: project, milestone, private file/version, revision, reminder,
  appointment, profil, password, dan WhatsApp CTA.
- Panel Filament: customer/staff, consultation, project, content, setting, activity
  log, relation manager, widget, search, notification, dan scoped operations.
- Authentication: registration customer, verification email, reset password, login
  rate limit, active-account guard, dan role-aware redirect.
- Security: mandatory TOTP 2FA untuk admin/staff, policy/IDOR protection, secure
  upload, ClamAV abstraction, CSP/header, session revocation, dan audit log.
- Lifecycle: retention evaluator, two-phase purge, reconciliation checksum/orphan,
  scheduler, readiness guard, queue retry/backoff, dan runbook.

## Perubahan audit Tahap 7

- Respons setup/recovery 2FA sekarang memakai `Cache-Control: no-store, private`.
- Demo seeder membuat physical fixture dengan path, size, checksum, dan scan timestamp
  yang konsisten; test idempotensi menolak mismatch reconciliation.
- Integration test ditambah untuk issued-token password reset dan signed email verification.
- Dokumentasi audit final, deployment checklist, release notes, dan README diperbarui.

## Keamanan

- File customer tetap berada pada private disk dan tidak boleh dihubungkan ke public.
- Upload hanya dipublikasikan ke final path setelah hasil scan `clean`.
- Infected dan scanner failure ditolak secara fail-closed.
- Admin/staff wajib 2FA; customer tidak diwajibkan.
- Cross-customer dan unassigned-staff access ditolak oleh policy, scope, dan tests.
- Dependency audit Composer dan npm bersih pada tanggal audit.

## Breaking change

Tidak ada breaking change API atau schema pada Tahap 7. Demo seeder sekarang menulis
tiga fixture kecil ke private local disk pada environment non-production. Seeder tetap
menolak berjalan saat `APP_ENV=production`.

## Migration

Tidak ada migration baru. Sebanyak 23 migration yang ada lulus fresh migration pada
MariaDB. Deployment normal memakai:

```powershell
php artisan migrate --force
```

Jangan memakai `migrate:fresh` atau demo seeder pada production.

## Environment variable

Tidak ada environment variable baru pada Tahap 7. Production tetap wajib mengisi
HTTPS URL, secure cookie, database, mail, queue/cache/session, private storage,
trusted proxy, production contact, dan konfigurasi ClamAV yang tercantum di
`.env.example`.

## Command operasional

```powershell
php artisan jokiinlah:readiness
php artisan queue:failed
php artisan schedule:list
php artisan jokiinlah:retention-evaluate --dry-run --limit=200
php artisan jokiinlah:purge --dry-run --limit=50
php artisan jokiinlah:files-reconcile --limit=1000 --checksum
```

## Hasil verifikasi

- SQLite: 133 test lulus, 799 assertion.
- MariaDB 10.4.32: 133 test lulus, 799 assertion.
- Pint: PASS.
- Vite: 56 modul, build PASS.
- Composer validate/audit: PASS, tanpa advisory.
- npm audit: 0 vulnerability.
- Production cache config/route/view: PASS.
- Browser QA rerun: 21 public + 27 customer + 15 security state, PASS.
- Responsive: delapan viewport dari `360x800` sampai `1920x1080`, PASS.

## Known limitation

- CSP masih memerlukan `unsafe-inline` dan `unsafe-eval` untuk stack Filament/Livewire/Alpine.
- Audit performance hanya smoke lokal, belum load test staging.
- Screen-reader dan automated contrast audit dedicated belum dilakukan.
- Database development hasil seed lama memiliki lima mismatch metadata/file dan harus
  diperbaiki manual setelah backup; source seeder baru sudah konsisten.
- Storage harus dipisahkan per environment agar scan orphan tidak melihat file database lain.
- Peringatan Git ownership bersifat lokal pada mesin audit dan tidak mengubah source.

## Prerequisite server

- Environment production, HTTPS/HSTS, trusted proxy, dan secure cookie.
- MariaDB/MySQL production dengan backup dan restore drill terverifikasi.
- Private filesystem/object storage terenkripsi dan terisolasi.
- ClamAV daemon serta smoke test clean/infected/failure.
- Queue worker dengan process manager dan failed-job monitoring.
- Cron scheduler satu menit, SMTP/provider mail, log aggregation, alert, APM.
- Enrollment 2FA seluruh akun admin/staff production.
- `php artisan jokiinlah:readiness` menghasilkan seluruh pemeriksaan PASS.

Ikuti [Deployment Checklist](DEPLOYMENT-CHECKLIST.md) sebelum go-live.
