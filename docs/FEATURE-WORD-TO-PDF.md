# Convert Word ke PDF Gratis

## Tujuan dan scope

Fitur publik ini mengonversi satu dokumen Microsoft Word menjadi PDF tanpa login.
Fitur hanya menerima DOC dan DOCX; tidak menyediakan batch, DOCM, template, RTF,
ODT, merge, edit, compress, OCR, password PDF, cloud storage, atau layanan konversi
pihak ketiga.

| Method | URL | Nama route |
|---|---|---|
| GET/HEAD | `/fitur-gratis/word-ke-pdf` | `free-tools.word-to-pdf` |
| POST | `/fitur-gratis/word-ke-pdf` | `free-tools.word-to-pdf.convert` |

Route POST berada di middleware `web`, sehingga CSRF Laravel aktif. Limiter
`word-to-pdf` membatasi 5 proses per 10 menit per IP yang di-hash dan 30 proses per
10 menit secara global. Distributed cache lock membatasi satu proses LibreOffice
aktif pada satu waktu untuk melindungi CPU, RAM, PID, dan disk.

## Privasi dan temporary storage

Dokumen tidak masuk database, activity log, analytics, public storage, atau Git.
Setiap proses memakai workspace privat:

```text
storage/app/private/conversions/{workspace-uuid}/
|-- {source-uuid}.doc|docx
|-- {source-uuid}.pdf
`-- profile/
```

Nama asli hanya dipakai untuk menghasilkan nama download yang sudah disanitasi.
Source dan profile dihapus segera setelah hasil PDF tervalidasi. PDF dan workspace
dihapus pada blok `finally` setelah body download selesai. Kegagalan dan timeout
membersihkan seluruh workspace. Scheduler tetap menjadi safety net untuk disconnect,
process termination, disk error, atau cleanup yang tidak sempat selesai.

## Arsitektur converter

- `ConvertWordToPdfRequest` menangani authorization publik dan batas upload.
- `ValidWordDocument` memeriksa extension, MIME hasil `fileinfo`, ukuran nonzero,
  struktur compound file, stream `WordDocument`, Word FIB, dan flag enkripsi untuk
  DOC. Untuk DOCX, XML content types, package relationship, dan root WordprocessingML
  diparsing dengan network access nonaktif. Container macro, encrypted, menyamar,
  rusak, path tidak aman, terlalu banyak entry, rasio kompresi ekstrem, dan expanded
  size berlebih ditolak.
- `WordToPdfConverterInterface` menjadi boundary controller dan dapat diganti fake
  pada test biasa.
- `LibreOfficeWordToPdfConverter` membuat workspace UUID, physical filename UUID,
  profile LibreOffice per proses, menjalankan malware scan fail-closed, memanggil
  runner, memvalidasi exit code, ukuran, dan signature `%PDF-`, lalu mengatur cleanup.
- `SymfonyLibreOfficeProcessRunner` memakai `Symfony Process` dengan array argument,
  working directory privat, environment parent yang dibersihkan, timeout dan idle
  timeout. Tidak ada `shell_exec` atau command string dari request.
- `WordToPdfConversionResult` hanya membawa path server yang dibuat converter; user
  tidak dapat menentukan binary, input path, output directory, atau command.

Argumen LibreOffice mencakup mode headless, `--nologo`, `--nodefault`,
`--nofirststartwizard`, `--norestore`, `--convert-to pdf`, output directory internal,
dan `-env:UserInstallation=file:///...` yang unik per conversion. `HOME`, `TMP`,
`TEMP`, dan `USERPROFILE` diarahkan ke workspace. Key dari process environment,
`$_ENV`, dan `$_SERVER` dibersihkan kecuali allowlist OS minimum. Stdout, stderr,
nama dokumen, dan isi dokumen tidak dicatat ke log.

## Konfigurasi

```dotenv
LIBREOFFICE_BINARY=
WORD_TO_PDF_MAX_MB=10
WORD_TO_PDF_TIMEOUT=60
WORD_TO_PDF_EXPANDED_MAX_MB=100
WORD_TO_PDF_OUTPUT_MAX_MB=50
WORD_TO_PDF_SANDBOX_VERIFIED=false
```

