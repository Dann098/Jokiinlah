# Permintaan Proyek Customer dan Chat Proyek

Dokumen ini menjelaskan implementasi permintaan proyek dari Customer Portal dan
percakapan yang terikat pada satu proyek. Fitur memakai domain `Consultation` dan
`Project` yang sudah ada; tidak ada sistem chat global, WebSocket, attachment chat,
atau edit/hapus pesan.

## Tujuan dan alur

Customer terautentikasi dan terverifikasi dapat membuka `/permintaan-proyek`,
mengirim permintaan baru, melihat riwayat miliknya, mencari atau memfilter status,
serta melengkapi deskripsi ketika admin meminta informasi tambahan.

Status internal `ConsultationStatus` ditampilkan kepada customer sebagai berikut:

| Status internal | Label customer | Makna |
|---|---|---|
| `new` | Menunggu Review | Permintaan menunggu tindakan admin |
| `contacted` | Perlu Info Tambahan | Customer boleh memperbarui deskripsi |
| `reviewed` | Disetujui | Permintaan dapat dikonversi admin |
| `converted` | Menjadi Proyek | Proyek sudah dibuat dan ditautkan |
| `cancelled` | Ditolak | Alasan penolakan ditampilkan |
| `closed` | Ditutup | Permintaan ditutup tanpa proyek aktif |

Admin menindaklanjuti permintaan sumber `customer_portal` melalui resource
Konsultasi Filament:

- **Minta info tambahan** menyimpan instruksi admin dan mengubah status menjadi
  `contacted`.
- **Setujui** menyimpan tanggapan opsional dan mengubah status menjadi `reviewed`.
- **Tolak** mewajibkan alasan dan mengubah status menjadi `cancelled`.
- **Konversi ke proyek** memakai aksi konversi lama yang idempoten dan mengubah
  status menjadi `converted`.

Setiap perubahan status dicatat pada activity log. Database notification dan email
dikirim per kanal agar kegagalan satu kanal tidak membatalkan transaksi bisnis.

## Route dan otorisasi

Seluruh route berikut memakai middleware `auth`, customer aktif, email
terverifikasi, dan role customer:

| Method | Route | Nama |
|---|---|---|
| GET | `/permintaan-proyek` | `customer.project-requests.index` |
| GET | `/permintaan-proyek/buat` | `customer.project-requests.create` |
| POST | `/permintaan-proyek` | `customer.project-requests.store` |
| GET | `/permintaan-proyek/{consultation}` | `customer.project-requests.show` |
| PATCH | `/permintaan-proyek/{consultation}` | `customer.project-requests.update` |

Identitas customer, `user_id`, sumber, status, dan seluruh field operasional selalu
ditentukan server. Query riwayat dibatasi dengan `user_id`; detail dan update
diperiksa kembali oleh `ConsultationPolicy`. Customer hanya dapat mengubah
`description` ketika status `contacted` dan konsultasi belum dikonversi.

## Validasi dan upload

Input permintaan divalidasi melalui Form Request:

- layanan harus aktif;
- judul 3–180 karakter dan deskripsi 30–5.000 karakter;
- nomor WhatsApp dinormalisasi ke format Indonesia;
- deadline tidak boleh berada di masa lalu;
- budget dibatasi sesuai presisi kolom;
- persetujuan privasi dan integritas wajib;
- attachment opsional memakai pipeline private upload yang sudah ada, termasuk
  validasi MIME/extension, batas ukuran, quarantine, dan malware scan.

Attachment bukan bagian dari chat. Dokumen proyek tetap dikirim melalui modul
**File Proyek**, sehingga akses file, versioning, scanning, dan audit tetap berada
di satu jalur yang terkontrol.

## Chat proyek

Chat dirender sebagai komponen Livewire `ProjectChat` di detail proyek customer dan
sebagai relation manager **Percakapan** di Filament. Browser melakukan polling setiap
7 detik. Tampilan awal mengambil 40 pesan terbaru dan tombol **Muat pesan sebelumnya**
menambah 40 pesan per permintaan.

Hak akses:

