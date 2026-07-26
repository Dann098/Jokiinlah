# Penutupan Tahap 5 — Panel Admin dan Staff

## 1. Judul

Implementasi dan audit keamanan Panel Admin dan Staff Jokiinlah pada `/admin`.

## 2. Status

SELESAI DAN TERVERIFIKASI. Bukti Git yang hanya tersedia setelah dokumen ini masuk commit dicatat pada laporan handoff final, karena sebuah commit tidak dapat memuat SHA finalnya sendiri.

## 3. Tanggal

27 Juli 2026, zona waktu Asia/Jakarta.

## 4. Scope

Filament panel, autentikasi admin/staff, resource operasional, dashboard per role, relation manager proyek, pembayaran manual, konten, pengaturan whitelist, activity log read-only, test, visual QA, dokumentasi, commit, dan push.

## 5. Out-of-scope

2FA, malware scanner, purge scheduler, object storage production, full CSP, invoice, payment gateway, quotation, chat, WebSocket, CRM kompleks, dan deployment production. Semuanya tetap di luar Tahap 5.

## 6. Baseline Tahap 4

Baseline Git `e2c25867512807f4efca50d915b74932e8f10254`, Laravel 12.64, PHP 8.2.12, 94 test dan 519 assertion, website publik dan Customer Portal lulus.

## 7. Audit awal

Audit mencakup model, enum, migration, policy, middleware, action, service, controller, notification, route, seeder, test, private storage, package, dan dokumentasi aktual. Worktree awal bersih dan branch aktif `main`.

## 8. Temuan

| ID | Severity | Area | Temuan | Tindakan |
|---|---|---|---|---|
| T5-01 | Tinggi | Panel | `/admin` masih placeholder | Diganti panel Filament nyata |
| T5-02 | Tinggi | Staff scope | Belum ada query scope panel | Scope resource, widget, search, dan child diterapkan |
| T5-03 | Tinggi | Generated CRUD | Ada force-delete/bulk/kolom storage | Seluruh aksi/kolom berbahaya dihapus |
| T5-04 | Tinggi | File upload | Policy create membutuhkan parent | Action diberi konteks parent dan dicek ulang |
| T5-05 | Sedang | Internal data | Visibility/internal note belum lengkap | Migration, policy, query, dan UI dipisah |
| T5-06 | Sedang | Payment | Enum memakai nama Inggris | Dipetakan ke `belum_dibayar`, `dp`, `lunas` |
| T5-07 | Sedang | Audit privacy | IP log disimpan mentah | IP menjadi fingerprint SHA-256 |
| T5-08 | Sedang | Pagination | Livewire dapat mengganti view global | View pagination publik dipilih eksplisit |
| T5-09 | Rendah | Runtime | `ext-intl` lokal belum aktif | `php_intl.dll` diaktifkan lokal, bukan di Git |

## 9. Keputusan arsitektur

Resource/Page menangani presentasi, action class menangani transisi penting, policy dan scoped query memberi authorization berlapis, observer mengaudit CRUD sederhana, dan service private storage existing menangani berkas. Hidden UI bukan satu-satunya kontrol.

## 10. Instalasi dan versi Filament

Dependency langsung `filament/filament ~5.0` ditambahkan tanpa broad upgrade. Versi terkunci: Filament `v5.7.3` dan Livewire `v4.3.3`. Aset runtime dipublikasikan ke `public/css`, `public/js`, dan `public/fonts`.

## 11. Panel configuration

`AdminPanelProvider` mengatur ID/path `admin`, brand, warna navy/gold, login, password reset, global-search opt-in, database notification, sidebar collapsible, empat navigation group, dua widget, dan middleware panel.

## 12. Authentication

Guest diarahkan ke login. `User::canAccessPanel()` hanya menerima admin/staff aktif dan verified. Customer, inactive, dan unverified ditolak. Tidak ada public registration Filament. Logout tersedia.

## 13. Navigation

Admin melihat Operasional, Pengguna, Konten Publik, dan Sistem. Staff hanya dashboard dan Proyek. URL resource tersembunyi tetap menghasilkan 403.

## 14. Role

Role server-side. Customer/Staff Resource tidak menyediakan role/password. Akun terkelola mendapat password acak 48 karakter, reset link, dan verification notification; plaintext tidak disimpan/dicatat.

## 15. Policy

Policy adalah batas utama. `forceDelete()` Consultation, Project, ProjectFile, dan Revision selalu `false`. ActivityLog immutable. SiteSetting tidak dapat dibuat/dihapus dari panel.

