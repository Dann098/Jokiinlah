# Audit Penutup Tahap 2 — Domain dan Authentication

**Status:** Final dan terverifikasi

**Tanggal audit:** 22 Juli 2026

**Batas scope:** Tahap 2 saja; Tahap 3 tidak dimulai.

## Ringkasan hasil audit

Audit penutup memastikan fondasi domain, autentikasi, authorization, file private, transaction, seeder, dan dokumentasi siap menjadi baseline sebelum pekerjaan Tahap 3 dimulai pada instruksi terpisah.

Temuan dan penyelesaiannya:

1. Login admin/staff memang diarahkan ke `/admin`. Route tersebut tersedia, dilindungi middleware `auth`, `active`, `verified`, dan `role:admin,staff`, serta menampilkan placeholder aman sampai Filament dibuat pada Tahap 5. Feature test memverifikasi redirect dan respons HTTP 200 untuk kedua role.
2. Isolasi file diuji pada controller dan policy: customer lain serta staff yang tidak ditugaskan menerima 403; file soft-deleted menerima 404 karena tidak dapat di-resolve oleh route model binding biasa.
3. Payload upload versi tidak dipercaya untuk `uploaded_by`, `version`, `document_uuid`, `customer_id`, `role`, atau `is_active`. Nilai identitas ditentukan server/action, sedangkan model juga melindungi field tersebut dari mass assignment.
4. Versi baru memakai physical UUID/path baru, mempertahankan logical `document_uuid`, menaikkan version secara transaksi, dan tidak menimpa file lama.
5. Konversi konsultasi ganda ditolak. Kegagalan audit log di dalam transaction membatalkan project, status consultation, dan code sequence.
6. Admin override di luar transition normal ditolak jika alasan kosong dan tidak menghasilkan activity log.
7. Demo seeder memiliki production guard eksplisit dan diuji menolak eksekusi pada environment production.
8. Original filename disanitasi melalui service khusus sebelum disimpan sebagai metadata dan sebelum digunakan pada `Content-Disposition`.
9. Internal permanent-delete action tidak memiliki route atau UI. Purge terjadwal penuh tetap menjadi Tahap 6.

## Hardening original filename

`FilenameSanitizer` menerapkan:

- normalisasi separator dan `basename` untuk menghapus komponen path;
- penghapusan ASCII control character termasuk NUL, CR, LF, dan DEL;
- penggantian karakter filename berisiko;
- normalisasi whitespace;
- panjang maksimum 180 karakter dengan extension dipertahankan;
- fallback `dokumen` jika nama kosong/tidak valid;
- sanitasi ulang saat download untuk melindungi data legacy;
- original filename tidak pernah menjadi storage path.

File fisik selalu memakai UUID. Download menggunakan API response filesystem Laravel dengan nama yang sudah disanitasi, sehingga `Content-Disposition` tidak menerima CR/LF atau path dari pelanggan.

## Permanent delete internal

`PermanentlyDeleteProjectFile` dipertahankan sebagai action internal untuk fondasi Tahap 6, dengan aturan:

- tidak ada route atau UI;
- actor wajib admin;
- file wajib sudah soft-deleted;
- alasan wajib;
- `retention_until` wajib tersedia dan sudah berakhir;
- file fisik wajib ada;
- kegagalan/penolakan storage menghasilkan exception dan mempertahankan database record;
- audit log dan force-delete hanya dilanjutkan setelah storage berhasil.

Scheduler purge, retry, operational UI, dan retention job belum dibuat serta tetap ditunda ke Tahap 6.

## Feature test penutup

Skenario eksplisit yang telah ditambahkan:

- Customer A tidak dapat melihat atau mengunduh file Customer B.
- Customer A tidak dapat mengunggah versi baru file Customer B.
- Staff yang tidak ditugaskan tidak dapat mengakses proyek/file atau mengunggah versi.
- File soft-deleted tidak dapat diunduh.
- Customer tidak dapat memanipulasi field identitas dan privilege.
- Upload versi baru tidak menimpa file lama.
- Sanitasi filename, fallback, panjang maksimum, dan header injection diperiksa.
- Konversi konsultasi ganda ditolak.
- Rollback konversi saat audit logging gagal diperiksa.
- Admin override tanpa alasan ditolak.
- Demo seeder ditolak pada production.
- Internal purge tidak memiliki route, menghormati retensi, dan mempertahankan record saat storage gagal.
- Placeholder `/admin` diuji untuk admin dan staff.

Hasil final setelah Pint: **32 test lulus, 110 assertion**. Route list memuat 24 route, termasuk placeholder `/admin` serta upload/download versioned file, dan tidak memuat route permanent delete.

## Akun demo development

Password akun demo development diubah menjadi `Password123!` agar memenuhi komposisi password aplikasi. Akun demo hanya untuk local/staging, wajib diganti/dihapus sebelum deployment, dan tidak pernah dibuat otomatis dari konsultasi guest.

## File utama audit

- `app/Services/FilenameSanitizer.php`
- `app/Http/Controllers/ProjectFileVersionController.php`
- `app/Http/Controllers/ProjectFileDownloadController.php`
- `app/Actions/ProjectFiles/PermanentlyDeleteProjectFile.php`
- `database/seeders/UserSeeder.php`
- `database/factories/UserFactory.php`
- `tests/Feature/AuthenticationTest.php`
- `tests/Feature/PhaseTwoClosingAuditTest.php`
- `tests/Feature/DomainActionsTest.php`
- `tests/Feature/DatabaseSeederTest.php`

## Command verifikasi wajib

```powershell
vendor\bin\pint
php artisan config:clear
php artisan test
php artisan route:list
npm.cmd run build
composer audit
```

Repository juga diperiksa agar `.env`, credential, database, private storage, upload, log, `vendor`, `node_modules`, dan `public/build` tidak ikut Git. Force push tidak digunakan.
