# Tahap 4 — Customer Portal

## Status rilis

- Status: selesai dan terverifikasi
- Tanggal verifikasi: 27 Juli 2026 (Asia/Jakarta)
- Scope: portal customer terautentikasi
- Di luar scope: panel operasional admin/staff Tahap 5 dan hardening infrastruktur Tahap 6
- Basis: arsitektur Tahap 1, domain/authentication Tahap 2, dan website publik Tahap 3
- Commit implementasi: `e2c25867512807f4efca50d915b74932e8f10254`
- Status push implementasi: berhasil ke `origin/main`

## Ringkasan hasil

Tahap 4 mengganti halaman dashboard placeholder dengan portal customer yang utuh:

- ringkasan proyek dan aktivitas customer;
- daftar proyek dengan pencarian, filter status, pagination, dan empty state;
- detail proyek, status, progress, timeline milestone, dan kontak WhatsApp;
- private project file, riwayat versi, upload, dan download terotorisasi;
- pengajuan revisi dengan lampiran private;
- pengingat dan jadwal konsultasi milik customer;
- pengelolaan profil dan password;
- layout customer responsif dan accessible.

Customer hanya dapat membaca serta memutasi resource miliknya. Portal tidak menyediakan
route untuk mengubah status/progress proyek, menghapus file/revisi, atau melakukan aksi
operasional admin/staff.

## Audit awal dan keputusan arsitektur

Audit sebelum perubahan mengonfirmasi fondasi berikut sudah tersedia dan dipertahankan:

- Laravel 12.64 pada PHP 8.2.12;
- database development MariaDB/MySQL `jokiinlah_dev`;
- autentikasi, role middleware, active-user middleware, dan email verification;
- model/policy domain proyek, milestone, file, revisi, reminder, appointment, notification,
  dan activity log;
- local private disk untuk dokumen;
- baseline 74 test dengan 357 assertion;
- build Vite, Composer validation/audit, dan npm audit lulus.

Temuan utama:

1. `/dashboard` masih berupa placeholder.
2. Belum ada portal customer yang mengagregasi data dengan scope pemilik.
3. Upload versi lama masih perlu diseragamkan dengan validasi dan penyimpanan private.
4. Lampiran revisi belum mempunyai checksum.
5. Policy reminder masih memberi aksi update/delete kepada customer.
6. Redirect login customer masih menunjuk nama route placeholder.
7. Belum ada UI dan visual regression QA untuk alur customer.

Solusi:

- membangun lapisan controller/request/action/service khusus customer;
- memakai policy, scoped nested binding, dan query ownership secara berlapis;
- menyatukan penyimpanan file dalam `PrivateProjectFileStorage`;
- membatasi mutation route dengan limiter bernama;
- menambah migration checksum lampiran revisi;
- membangun layout dan komponen Blade customer;
- menambah test feature keamanan dan harness visual QA browser.

## Struktur portal

```text
app/
├── Actions/
│   ├── Revisions/CreateCustomerRevision.php
│   └── Users/{UpdateCustomerPassword,UpdateCustomerProfile}.php
├── Http/
│   ├── Controllers/Customer/
│   │   ├── AppointmentController.php
│   │   ├── DashboardController.php
│   │   ├── ProfileController.php
│   │   ├── ProjectController.php
│   │   ├── ProjectFileController.php
│   │   ├── ProjectFileDownloadController.php
│   │   ├── ProjectFileVersionController.php
│   │   ├── ReminderController.php
│   │   └── RevisionController.php
│   └── Requests/
│       ├── StoreProjectFileRequest.php
│       ├── StoreProjectFileVersionRequest.php
│       ├── StoreRevisionRequest.php
│       ├── UpdateCustomerPasswordRequest.php
│       └── UpdateCustomerProfileRequest.php
├── Rules/SafePrivateUpload.php
└── Services/PrivateProjectFileStorage.php

resources/views/
├── components/customer/
├── customer/
│   ├── appointments/index.blade.php
│   ├── profile/edit.blade.php
│   ├── projects/{index,show,files}.blade.php
│   ├── reminders/index.blade.php
│   ├── revisions/{index,show}.blade.php
│   └── dashboard.blade.php
├── errors/403.blade.php
└── layouts/customer.blade.php
```

## Route dan middleware

Semua route customer berada di prefix `/dashboard`, namespace nama `customer.*`, dan
middleware:

```text
auth → active → verified → role:customer → scopeBindings
```