## 16. Staff scoping

Staff hanya membuka project dengan `assigned_staff_id` miliknya. Scope yang sama berlaku untuk relation manager, widget, global search, direct URL, download, dan mutation. Foreign/unassigned project menghasilkan 404.

## 17. Global search security

Global search memakai resource opt-in. Resource admin-only tidak terdaftar untuk staff. Project search memakai query Resource yang sudah di-scope.

## 18. Dashboard admin

Admin melihat proyek aktif, revisi terbuka, tenggat tujuh hari, konsultasi baru, project belum ditugaskan, payment terbuka, dan jadwal lintas project.

## 19. Dashboard staff

Staff hanya melihat proyek, revisi, tenggat, dan appointment assigned project. Tidak ada agregat consultation atau payment.

## 20. Customer Resource

Admin-only CRUD terbatas untuk identitas, institusi/program studi, dan status aktif. Role/password/verifikasi server-owned. Perubahan dicatat dan self-delete admin dilindungi.

## 21. Staff Resource

Admin-only. Role selalu `staff`; tidak ada password/role field. Akun baru memakai reset password dan email verification. Staff tidak dapat membuka resource.

## 22. Consultation Resource

Admin-only list/detail/follow-up. Attachment private dan path/checksum tidak tampil. Linking hanya customer aktif, verified, email sama. Conversion hanya reviewed + linked + belum punya project.

## 23. Project Resource

Admin membuat project manual; code/status/progress/payment/retention server-generated. Staff melihat assigned project dan action kerja yang diizinkan, tanpa mengubah customer, assignment, detail admin, atau payment.

## 24. Status transition

Transisi normal didefinisikan enum. Staff hanya transisi normal. Admin override membutuhkan alasan. Semua perubahan dicatat.

## 25. Progress

Progress 0–100, hanya admin/assigned staff, transaction-safe, dan masuk activity log.

## 26. Payment status manual

Nilai tepat `belum_dibayar`, `dp`, `lunas`. Hanya admin melihat/mengubah. Tidak ada invoice atau payment-gateway route.

## 27. Milestone Relation Manager

Admin/assigned staff mengelola milestone parent yang sah. `project_id` tidak berasal dari form. Internal note tidak muncul di Customer Portal.

## 28. File Relation Manager

Admin/assigned staff upload/download/version melalui validator, private storage, dan action existing. Path/stored name/UUID/checksum tidak tampil. Staff tidak delete; tidak ada force-delete/restore/associate/dissociate/bulk.

## 29. Revision Relation Manager

Revisi customer tidak dibuat/dihapus di panel. Admin/assigned staff menanggapi melalui transition action. Attachment private dan internal note tidak tampil ke customer.

## 30. Reminder Relation Manager

Parent/customer/creator server-owned. Reminder dapat public-targeted atau internal. Customer query hanya public-targeted.

## 31. Appointment Relation Manager

Parent/customer/staff berasal dari project. Meeting link hanya aktif untuk HTTPS. Public note dan internal note dipisah.

## 32. Service Resource

Admin-only CRUD, unique slug, enum category, active flag, JSON tags, sort order, dan path asset tervalidasi.

## 33. Portfolio Resource

Admin-only CRUD, publish/demo flag, unique slug, technology/gallery terstruktur, dan path asset tervalidasi.

## 34. Article Resource

Admin-only CRUD. Author server-generated. Draft, unpublished, dan future article tidak tampil publik.

## 35. Testimonial Resource

Admin-only CRUD, rating 1–5, publish/demo flag. Seeder hanya data demo; production tidak menampilkan demo.

## 36. FAQ Resource

Admin-only CRUD dengan service opsional, sort order, kategori, active flag. Publik hanya FAQ aktif.

## 37. Site Setting Resource

Admin hanya mengubah lima key whitelist. Key/type/group/public flag tidak dapat diinjeksi. WhatsApp/email/panjang dan key secret divalidasi action.

## 38. Activity Log Resource

Admin-only read-only index/detail. Tidak ada create/edit/delete/bulk route/action. User-agent/IP tidak ditampilkan.

## 39. Notification

Panel menampilkan database notification penerima. Consultation hanya ke admin aktif. Undangan akun memakai reset/verify. Test memastikan notification tidak bocor antaruser.

## 40. Private file security

Disk local private, UUID path/name, SHA-256 checksum, MIME/extension/size validation, double-extension/executable ditolak, archive tidak diekstrak, failure cleanup aman, soft-deleted tidak diakses.

## 41. Mass assignment

