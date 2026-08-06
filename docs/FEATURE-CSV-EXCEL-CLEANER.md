# Pembersih CSV & Excel Gratis

## Ringkasan

Fitur publik ini membersihkan CSV dan XLSX langsung di browser tanpa login dan tanpa
mengirim isi file ke server. Route yang tersedia:

| Method | URL | Nama route |
|---|---|---|
| GET/HEAD | `/fitur-gratis` | `free-tools.index` |
| GET/HEAD | `/fitur-gratis/pembersih-data` | `free-tools.data-cleaner` |

Tidak ada endpoint POST, upload server, database, session, API eksternal, analytics
payload, atau browser storage untuk data pengguna. Papa Parse dan SheetJS dibundel
oleh Vite dan hanya dimuat secara dinamis saat halaman cleaner dibuka.

Tujuan fitur dibatasi pada pembacaan, perbandingan, pembersihan, dan ekspor satu file
CSV atau satu sheet XLSX. Fitur ini tidak menyediakan AI cleaning, akun khusus,
riwayat, cloud storage, penggabungan file, konversi tanggal, atau analisis data lanjut.

Dependency frontend yang ditambahkan:

- `papaparse` 5.5.4 dari npm;
- `xlsx` 0.20.3 dari paket distribusi resmi SheetJS yang dikunci di `package-lock.json`.

## Alur dan aturan pembersihan

Urutan operasi bersifat deterministik:

1. normalisasi header menjadi `snake_case` dan buat nama unik;
2. rapikan spasi nilai teks tanpa mengubah angka atau line break;
3. hapus baris yang seluruh nilainya kosong;
4. hapus kolom yang seluruh nilainya kosong;
5. hapus baris duplikat dan pertahankan kemunculan pertama.

Data awal selalu disalin sebelum transformasi. Preview dibatasi 100 baris, sedangkan
file hasil memuat seluruh baris bersih. CSV hasil memakai delimiter koma dan UTF-8
BOM. XLSX hasil selalu berupa workbook baru dengan satu sheet `Data Bersih`.

## Format dan batas keamanan

- CSV maksimal 10 MB dan XLSX maksimal 5 MB; `.xls`, `.xlsm`, `.xlsb`, `.ods`, dan
  format lain ditolak.
- Satu sheet Excel diproses pada satu waktu. Jika sheet pertama kosong, sheet valid
  berikutnya dipilih otomatis tanpa menghilangkan daftar sheet.
- CSV diproses bertahap dan dihentikan pada structural error pertama. Detail error
  disimpan maksimal lima.
- Maksimal 1.000.000 sel per file/sheet.
- XLSX harus berupa paket OOXML ZIP yang memiliki `[Content_Types].xml` dan
  `xl/workbook.xml`; total ukuran dekompresi dibatasi 50 MB sebelum `XLSX.read`.
- Formula tidak dihitung. Cached value Excel dipakai jika ada; formula tanpa cached
  value diperlakukan sebagai teks.
- Nilai yang dapat memicu formula spreadsheet (`=`, `+`, `@`, `-`, tab, CR, atau LF)
  diberi prefix aman saat ekspor. Angka negatif seperti `-1200` dan `-3.5` tetap utuh.
- Style, macro, gambar, komentar, merged cell, dan formula workbook sumber tidak
  dibawa ke file hasil.
- Token generasi mencegah hasil file lama menimpa file terbaru ketika dua pembacaan
  beririsan.

## Privasi dan lifecycle memory

State file, workbook, data awal, data bersih, dan preview hanya berada di memory tab.
Modul tidak memakai `fetch`, Axios, beacon, `localStorage`, `sessionStorage`, atau
IndexedDB. Reset dan penghancuran komponen melepas referensi file/workbook/array;
reload tidak memulihkan data. Object URL download selalu dijadwalkan untuk dicabut.

## Accessibility dan responsive

Upload dapat dipakai dengan klik, keyboard, atau drag-and-drop dan memiliki focus
indicator. Semua opsi mempunyai label, error memakai `role="alert"`, status memakai
`aria-live`, dan preview memakai tab ARIA dengan navigasi panah/Home/End. Kontrol
interaktif memiliki tinggi minimal 44 px. Tabel memakai scroller horizontal lokal
sehingga halaman tidak mengalami horizontal overflow.

Browser QA memproses data sampai state hasil pada 360x800, 390x844, 768x1024,
1024x768, 1366x768, dan 1440x900. Report serta screenshot berada di
`docs/screenshots/data-cleaner/`.

## File yang dibuat atau diubah

Implementasi utama:

- route/controller: `routes/web.php` dan `FreeToolController.php`;
- halaman/kartu/sitemap: Blade cleaner, index Fitur Gratis, dan sitemap publik;
- frontend: `resources/js/data-cleaner.js`, lazy registration di `app.js`, serta style
  responsive di `resources/css/app.css`;
- dependency: `package.json` dan `package-lock.json`;
- test: `FreeDataCleanerTest.php`, regresi route pada `FreeCvBuilderTest.php`, dan
  `tests/Frontend/data-cleaner.test.mjs`;
- QA: `scripts/data-cleaner-qa.mjs`, runner PowerShell, report JSON, dan enam screenshot;
- dokumentasi: file ini, `docs/testing/data-cleaner.tdd.md`, dan ringkasan README.

## Verifikasi aktual

| Gate | Hasil |
|---|---|
| Feature/regression khusus | 11 passed; 131 assertions |
| Unit frontend seluruh modul | 37 passed |
| Coverage cleaner | lines 95,43%; functions 95,40%; branches 82,48% |
| Full Laravel suite | 237 passed; 1.315 assertions |
| Pint | passed |
| Vite production build | passed; 67 modules transformed |
| Browser QA | passed; 6 stateful viewports; 0 console/network errors |
| Dependency audit | Composer 0 advisories; npm 0 vulnerabilities |
| Security review | tidak ada temuan Medium/High tersisa |

Reproduksi browser QA:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/run-data-cleaner-qa.ps1
```

## Release Git

- RED checkpoint: `57b0488` (`test: define free CSV and Excel cleaner behavior`).
- Feature commit: `1dafc1b` (`feat: add free CSV and Excel cleaner`).
- Push status: berhasil dikirim ke `origin/main` pada 6 Agustus 2026; remote bergerak
  dari `c461d3f` ke `1dafc1b` tanpa force push.