| Method | URI | Route name | Fungsi |
|---|---|---|---|
| GET | `/dashboard` | `customer.dashboard` | Ringkasan |
| GET | `/dashboard/proyek` | `customer.projects.index` | Daftar proyek |
| GET | `/dashboard/proyek/{project}` | `customer.projects.show` | Detail proyek |
| GET/POST | `/dashboard/proyek/{project}/file` | `customer.projects.files.*` | Daftar/upload file |
| GET | `/dashboard/proyek/{project}/file/{projectFile}/download` | `customer.projects.files.download` | Download private |
| POST | `/dashboard/proyek/{project}/file/{projectFile}/versi` | `customer.projects.files.versions.store` | Upload versi |
| GET/POST | `/dashboard/proyek/{project}/revisi` | `customer.projects.revisions.*` | Daftar/kirim revisi |
| GET | `/dashboard/proyek/{project}/revisi/{revision}` | `customer.projects.revisions.show` | Detail revisi |
| GET | `/dashboard/proyek/{project}/revisi/{revision}/lampiran` | `customer.projects.revisions.attachment` | Download lampiran |
| GET | `/dashboard/pengingat` | `customer.reminders.index` | Daftar pengingat |
| GET | `/dashboard/jadwal` | `customer.appointments.index` | Daftar jadwal |
| GET/PATCH | `/dashboard/profil` | `customer.profile.*` | Lihat/perbarui profil |
| PUT | `/dashboard/password` | `customer.password.update` | Ganti password |

Mutation customer memakai limiter `customer-mutations` sebanyak 12 request/menit,
dikunci dengan hash email terautentikasi dan alamat IP. Download memakai batas
30 request/menit. Semua form mutation memiliki proteksi CSRF bawaan Laravel.

## Authorization dan isolasi data

- `ProjectPolicy`, `ProjectFilePolicy`, `RevisionPolicy`, `ReminderPolicy`, dan
  `AppointmentPolicy` menjadi batas authorization.
- Controller selalu memulai query dari relasi user/project yang terautentikasi.
- Nested implicit binding memakai `scopeBindings`, sehingga child resource harus benar-benar
  berada di parent pada URL.
- Akses proyek user lain menghasilkan HTTP 403.
- File, versi, revisi, lampiran, reminder, appointment, dan notification tidak bocor antar-user.
- Internal notes tidak dirender pada portal.
- Customer tidak lagi mendapat hak update/delete reminder.
- Tidak ada mass assignment untuk `user_id`, `project_id`, status, priority, path, checksum,
  uploader, atau nomor versi; nilai tersebut ditentukan server.

## Dashboard dan proyek

Dashboard menampilkan:

- jumlah proyek aktif;
- proyek yang menunggu data;
- proyek dalam customer review;
- proyek selesai;
- proyek terbaru;
- milestone mendatang;
- notifikasi belum dibaca;
- CTA ke proyek dan WhatsApp.

Daftar proyek mendukung pencarian server-side pada kode/judul, filter status yang
di-whitelist, pagination dengan query string, status badge, progress bar, dan empty state.
Output Blade tetap escaped sehingga query atau judul berbahaya tidak dieksekusi.

Detail proyek menampilkan data customer-safe:

- kode, judul, layanan, status, progress, tanggal, dan deskripsi;
- timeline milestone berurutan;
- tautan ke file dan revisi;
- CTA WhatsApp dengan pesan kontekstual yang di-URL-encode.

Status dan progress bersifat read-only untuk customer.

## Private file dan versioning

Upload file dan versi menggunakan `SafePrivateUpload` serta
`PrivateProjectFileStorage`:

- storage disk `local` (`storage/app/private`), tanpa public URL;
- physical directory dan filename berupa UUID;
- original filename hanya disimpan sebagai metadata yang telah disanitasi;
- checksum SHA-256 dihitung setelah file tersimpan;
- kategori diambil dari whitelist konfigurasi;
- extension, MIME, ukuran maksimum, file tanpa extension, dan dangerous
  intermediate/double extension divalidasi;
- arsip ZIP/RAR diperlakukan sebagai opaque attachment dan tidak diekstrak;
- kegagalan checksum/database membersihkan file yang sudah sempat tersimpan;
- download memakai policy dan streamed response;
- version number dihitung di dalam transaksi dan dijaga unique constraint;
- version history ditampilkan per document UUID.

