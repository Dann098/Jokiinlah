# Tahap 2 — Fondasi Domain dan Autentikasi

**Status:** Selesai dan terverifikasi  
**Tanggal:** 22 Juli 2026  
**Scope:** autentikasi, database/domain model, authorization, factory/seeder, file security foundation, dan automated test.

## Ringkasan hasil

Tahap 2 mengubah skeleton Laravel menjadi fondasi aplikasi yang dapat dimigrasikan dan diuji. Tidak ada landing page final, portal customer lengkap, resource Filament, payment gateway, invoice, membership, atau chat internal pada tahap ini.

Keputusan arsitektur Tahap 1 telah diterapkan:

- internal datetime menggunakan UTC; input customer diproses sebagai Asia/Jakarta melalui `DateTimeService`;
- konsultasi hanya dikelola admin dan tidak memiliki `assigned_staff_id`;
- konsultasi guest harus dihubungkan ke akun customer terverifikasi dengan email yang sama sebelum konversi;
- status/progress proyek diubah manual oleh admin atau assigned staff dan selalu diaudit;
- milestone hanya timeline dan tidak menghitung progress;
- file memiliki `document_uuid`, UUID physical name, unique `(project_id, document_uuid, version)`, versioning, SoftDeletes, dan retention metadata;
- customer dapat mengunggah versi baru tetapi tidak dapat menghapus file yang sudah diproses;
- direct serving pada private local disk dinonaktifkan;
- ZIP/RAR hanya disimpan, tidak diekstrak atau dijalankan;
- 2FA admin/staff tetap dijadwalkan ke Tahap 6.

## Komponen yang dibuat

### Konfigurasi dan autentikasi

- `config/jokiinlah.php` untuk timezone tampilan, whitelist file, ukuran upload, retensi, dan prefix kode.
- Laravel Fortify untuk registrasi, login/logout, verifikasi email, lupa/reset password, dan profile/password action.
- Registrasi selalu menghasilkan customer aktif; field role atau status dari request tidak dipercaya.
- Login menolak akun nonaktif dengan respons kredensial generik dan rate limit 5 percobaan/menit.
- Password baru minimal 12 karakter, menggunakan huruf besar/kecil, angka, simbol, dan pemeriksaan compromised password.
- Redirect login: customer ke `/dashboard`, admin/staff ke `/admin`.
- Middleware `active` dan `role` terdaftar pada bootstrap aplikasi.

### Database dan model

Migration mencakup:

`users`, `notifications`, `code_sequences`, `services`, `consultations`, `projects`, `project_milestones`, `project_files`, `revisions`, `reminders`, `appointments`, `portfolios`, `articles`, `testimonials`, `faqs`, `site_settings`, dan `activity_logs`.

Kolom waktu bisnis menggunakan `DATETIME` dan aplikasi menulis UTC. Ini menghindari implicit timezone conversion dan batas/invalid default `TIMESTAMP` pada MariaDB 10.4. `created_at`/`updated_at` dikelola Laravel.

Trait `SoftDeletes` digunakan eksplisit pada `Consultation`, `Project`, `ProjectFile`, dan `Revision`. Field `archived_at` serta `retention_until` tersedia; purge otomatis ditunda ke Tahap 6.

### Enum dan workflow

Enum memakai value bahasa Inggris yang stabil dan label Indonesia. `ProjectStatus` menetapkan transisi normal berikut:

```text
new_request -> consultation
consultation -> waiting_data | requirement_analysis
waiting_data -> requirement_analysis
requirement_analysis -> in_progress
in_progress -> customer_review
customer_review -> revision | completed
revision -> in_progress | customer_review | completed
```

Assigned staff hanya boleh memakai transisi normal. Admin dapat override dengan alasan wajib; perubahan menyimpan before/after, jenis transisi, alasan, actor, IP, user agent, dan waktu.

### Konsultasi dan kode

- `LinkConsultationToCustomer` menolak relink, akun belum terverifikasi, role bukan customer, atau email berbeda.
- Tidak ada akun/password default yang dibuat otomatis.
- `ConvertConsultationToProject` berjalan dalam transaksi, mensyaratkan status reviewed, akun terverifikasi, layanan tersedia, dan belum pernah dikonversi.
- `CodeGenerator` memakai row lock dan sequence harian agar kode proyek aman terhadap collision pada request paralel.

### Private file