Role, password, project code, parent ID, version, checksum, payment, dan actor server-owned melalui guarded field/action whitelist.

## 42. Timezone

Database UTC; input/tampilan operasional memakai `config('jokiinlah.display_timezone')`, default Asia/Jakarta.

## 43. Accessibility

QA memeriksa satu H1, duplicate ID, label control, broken image, modal, serta navigasi responsif. Tidak ada unlabeled visible control.

## 44. Responsive behavior

Lulus pada 360×800, 390×844, 768×1024, 1024×768, 1366×768, dan 1440×900. Tidak ada horizontal overflow.

## 45. Performance

Resource/widget eager-load relasi, table dipaginasi, dashboard appointment dibatasi lima, polling notification dimatikan, dan widget penting non-lazy.

## 46. Migration

Migration `2026_07_27_010000_add_admin_panel_operational_fields.php` non-destruktif dan memetakan enum payment lama. Semua 22 migration `Ran`; tidak ada `migrate:fresh`.

## 47. Seeder

Seeder idempotent/production-guarded menyediakan fixture new/reviewed/converted, assigned/unassigned, child/payment state, draft/future, active/inactive, dan hanya testimonial demo.

## 48. Automated test

Empat test Tahap 5 memeriksa panel access, resource URL, cross-staff IDOR, domain action, content/settings/log, widget Livewire, notification ownership, dan private file. Regression Tahap 1–4 tetap berjalan.

## 49. Jumlah test

109 test: 109 passed, 0 failed, 0 skipped. Durasi final 65.68 detik.

## 50. Jumlah assertion

671 assertion, seluruhnya lulus.

## 51. Build

`npm.cmd run build` lulus; Vite 6.4.3 memproses 56 modul.

## 52. Composer audit

`composer validate --strict` valid. `composer audit`: tidak ada security advisory.

## 53. npm audit

`npm.cmd audit`: 0 vulnerability.

## 54. Visual QA

`scripts/visual-qa-admin-staff.mjs` menjalankan tepat 54 state admin/staff. Status `passed`; console error 0 dan unexpected network error 0.

## 55. Screenshot

54 PNG dan report JSON ada di `docs/screenshots/tahap-5/`; tidak memuat password, token, private path, credential, attachment sensitif, atau `.env`.

## 56. File dibuat

Panel/provider, 11 resource, lima relation manager, dua widget, action domain, observer, migration, test, visual harness, aset Filament, screenshot/report, dan dokumen ini.

## 57. File diubah

Enum, model, policy, action/service existing, provider, login response, route, config, seeder, Blade navigation/pagination, Composer files, README, dan regression test.

## 58. Package ditambah

Direct dependency `filament/filament ~5.0`; Livewire transitive final `v4.3.3`.

## 59. Masalah

Generated destructive action/kolom privat, ext-intl lokal, parent-aware file policy, lazy widget untuk headless QA, dan default pagination Livewire.

## 60. Solusi

Resource di-hardening, ext-intl diaktifkan lokal, action file memakai parent context, widget penting non-lazy, dan pagination publik memilih view eksplisit.

## 61. Risiko tersisa

Email bergantung transport environment. Antivirus, purge scheduler, object storage, dan full CSP belum tersedia. Fixture demo wajib dihapus sebelum production.

## 62. Pekerjaan Tahap 6

Belum dimulai: 2FA, malware scanning, purge scheduler, object storage production, CSP penuh, dan deployment hardening.

## 63. Commit hash

SHA final tidak dapat ditulis ke commit yang sama tanpa mengubah SHA. Nilai `git rev-parse HEAD` final dicatat faktual pada handoff setelah commit.

## 64. Push status

Push `origin main` dilakukan/diverifikasi setelah commit dokumen ini; hasil faktual dicatat pada handoff, bukan diprediksi dalam commit.

## 65. Working tree

Target penutupan: branch `main`, upstream `origin/main`, working tree bersih setelah push. Bukti pascacommit dicatat pada handoff.

## 66. Definition of Done

- Panel nyata, auth/role, navigation, scope, search, widget, notification, dan child authorization selesai.
- Admin seluruh project; staff hanya assigned dan tanpa consultation/payment.
- Conversion, status/progress/assignment/payment, lima relation manager, dan private file aman.
- Content, setting whitelist, activity log read-only, public site, dan Customer Portal lulus.
- 109 test/671 assertion, Pint, Vite, Composer, npm audit, dan visual QA 54 state lulus.
- File sensitif tidak masuk Git dan Tahap 6 belum dimulai.