Model `ProjectFile` melindungi field identitas, storage, checksum, uploader, dan nomor versi
dari mass assignment. Implementasi route legacy Tahap 2 juga memakai rule dan storage
service yang sama.

## Revisi

Customer dapat mengirim revisi berisi judul, deskripsi, dan lampiran opsional. Server
menentukan:

- project dan user;
- status awal;
- priority default;
- storage path, nama metadata, MIME, ukuran, dan checksum lampiran.

Pembuatan revisi berjalan dalam transaksi, menghasilkan activity log, dan membersihkan
lampiran jika transaksi gagal. Detail serta download lampiran dilindungi ownership policy
dan nested binding. Customer tidak dapat menyuntikkan status, priority, atau project ID.

## Reminder, appointment, dan timezone

Reminder serta appointment diambil melalui proyek milik customer. Waktu disimpan sesuai
konvensi internal aplikasi dan ditampilkan dalam `DISPLAY_TIMEZONE=Asia/Jakarta`.

Tautan meeting hanya dirender sebagai link aktif jika skemanya HTTPS. Nilai dengan skema
lain tetap tampil sebagai informasi yang tidak dapat diklik. Tidak ada mutation reminder
atau appointment dari portal customer.

## Profil dan password

Profil hanya mengizinkan field yang di-whitelist: nama, nomor telepon, institusi, dan
program studi. Role, email verification, status aktif, serta field sistem tidak dapat
diubah melalui payload.

Perubahan password:

- mewajibkan password saat ini;
- memakai validasi konfirmasi dan aturan password Laravel;
- di-hash oleh model/action;
- tidak pernah dicatat pada activity log;
- menghasilkan audit event tanpa data rahasia.

## UI, accessibility, dan responsive behavior

Layout customer menyediakan sidebar desktop, drawer mobile, topbar, dropdown user,
notification indicator, logout, breadcrumb, page heading, dan skip link.

Komponen reusable mencakup stat card, project card, status badge, progress bar, milestone,
file/version, revisi, reminder, appointment, upload panel, error summary, format tanggal,
dan format ukuran file.

Verifikasi UI mencakup:

- semantic landmark dan satu heading utama;
- label form unik dan terhubung;
- error summary dan inline error;
- keyboard-visible focus;
- Escape untuk menutup drawer/dropdown;
- focus restoration dan body scroll lock pada drawer;
- reduced-motion behavior;
- line wrapping nama file/judul panjang;
- viewport 360, 390, 768, 1024, 1366, dan 1440 px;
- tidak ada horizontal overflow.

Animasi hanya berupa transisi ringan dan menghormati preferensi reduced motion. Asset
frontend tetap dibundel lokal oleh Vite tanpa menambah dependency runtime eksternal.

## Activity log

Activity log dibuat untuk upload file, upload versi, pengajuan revisi, perubahan profil,
dan perubahan password. Log berisi identitas actor/resource dan metadata aman yang relevan;
password serta isi sensitif tidak dicatat.

## Migration dan data demo

Migration baru:

```text
2026_07_27_000100_add_attachment_checksum_to_revisions_table
```

Migration menambah checksum lampiran revisi dan telah dijalankan non-destruktif pada
database development sebagai batch 2. Tidak ada `migrate:fresh`, reset, truncate, atau
penghapusan data.

`ProjectSeeder` menambah variasi proyek customer untuk status waiting data, customer
review, completed, judul panjang, nama file panjang, milestone, file, versi, revisi,
reminder, dan appointment. Customer kedua tetap disediakan untuk empty-state dan tes
ownership. Guard production pada seeder tetap aktif.

## Pengujian otomatis

Feature test baru:

- `tests/Feature/CustomerPortalTest.php`
- `tests/Feature/CustomerPortalFileTest.php`
- `tests/Feature/CustomerPortalRevisionTest.php`

Cakupan utama:

- guest, role, inactive, dan unverified access;
- dashboard aggregation dan customer scope;
- search, filter, pagination, empty state, dan XSS escaping;
- own/foreign project serta internal-note privacy;
- reminder, appointment, timezone, dan unsafe meeting URL;
- whitelist profil, role injection, current password, dan secret-free log;
- WhatsApp encoding;
- ketiadaan route status/progress/delete;
- nested file/revision IDOR;
- upload UUID/checksum/MIME/extension/double-extension/size;
- download private;
- cleanup pada storage/database failure;
- version sequence dan unique constraint;
- soft-deleted resource;
- protected revision payload dan attachment rollback.

