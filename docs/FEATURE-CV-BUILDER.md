# Pembuat CV ATS Gratis — Academic Classic

## Ringkasan

Fitur ini menyediakan pembuat CV satu kolom gratis tanpa login pada website publik
Jokiinlah. Gambar referensi hanya dipakai sebagai inspirasi struktur visual; template
publik bernama **Academic Classic** dan tidak memakai nama, logo, identitas, atau isi
CV dari Harvard.

Route publik:

| Method | URL | Nama route |
|---|---|---|
| GET/HEAD | `/fitur-gratis` | `free-tools.index` |
| GET/HEAD | `/fitur-gratis/pembuat-cv` | `free-tools.cv-builder` |

Tidak ada endpoint POST, model, migration, database, session, upload server, atau API
eksternal untuk data CV.

## Arsitektur frontend

`resources/js/cv-builder.js` mendaftarkan state Alpine `cvBuilder` melalui entry Vite
publik. Blade dipecah menjadi halaman utama, partial form, partial preview, form card,
dan privacy notice. Preview hanya menggunakan `x-text` dan binding atribut; tidak ada
`x-html` untuk data pengguna.

Struktur draft:

| Bagian | Data utama | Batas |
|---|---|---|
| Personal | nama, gelar, kota, telepon, email, LinkedIn, website | panjang per field dinormalisasi |
| Ringkasan | teks profesional | 900 karakter |
| Pengalaman | organisasi, posisi, lokasi, periode, status aktif, bullet | 8 item; 5 bullet × 250 karakter |
| Pendidikan | program, institusi, lokasi, periode, IPK, penghargaan, mata kuliah, aktivitas | 8 item |
| Proyek | nama, peran, periode, teknologi, URL, bullet | 8 item; 4 bullet × 250 karakter |
| Sertifikasi | nama, penerbit, tanggal, credential ID, URL | 12 item |
| Keahlian | nama kategori dan tag | 8 kategori; 20 tag per kategori |
| Pengaturan | toggle enam bagian dan penggunaan foto | boolean |

Semua operasi repeater memakai penggantian array/object baru. Draft dari browser
diperlakukan sebagai input tidak tepercaya dan dinormalisasi ulang sebelum dipakai.
URL hanya menerima skema HTTP/HTTPS.

## Privasi dan localStorage

- Key tunggal: `jokiinlah_cv_academic_classic_v1`.
- Penyimpanan memakai debounce 600 ms.
- Draft menyimpan `savedAt` dan `expiresAt`, lalu otomatis dihapus setelah 30 hari.
- Draft kedaluwarsa atau JSON yang tidak dapat dipakai dihapus dengan aman.
- Reset membatalkan timer tertunda sebelum menghapus key agar data tidak muncul lagi.
- Foto, object URL, dan isi file tidak pernah masuk localStorage.
- Tidak ada `fetch`, Axios, form submit, query string, analytics payload, atau log server
  yang membawa data CV.

Pengguna tetap perlu menghapus draft lebih awal ketika memakai perangkat bersama.
Tombol **Hapus Semua Data** meminta konfirmasi, menghapus draft, mengosongkan state,
mencabut object URL, membersihkan input foto, dan mengembalikan pengaturan awal.

## Foto lokal

Foto diproses dengan object URL browser. Validasi allowlist memeriksa MIME dan
extension JPG/JPEG, PNG, atau WebP serta batas 1 MB. SVG, extension/MIME yang tidak
sesuai, dan file terlalu besar ditolak sebelum preview. Object URL lama dicabut saat
foto diganti, dihapus, reset, atau komponen dihancurkan.

## Print dan PDF

Preview menggunakan ukuran A4 `210mm × 297mm`, font lokal Arial/Helvetica/Liberation
Sans, teks hitam, garis tipis, satu kolom, dan foto rasio 3:4. Zoom 75/90/100 hanya
berlaku di layar.

CSS print memakai `@page { size: A4; margin: 14mm; }`. Navbar, footer, editor, toolbar,
dan kontrol disembunyikan; item pengalaman/pendidikan/proyek memakai
`break-inside: avoid`. Tombol cetak menjalankan `window.print()`, sehingga pengguna
dapat memilih Save as PDF/Simpan sebagai PDF tanpa library PDF tambahan.

## Accessibility dan responsive

- Setiap input memiliki label; grup memakai fieldset/legend.
- Error email/URL dihubungkan dengan `aria-describedby` dan `aria-invalid`.
- Tab mobile memakai `role="tablist"`, `role="tab"`, dan `aria-selected`.
- Toast dan status draft memakai `aria-live`.
- Tombol, upload file, reset, repeater, urutan bullet, dan zoom dapat dipakai keyboard.
- Desktop memakai editor dan preview berdampingan; mobile/tablet memakai tab dan
  preview A4 di dalam scroller horizontal terkontrol.

Browser QA mencakup 360×800, 390×844, 768×1024, 1024×768, 1366×768, 1440×900,
dan 1920×1080 tanpa horizontal overflow halaman, broken image, console error, atau
network error. Report dan screenshot berada di `docs/screenshots/cv-builder/`.

## Keamanan dan batasan

Pengujian browser membuktikan payload HTML tampil sebagai teks, URL `javascript:`
tidak menghasilkan link, SVG ditolak, dan foto WebP hanya memakai URL `blob:` lokal.
CSP yang sudah ada mengizinkan `blob:` pada `img-src`, sehingga preview foto kompatibel.

CSP global proyek masih memakai `unsafe-inline` dan `unsafe-eval` untuk kompatibilitas
Alpine pada seluruh website. Fitur ini tidak memperlonggar CSP tersebut. Migrasi penuh
ke Alpine CSP build/nonces perlu dikerjakan sebagai hardening lintas aplikasi, bukan
perubahan parsial pada halaman CV.

Versi ini sengaja tidak menyediakan akun CV, database CV, AI writer, pemeriksaan/skor
ATS otomatis, DOCX, pembayaran, watermark, cloud storage, atau banyak template.

## Verifikasi aktual

| Gate | Hasil |
|---|---|
| Feature test CV | 6 passed, 67 assertions |
| Frontend `node:test` | 10 passed |
| Full Laravel suite | 232 passed; 1251 assertions |
| Pint | passed |
| Vite build | passed; 60 modules transformed |
| Composer validate | valid |
| Composer audit | no security advisories |
| npm audit | 0 vulnerabilities |
| Browser QA | passed; 7 viewports, 0 console/network errors |
| Print QA | PDF pendek 1 halaman; PDF panjang 4 halaman |

Reproduksi browser/print QA:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/run-cv-builder-qa.ps1
```