`LIBREOFFICE_BINARY` wajib menunjuk file executable yang benar pada server. Contoh
umum adalah `C:\Program Files\LibreOffice\program\soffice.exe` di Windows atau
`/usr/bin/libreoffice`/`/usr/bin/soffice` di Linux, tetapi path tidak di-hardcode.
Jika kosong/tidak valid, halaman tetap dapat dibuka dan proses menampilkan pesan
aman bahwa layanan sedang tidak tersedia.

`WORD_TO_PDF_SANDBOX_VERIFIED` hanya boleh diubah menjadi `true` setelah operator
memastikan proses LibreOffice berjalan sebagai user non-root khusus, tanpa network
egress, dengan filesystem confinement dan limit CPU/RAM/PID/disk. Pada production,
converter menolak berjalan jika attestation ini masih `false`.

## Validasi dan keamanan

- allowlist DOC/DOCX; DOCM dan extension lain ditolak;
- ukuran default maksimal 10 MB dan file kosong ditolak;
- MIME server, extension, signature fisik, struktur OLE/OOXML, dan jenis dokumen
  harus konsisten; XML menggunakan `LIBXML_NONET`, DOCM dan legacy DOC yang memuat
  storage macro/encryption ditolak;
- DOCX dibaca tanpa diekstrak, maksimal 2.000 entry, expanded size default 100 MB,
  dan compression ratio per entry maksimal 100:1;
- CSRF aktif, operasi mahal di-rate-limit, dan konversi dibatasi satu per satu;
- ClamAV harus menyatakan dokumen bersih sebelum proses LibreOffice dimulai;
- command memakai array argument dan binary hanya berasal dari config;
- physical filename serta directory memakai UUID;
- output wajib file nonzero maksimal 50 MB dengan header `%PDF-`;
- nama download melewati `FilenameSanitizer`, dipaksa berekstensi `.pdf`, dan
  Content-Disposition dibentuk oleh Symfony untuk mencegah CRLF/header injection;
- cleanup hanya menerima immediate child UUID, menolak symlink, serta memverifikasi
  canonical parent sebelum recursive delete;
- response memakai `application/pdf`, `nosniff`, `no-store`, dan pesan error tidak
  membawa path, command, stderr, stack trace, atau environment.

LibreOffice headless bukan security sandbox. Production wajib menjalankannya sebagai
user OS non-root dengan least privilege, filesystem confinement, network egress yang
dibatasi, serta limit CPU, memory, process, dan disk.

## Cleanup dan scheduler

```powershell
php artisan jokiinlah:conversion-cleanup --dry-run
php artisan jokiinlah:conversion-cleanup
```

Command hanya memproses direct child UUID di configured conversion root dan hanya
menghapus workspace berusia lebih dari 120 menit. Directory baru, nama non-UUID,
symlink, sibling, dan path di luar root tidak dihapus. Scheduler menjalankan command
setiap jam dengan `withoutOverlapping(10)` dan `onOneServer()`.

`php artisan jokiinlah:readiness` juga memeriksa binary LibreOffice, attestation
isolasi, ekstensi ZIP/DOM, cache shared yang mendukung lock, lokasi workspace
canonical privat/writable tanpa symlink, dan ketersediaan `proc_open` tanpa membuka
binary path ke publik.

## Automated test dan integration test

Feature/unit coverage mencakup halaman publik, kartu, SEO/sitemap, route/middleware,
DOC/DOCX, file salah/kosong/oversize, filename traversal/CRLF, response PDF, cleanup
stream, failure/timeout/unavailable, rate limit, Process argv/config/timeout/profile,
output invalid, cleanup containment/dry-run, scheduler, dan readiness.

Hasil aktual 7 Agustus 2026:

| Gate | Hasil |
|---|---|
| RED khusus fitur | 13 failed; 1 skipped (implementasi belum ada) |
| Targeted GREEN + regresi free tools | 24 passed; 227 assertions; 1 skipped |
| Full Laravel suite | 253 passed; 1.442 assertions; 1 skipped |
| Frontend suite existing | 37 passed |
| PHPUnit coverage | Tidak tersedia; runtime tidak memiliki Xdebug/PCOV |
| Integration LibreOffice | SKIP eksplisit; binary tidak tersedia |
| Pint | PASS |
| Vite build | PASS; 68 modules transformed |
| Composer validate/audit | PASS; 0 advisory |
| npm audit | PASS; 0 vulnerability |

Integration test membuat DOCX berisi heading, paragraf, dan tabel. Test hanya berjalan
jika binary terkonfigurasi; jika tersedia, test memeriksa output, ukuran, `%PDF-`, dan
cleanup. Mesin development ini tidak memiliki LibreOffice, sehingga format/layout
hasil PDF nyata belum diverifikasi dan fitur belum boleh dinyatakan production-ready.

## Browser QA

Runner:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/run-word-to-pdf-qa.ps1
```

Browser QA berstatus **partial** karena LibreOffice tidak tersedia. Bagian responsive
UI lulus di Microsoft Edge Chromium pada 360x800, 390x844, 768x1024, 1024x768,
1366x768, dan 1440x900. Seluruh viewport memiliki satu H1, tanpa horizontal overflow,
dropzone tidak terpotong, virtual file dengan nama panjang benar-benar terpilih dan
terlihat tanpa merusak layout, tombol submit 48 px, privacy notice/error/live-region
tersedia, dan tidak ada console/network error. PDF ditolak pada validasi client.
Report dan screenshot ada di `docs/screenshots/word-to-pdf/`.

DOC/DOCX nyata dengan multi-page, gambar, header/footer, tabel, font, DOC legacy,
encrypted/corrupt document, serta pemeriksaan layout PDF tidak dijalankan karena
LibreOffice tidak tersedia. Skenario server-side reject/failure tetap diuji otomatis.

## Prerequisite production dan batasan

- LibreOffice terpasang dan binary path terverifikasi oleh web/worker user;
- PHP mengizinkan process execution dan `proc_open`;
- ekstensi PHP DOM/libxml dan ZIP aktif;
- ClamAV aktif dan dapat dijangkau; kegagalan scanner membuat conversion fail-closed;
- `storage/app/private` writable, terisolasi, tidak disymlink ke public, serta disk
  dan inode cukup;
- `upload_max_filesize` minimal 10 MB dan `post_max_size` lebih besar dari batas itu;
- timeout PHP, reverse proxy, dan web server melebihi conversion timeout;
- scheduler Laravel berjalan setiap menit;
- cache production menggunakan database/Redis/Memcached/DynamoDB yang shared dan
  mendukung atomic lock di seluruh node;
- user proses non-root dan resource/network isolation diterapkan, lalu
  `WORD_TO_PDF_SANDBOX_VERIFIED=true`;
- font umum tersedia: Liberation Sans/Serif dan substitusi Arial/Times/Calibri yang
  sesuai lisensi environment.

Hasil dapat berbeda jika font server tidak tersedia. Fitur tidak mendukung DOCM,
file password/encrypted, banyak file, dan tidak menjanjikan layout identik 100%.

## File dan release Git

Implementasi mengubah route/provider/config/env/filesystem/sitemap/index/README dan
menambah controller, FormRequest, rule, contract, Process runner, converter, value
object, exception, command, Blade, Alpine module, test, QA runner, screenshot, serta
dokumentasi ini. Tidak ada database atau migration baru.

- RED checkpoint: `e490ec1` (`test: define free Word to PDF converter behavior`).
- Feature GREEN: `736f142` (`feat: add free Word to PDF converter`).
- Readiness RED/GREEN: `43b48d1` / `7bb2c47`.
- Security RED: `f5b9549`, `f55e1c2`, `844ffdd`, dan `96d275c`.
- Security GREEN: `7dea9d6`.
- Trust-boundary RED: `871fbad` dan `fe13b11`.
- Trust-boundary GREEN: `75b5d8a`.
- Lock TTL fix after final review: `ce52f4b`.
- Push status: pending final verification.