Hasil verifikasi implementasi sebelum penutupan:

```text
94 test passed
519 assertions
0 failed
0 skipped
```

## Build dan dependency audit

Perintah penutupan:

```powershell
vendor\bin\pint --test
php artisan optimize:clear
php artisan test
npm.cmd run build
npm.cmd audit
composer validate --strict
composer audit
```

Build Vite menghasilkan 56 module. Audit dependency tidak menemukan advisory npm maupun
Composer pada saat verifikasi.

## Visual QA

Harness: `scripts/visual-qa-customer.mjs`

Fixture upload: `scripts/fixtures/visual-qa-document.pdf`

Report: `docs/screenshots/tahap-4/visual-qa-report.json`

Jalankan Laravel pada port 8003 dan Chromium/Edge dengan remote debugging port 9225:

```powershell
$env:QA_BASE_URL='http://127.0.0.1:8003'
$env:QA_DEBUGGER_URL='http://127.0.0.1:9225'
$env:QA_FOREIGN_PROJECT_ID='2'
node scripts/visual-qa-customer.mjs
```

Hasil:

- 27 state lulus;
- login, dashboard data/kosong, list desktop/mobile, search/filter;
- detail, milestone, file panjang, upload error/sukses, version history;
- revisi error/form/list, reminder, appointment, profil/password;
- mobile drawer, tablet, ownership 403, dan project list kosong;
- 0 overflow horizontal;
- 0 broken image;
- 0 duplicate ID;
- 0 unlabeled control;
- 0 console error;
- 0 unexpected network error.

Screenshot PNG dan report JSON berada di `docs/screenshots/tahap-4/`.

## File yang dibuat dan diubah

Kelompok perubahan:

- backend: controller customer, form request, action, upload rule, private storage service,
  model, policy, login response, provider, config, route, migration, factory, dan seeder;
- frontend: customer layout/views/components, halaman 403, shared form component, CSS,
  dan JavaScript navigation;
- quality: tiga feature-test suite, PDF fixture, CDP visual-QA harness, 27 screenshot, dan
  report JSON;
- dokumentasi: dokumen ini dan pembaruan README.

Dependency Composer/npm tidak ditambah.

## Masalah yang ditemukan dan penyelesaian

- Service factory memakai `unique()->randomElement()` pada himpunan kecil sehingga seeding
  berulang dapat kehabisan nilai; diganti suffix unik yang tetap realistis.
- Browser QA mewarisi cookie target sebelumnya; harness kini membersihkan browser cookie
  saat mulai agar login dan empty-state dapat diulang secara deterministik.
- Screenshot file awalnya berhenti di panel upload; state QA kini menggulir ke daftar
  dokumen dan version history agar bukti visual tepat sasaran.
- Route placeholder lama diganti route bernama customer; referensi navbar/mobile diperbarui.
- Safe meeting URL dibatasi ke HTTPS untuk mencegah skema berbahaya.

## Risiko tersisa dan batas tahap

- Malware scanning asynchronous, 2FA, session/device management, retention purge scheduler,
  object storage, observability production, dan CSP penuh tetap menjadi Tahap 6.
- Workflow admin/staff untuk mengubah status/progress, menanggapi revisi, serta mengelola
  jadwal/file tetap menjadi Tahap 5.
- Rate limiter aplikasi bukan pengganti WAF atau throttling di reverse proxy.
- MIME inspection mengurangi risiko upload, tetapi tidak menggantikan antivirus.

Tidak ada implementasi Tahap 5 yang dimulai pada perubahan ini.

## Definition of Done

- [x] Customer portal menggantikan placeholder.
- [x] Semua query dan nested resource dibatasi ownership.
- [x] File/lampiran private, UUID-based, checksummed, dan terotorisasi.
- [x] Status/progress tidak dapat dimutasi customer.
- [x] Revisi, reminder, appointment, profil, password, dan WhatsApp tersedia.
- [x] Activity log aman tersedia untuk mutation penting.
- [x] Responsive dan accessibility QA lulus.
- [x] 94 test / 519 assertion lulus.
- [x] Pint, Vite build, npm audit, Composer validation/audit lulus.
- [x] Migration development dijalankan tanpa operasi destruktif.
- [x] Visual QA 27 state lulus dan bukti tersimpan.
- [x] Commit hash tercatat dan commit implementasi berhasil di-push ke `origin/main`.
