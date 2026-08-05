# Free CV Builder — TDD Evidence

## Source dan user journeys

Acceptance criteria berasal dari spesifikasi Pembuat CV ATS Gratis yang diberikan
untuk proyek ini.

1. Sebagai pengunjung, saya dapat membuka alat CV tanpa login dan tanpa mengirim data
   ke server.
2. Sebagai pelamar, saya dapat mengisi bagian CV dinamis dan melihat preview A4 secara
   langsung.
3. Sebagai pengguna yang menjaga privasi, saya dapat memulihkan draft lokal, menghapus
   seluruh data, dan memakai foto tanpa upload.
4. Sebagai pengguna keyboard/mobile, saya dapat mengoperasikan form, tab, repeater,
   zoom, dan print secara accessible.
5. Sebagai pelamar, saya memperoleh PDF A4 yang rapi: data contoh satu halaman,
   konten panjang dapat menjadi dua halaman, tanggal tidak terpotong, Keahlian tidak
   terpisah, dan foto opsional tetap mengikuti layout header.

## RED

Checkpoint test sudah tersedia pada commit `40179b2` sebelum source fitur dibuat.

Command:

```text
php artisan test
```

Hasil baseline aktual:

```text
FAIL — Tests\Feature\FreeCvBuilderTest
4 failed, 226 passed, 1187 assertions
```

Penyebab RED sesuai target: dua route belum ada, halaman belum ada, navbar/sitemap
belum memuat fitur gratis, dan koleksi route menemukan 0 route `fitur-gratis`.

Security review kemudian menemukan draft kedaluwarsa belum dibersihkan. Regression
test frontend ditambahkan dan menghasilkan RED terarah:

```text
8 passed, 1 failed
Expected personal.fullName '', received 'Data Kedaluwarsa'
```

Regresi layout print ditambahkan sebelum perbaikan CSS. RED terarah terbukti melalui:

```text
php artisan test --filter=FreeCvBuilderTest
FAIL — 1 failed, 5 passed; deklarasi A4/margin 12mm belum tersedia

powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/run-cv-builder-qa.ps1
FAIL — Data contoh tercetak 2 halaman, diharapkan 1
```

## GREEN

| Jaminan | Test/command | Jenis | Hasil |
|---|---|---|---|
| Index dan builder public, SEO/JSON-LD/nav/sitemap benar, hanya GET/HEAD | `php artisan test tests/Feature/FreeCvBuilderTest.php` | feature/integration | PASS — 6 test, 75 assertion |
| Repeater dan toggle memakai penggantian state baru | `npm.cmd run test:frontend` | unit | PASS |
| Draft rusak dinormalisasi dan draft >30 hari dihapus | `npm.cmd run test:frontend` | unit/security | PASS |
| Reset membatalkan debounce dan tidak membuat key kembali | `npm.cmd run test:frontend` | unit/privacy | PASS |
| URL non-HTTP(S) dan foto SVG ditolak | `npm.cmd run test:frontend` | unit/security | PASS |
| Data contoh tersedia dan dapat dipersistenkan | `npm.cmd run test:frontend` | unit | PASS |
| Website publik, Customer Portal, Filament, security, dan seluruh modul tidak regresi | `php artisan test` | full regression | PASS — 232 test |
| Frontend production dapat dibundel | `npm.cmd run build` | build | PASS — 60 module |
| Tujuh viewport dan alur interaksi nyata | `scripts/run-cv-builder-qa.ps1` | browser/E2E | PASS |
| Data contoh tanpa/dengan foto, URL panjang, dan konten panjang menghasilkan PDF A4 yang benar | `Page.printToPDF` dan `pdftotext` dalam browser QA | print/E2E | PASS — 1, 1, dan 2 halaman |

## Browser evidence

Report `docs/screenshots/cv-builder/visual-qa-report.json` membuktikan:

- load sample, add/remove experience, section toggle;
- localStorage restore dan reset;
- SVG ditolak dan WebP tampil melalui blob URL;
- payload XSS tampil sebagai teks;
- URL `javascript:` tidak tampil sebagai link;
- tujuh viewport tanpa overflow halaman;
- 0 console error dan 0 network error;
- navbar, footer, editor, form, toolbar, tombol, dan pesan privasi tersembunyi saat print;
- data contoh tanpa foto dan dengan foto masing-masing satu halaman A4;
- PDF panjang dua halaman berisi 49 dan 24 baris bermakna, tanpa halaman kosong/yatim;
- semua kategori Keahlian, termasuk Soft Skills, berada bersama di halaman 2;
- tanggal `Agustus 2023 - Agustus 2024` dan URL portofolio lengkap terbaca dari PDF;
- foto tercetak pada rasio 3:4 di kanan atas, sedangkan mode tanpa foto memakai lebar penuh.

Runner memprioritaskan Google Chrome bila tersedia dan memakai Edge Chromium sebagai
fallback. Verifikasi lokal terakhir memakai Edge Chromium karena binary Google Chrome
tidak terpasang pada mesin pengujian.

## Coverage dan gap

Runtime PHP tidak menyediakan PCOV/Xdebug, sehingga persentase line coverage baru
tidak tersedia. Bukti perilaku terdiri dari 232 test Laravel/1.259 assertions, 10 unit
frontend, 75 assertion khusus fitur, serta browser/print E2E nyata. CSP global yang
masih memerlukan `unsafe-inline`/`unsafe-eval` dicatat sebagai hardening lintas
aplikasi; fitur ini tidak menambah atau memperlonggar policy tersebut.
