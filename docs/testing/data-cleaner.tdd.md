# Data Cleaner — TDD Evidence

## Acceptance journeys

1. Pengunjung membuka cleaner tanpa login dan tanpa upload server.
2. CSV koma/titik koma dan XLSX satu/multi-sheet dapat dianalisis, dibersihkan, lalu
   diekspor penuh sebagai CSV atau XLSX.
3. File kosong, oversized, corrupt, tersamar, format lama, macro, structural error,
   zip bomb, dan range ekstrem ditolak dengan pesan aman.
4. Formula injection dicegah tanpa merusak angka negatif.
5. Reset/reload tidak menyimpan data dan seluruh alur dapat dipakai pada mobile,
   tablet, desktop, keyboard, dan drag-and-drop.

## RED

Checkpoint RED tersimpan pada commit `57b0488` dengan command:

```text
node --test tests/Frontend/data-cleaner.test.mjs
php artisan test tests/Feature/FreeDataCleanerTest.php tests/Feature/FreeCvBuilderTest.php
```

Hasil awal:

```text
Frontend: ERR_MODULE_NOT_FOUND resources/js/data-cleaner.js
Laravel: 6 failed, 5 passed (route/view/source belum tersedia)
```

Regression tambahan juga ditulis sebelum hardening untuk membuktikan kegagalan pada
CSV struktural, XLSX palsu, range/dekompresi ekstrem, race pembacaan file, sheet
pertama kosong, formula control-whitespace, dan amplifikasi error CSV.

## GREEN

| Jaminan | Test | Hasil |
|---|---|---|
| Route publik, kartu, SEO, sitemap, no POST/upload | `FreeDataCleanerTest` | 5 passed |
| Regresi pembuat CV dan route gratis | `FreeCvBuilderTest` | 6 passed |
| Transformasi immutable dan urutan pipeline | `data-cleaner.test.mjs` | passed |
| CSV delimiter/BOM/Unicode/structural errors | unit + browser QA | passed |
| XLSX signature, ZIP cap, range cap, multi-sheet | unit + browser QA | passed |
| Full-row CSV/XLSX download dan formula safety | unit + actual download QA | passed |
| Race, reset, no persistence/network | unit + browser QA | passed |
| Enam viewport stateful | CDP browser QA | passed |
| Seluruh aplikasi | `php artisan test` | 237 passed; 1.315 assertions |

Coverage terukur untuk `resources/js/data-cleaner.js`:

```text
lines 95.43% | branches 82.48% | functions 95.40%
```

Command coverage:

```powershell
node --test --experimental-test-coverage `
  --test-coverage-include=resources/js/data-cleaner.js `
  --test-coverage-lines=80 `
  --test-coverage-functions=80 `
  --test-coverage-branches=80 `
  tests/Frontend/data-cleaner.test.mjs
```

## Browser evidence

`docs/screenshots/data-cleaner/browser-qa-report.json` membuktikan:

- CSV koma/titik koma, BOM, karakter Unicode, trim, empty row/column, dan deduplikasi;
- XLSX dengan sheet kosong pertama, pergantian sheet, cached formula, dan angka negatif;
- ekspor CSV/XLSX aktual memakai seluruh baris dan tidak membawa formula;
- invalid/oversized/corrupt/XLS/XLSM ditolak;
- tidak ada POST/XHR/Fetch/Ping, local/session storage, atau IndexedDB;
- reset dan reload tidak memulihkan data;
- enam viewport memuat state hasil, tombol download/reset, scroller tabel, target 44 px,
  dan tidak mengalami overflow halaman;
- tidak ada console error atau network error.

QA lokal memakai Microsoft Edge Chromium karena Google Chrome tidak tersedia pada
mesin pengujian. Runner tetap memprioritaskan Google Chrome bila binary tersedia.
