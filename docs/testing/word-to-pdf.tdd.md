# TDD Evidence: Convert Word ke PDF Gratis

## Source dan user journeys

Journey diturunkan dari spesifikasi Word-to-PDF pada 7 Agustus 2026:

1. Guest membuka fitur dan mengunggah satu DOC/DOCX untuk mengunduh PDF.
2. File tidak valid, oversize, kosong, DOCM, dan abuse ditolak secara aman.
3. Nama file berbahaya tidak dapat mengontrol path, header, binary, atau output.
4. Source, profile, PDF, dan workspace dibersihkan pada success/failure/timeout.
5. Operator memiliki cleanup safety net dan readiness check.

## RED, GREEN, dan regression evidence

| Tahap | Command | Hasil |
|---|---|---|
| RED | `php artisan test tests/Feature/FreeWordToPdfTest.php tests/Unit/Services/LibreOfficeWordToPdfConverterTest.php tests/Feature/ConversionCleanupCommandTest.php tests/Feature/Integration/LibreOfficeWordToPdfIntegrationTest.php` | 13 failed karena interface/command belum ada; 1 skipped karena LibreOffice absent |
| GREEN | command yang sama ditambah regresi CV/data-cleaner | 24 passed; 227 assertions; 1 skipped |
| Security RED | targeted security boundary tests | disguised archive/macro, missing output, oversized output, inherited process context, and concurrent request failed as expected |
| Security GREEN | Word converter + cleanup + readiness suites | 22 passed; 161 assertions |
| Full | `php artisan test` | 253 passed; 1.442 assertions; 1 skipped |
| Coverage | `php artisan test --coverage --min=80 ...` | Tidak dapat dijalankan: Xdebug/PCOV tidak tersedia |

## Test specification

| Jaminan | Test | Tipe | Hasil |
|---|---|---|---|
| Halaman/kartu publik, SEO, sitemap, accessibility | `FreeWordToPdfTest` | Feature | PASS |
| Route GET/POST, web/CSRF dan throttle | `FreeWordToPdfTest` | Feature | PASS |
| DOC/DOCX diterima; salah/kosong/oversize ditolak | `FreeWordToPdfTest` | Feature | PASS |
| Download PDF dan filename traversal/CRLF aman | `FreeWordToPdfTest` | Feature | PASS |
| Failure, timeout, unavailable memberi pesan aman | `FreeWordToPdfTest` | Feature | PASS |
| Limiter memblokir proses ke-6 per IP | `FreeWordToPdfTest` | Feature | PASS |
| Global limiter dan satu conversion lock membatasi resource | `FreeWordToPdfTest` | Feature | PASS |
| Download callback menghapus workspace | `FreeWordToPdfTest` | Feature | PASS |
| ZIP/OLE menyamar dan macro ditolak secara struktural | `FreeWordToPdfTest` | Feature | PASS |
| OOXML relationship/XML root dan legacy Word FIB diverifikasi | `FreeWordToPdfTest` | Feature | PASS |
| Argv array, binary config, timeout, UUID, profile, signature | `LibreOfficeWordToPdfConverterTest` | Unit | PASS |
| Semua failure path membersihkan workspace | `LibreOfficeWordToPdfConverterTest` | Unit | PASS |
| Cleanup stale/dry-run/containment dan scheduler | `ConversionCleanupCommandTest` | Feature | PASS |
| Binary/workspace/proc_open masuk readiness | `ProductionSecurityTest` | Feature | PASS |
| CWD privat dan application secrets tidak diwariskan | `LibreOfficeWordToPdfConverterTest` | Unit | PASS |
| Malware scan non-clean menghentikan proses sebelum LibreOffice | `LibreOfficeWordToPdfConverterTest` | Unit | PASS |
| Konversi DOCX real dan cleanup | `LibreOfficeWordToPdfIntegrationTest` | Integration | SKIP: binary absent |

## Commit evidence dan known gaps

- RED: `e490ec1`.
- GREEN: `736f142`.
- Readiness RED/GREEN: `43b48d1` / `7bb2c47`.
- Security RED: `f5b9549`, `f55e1c2`, `844ffdd`, dan `96d275c`.
- Security GREEN: `7dea9d6`.
- Trust-boundary RED/GREEN: `871fbad`, `fe13b11` / `75b5d8a`.
- Final lock TTL fix: `ce52f4b`.
- PHPUnit line/branch coverage belum dapat diukur pada mesin ini karena driver coverage
  tidak terpasang.
- Kualitas layout PDF nyata, font substitution, DOC legacy, multi-page, image,
  header/footer, encrypted/corrupt documents harus diuji ulang pada environment yang
  memiliki LibreOffice dan font production.