- Root disk `local`: `storage/app/private` dengan `serve=false`.
- Route versi baru: `POST /project-files/{projectFile}/versions`.
- Route download: `GET /project-files/{projectFile}/download`.
- Upload dibatasi 20 MB dan extension/MIME yang disetujui: PDF, DOC/DOCX, XLS/XLSX, CSV, ZIP/RAR, JPG/JPEG, PNG, WEBP.
- Nama fisik adalah UUID; original name hanya metadata dan disanitasi dengan `basename`.
- Checksum SHA-256, MIME, ukuran, uploader, description, version, dan retention dicatat.
- Download memeriksa policy lalu mencatat activity log.
- Gagal menyimpan metadata akan membersihkan file fisik yang sempat ditulis.
- Action permanent delete hanya menerima admin, file yang sudah soft-deleted, dan alasan wajib; aksi dicatat sebelum file fisik serta row dihapus permanen.

Catatan keamanan: whitelist bukan antivirus. Sebelum production, tambahkan malware scanning/quarantine. Archive ZIP/RAR tidak boleh diekstrak maupun dijalankan oleh aplikasi.

### Authorization

Policy tersedia untuk seluruh model domain. Aturan utama:

- admin memiliki pengelolaan domain penuh kecuali activity log tidak boleh dimutasi;
- consultation admin-only;
- staff hanya dapat melihat/mengerjakan proyek yang `assigned_staff_id`-nya cocok;
- customer hanya melihat proyek miliknya;
- akses child entity selalu diturunkan dari akses proyek;
- hanya admin yang dapat soft delete/restore/force delete project file;
- query daftar proyek wajib menggunakan scope `visibleTo($user)`.

### Seeder

Seeder idempoten menghasilkan 1 admin, 2 staff, 5 customer, 8 layanan, 6 portfolio, 5 artikel, 5 testimoni demo, 8 FAQ, 2 konsultasi, 4 proyek, milestone, 2 versi logical document, revisi, reminder, appointment, dan site setting. Seeder menolak environment production.

Testimoni dummy memiliki `is_demo=true` dan dapat diubah melalui dashboard admin setelah modul konten tersedia.

## Verifikasi yang dijalankan

Safety gate sebelum destructive migration:

```text
local|mysql|jokiinlah_dev|UTC|Asia/Jakarta
```

Hasil aktual:

- MariaDB `10.4.32` XAMPP terhubung;
- seluruh 19 migration domain/framework selesai;
- seeder selesai dan pengulangan seeder mempertahankan hitungan yang sama;
- 19 test lulus dengan 59 assertion;
- Vite production build lulus, 56 module ditransformasi;
- Composer audit: tidak ada security advisory;
- npm audit saat instalasi: 0 vulnerability;
- route direct `/storage/{path}` hilang setelah `serve=false`;
- `.env`, SQLite, private storage, build, vendor, dan node_modules ter-ignore.

## Command pengujian ulang

```powershell
php artisan config:clear
php artisan test
php artisan route:list
npm run build
composer audit
```

Untuk uji integrasi MariaDB, verifikasi target development dahulu, lalu:

```powershell
php artisan migrate:fresh --seed
php artisan db:seed
```

Perintah kedua memvalidasi idempotensi. Jangan gunakan `migrate:fresh` pada database yang mengandung data penting.

## Potensi error dan solusi

- **Connection refused port 3306:** hidupkan MariaDB XAMPP dan pastikan port tidak dipakai service lain.
- **MariaDB invalid default timestamp:** migration bisnis telah menggunakan `DATETIME`; jangan mengembalikan kolom wajib ke implicit `TIMESTAMP` MariaDB 10.4.
- **`db:show` gagal pada XAMPP:** beberapa build MariaDB tidak memiliki `performance_schema.session_status`. Gunakan safety gate melalui konfigurasi Laravel dan query `SELECT DATABASE()`; kegagalan ini bukan kegagalan migration.
- **Vite manifest tidak ditemukan:** jalankan `npm install` lalu `npm run build`, atau `npm run dev` saat development.
- **Upload ditolak:** periksa extension, MIME hasil deteksi server, dan batas `UPLOAD_MAX_SIZE`; jangan melonggarkan executable/archive handling.
- **Email verifikasi/reset tidak terkirim:** konfigurasi mailer produksi; development default mencatat email ke log.
- **Logo tidak muncul:** tempatkan file asli pada `public/images/logo.jpeg`; jangan membuat placeholder.

## Batas tahap dan tindak lanjut

Tahap 3 membangun layout publik dan konten landing page. Tahap 4 membangun portal customer lengkap. Tahap 5 membangun dashboard/resource admin. Tahap 6 menambah retention purge terjadwal, 2FA admin/staff, notification hardening, SEO, dan security hardening lanjutan.