| Aktor | Melihat | Mengirim |
|---|---|---|
| Customer pemilik proyek aktif | Ya | Ya |
| Staff aktif yang ditugaskan | Ya | Ya |
| Staff lain | Tidak | Tidak |
| Admin aktif | Ya | Ya |
| Pengguna nonaktif | Tidak | Tidak |
| Proyek selesai | Ya | Ya |
| Proyek dibatalkan | Ya | Tidak |
| Proyek soft-deleted | Tidak | Tidak |

`ProjectPolicy::viewChat` dan `sendMessage` diperiksa pada mount, polling, pagination,
dan pengiriman. Pesan dibatasi 2.000 karakter serta 20 pesan per menit untuk setiap
kombinasi user dan proyek. Teks ditampilkan dengan escaping Blade dan tidak menerima
HTML.

Pesan bersifat append-only. Model menolak operasi update dan delete, dan relation
manager tidak menyediakan action edit/hapus. Activity log menyimpan ID pesan,
proyek, pengirim, dan waktu—bukan isi pesan.

## Unread dan notifikasi

`project_chat_participants` menyimpan `last_read_message_id` dan `last_read_at`.
Unread dihitung dari ID pesan, bukan hanya timestamp, agar pesan yang dibuat pada
detik yang sama tetap akurat. Read marker di-upsert secara atomik ketika chat dibuka,
dipolling, atau pengirim berhasil mengirim pesan.

Penerima notifikasi pesan:

- customer pemilik;
- staff yang sedang ditugaskan;
- seluruh admin aktif;
- pengirim dikecualikan;
- staff yang tidak ditugaskan dan user nonaktif dikecualikan.

Database notification dan email berisi potongan maksimal 100 karakter serta URL
kontekstual ke portal customer atau panel Filament. Notifikasi queued memakai
`afterCommit`, tiga percobaan, dan backoff bertahap.

## Skema dan migrasi

`2026_07_31_000100_add_customer_workflow_fields_to_consultations_table.php`
menambah:

- `customer_response` nullable;
- `rejection_reason` nullable;
- `responded_at` nullable;
- index gabungan `source,status`.

`2026_07_31_000200_create_project_chat_tables.php` membuat:

- `project_messages`: proyek, pengirim nullable, isi pesan, timestamp, dan index;
- `project_chat_participants`: proyek, user, read marker, unique proyek-user, dan
  index unread.

Kedua migrasi bersifat additive dan sudah diuji migrate, rollback dua langkah, lalu
migrate ulang pada MariaDB disposable. Jalankan deployment normal:

```powershell
php artisan optimize:clear
php artisan migrate --force
php artisan queue:restart
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Sebelum rollback database, hentikan traffic/worker dan ambil backup. Rollback dua
migrasi akan menghapus seluruh pesan dan read marker; lakukan hanya jika fitur belum
dipakai atau data telah diekspor. Rollback aplikasi tanpa rollback skema aman karena
perubahannya additive.

## Frontend dan aksesibilitas

Customer Portal memakai entry Vite `resources/js/customer.js` yang memulai Livewire
dan satu instance Alpine. Layout memakai `@livewireScriptConfig`, sehingga tidak ada
duplikasi Alpine dan submit chat tetap berupa request Livewire.

Chat memakai heading semantik, ordered list, `aria-live`, label textarea, status
karakter, dan pesan error yang terlihat. QA browser mencakup viewport 360×800,
390×844, 768×1024, 1024×768, 1366×768, dan 1440×900 tanpa overflow horizontal,
kontrol tanpa label, console error, atau respons 5xx.

## Pengujian dan batasan

Pengujian fitur mencakup 28 test permintaan proyek dan 31 test chat (120 assertion)
pada SQLite serta MariaDB. Cakupan perilaku meliputi role/ownership, IDOR, lifecycle,
validasi, rate limit, append-only, unread, penerima notifikasi, audit tanpa isi pesan,
Livewire, dan route.

Batasan yang disengaja:

- polling, bukan real-time WebSocket;
- tidak ada chat global;
- tidak ada attachment chat;
- tidak ada edit atau hapus pesan;
- proyek selesai tetap dapat berkomunikasi, sedangkan proyek dibatalkan read-only;
- riwayat panjang memakai incremental load, belum cursor virtualization.
