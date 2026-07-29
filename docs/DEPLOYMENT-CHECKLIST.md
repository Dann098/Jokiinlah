# Deployment Checklist Jokiinlah

Checklist ini wajib diisi untuk setiap release. Jangan lanjut bila item bertanda
blocker belum terpenuhi. Release candidate source berstatus **STATUS B** sampai
seluruh prerequisite operasional dibuktikan.

## Sebelum deployment

- [ ] Release branch/tag dan commit telah direview; worktree bersih.
- [ ] Automated test, Pint, build, Composer audit, dan npm audit lulus.
- [ ] Backup database dan private files berada pada titik waktu konsisten.
- [ ] Restore drill berhasil di environment terisolasi; waktu, operator, dan checksum dicatat.
- [ ] RPO/RTO, maintenance window, PIC, komunikasi, dan rollback decision point disetujui.
- [ ] Database/user production tersedia dengan least privilege dan `utf8mb4`.
- [ ] `.env` dibuat di secret manager/server, bukan dari credential repository.
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`.
- [ ] `APP_KEY` unik dan tersimpan aman; jangan dirotasi tanpa rencana re-encryption/session.
- [ ] HTTPS certificate, redirect HTTP, HSTS, dan trusted proxy telah diuji.
- [ ] `SESSION_SECURE_COOKIE=true`, HTTP-only, SameSite, domain, dan database session benar.
- [ ] Cache, queue, session, dan failed-job database/Redis tersedia.
- [ ] SMTP/provider mail, sender domain, dan production contact tersedia.
- [ ] Queue worker dikelola Supervisor/systemd/platform dan memiliki restart policy.
- [ ] Cron menjalankan `schedule:run` setiap menit.
- [ ] ClamAV TCP daemon tersedia hanya untuk jaringan aplikasi.
- [ ] `MALWARE_SCANNER_ENABLED=true` dan driver `clamav`; clean/infected/failure diuji.
- [ ] Private disk/bucket writable, terenkripsi, tidak web-accessible, dan unik per environment.
- [ ] Permission `storage` dan `bootstrap/cache` mengikuti user aplikasi.
- [ ] Public web root menunjuk hanya ke `public`.
- [ ] Log aggregation, retention, redaction, alert, disk space, queue, dan health monitoring aktif.
- [ ] Semua akun/data demo dihapus atau diganti; demo seeder tidak dijalankan.
- [ ] Admin/staff production dijadwalkan enroll 2FA dan menyimpan recovery code offline.

Gate sebelum perubahan:

```powershell
vendor\bin\pint --test
php artisan test
npm.cmd run build
composer validate --strict
composer audit
npm.cmd audit
```

## Saat deployment

Aktifkan maintenance mode bila release/migration membutuhkan:

```powershell
php artisan down --retry=60
composer install --no-dev --prefer-dist --optimize-autoloader
npm.cmd ci
npm.cmd run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
php artisan jokiinlah:readiness
```

Catatan:

- Jangan menjalankan `migrate:fresh`, `db:wipe`, atau demo seeder pada production.
- Jangan menjalankan `storage:link` untuk private customer files. Aplikasi ini tidak
  memerlukannya untuk `storage/app/private`.
- Jika public symlink benar-benar dibutuhkan untuk aset non-sensitif lain, periksa
  source/target dan pastikan tidak menunjuk private disk sebelum menjalankannya.
- Jangan lanjutkan bila `jokiinlah:readiness` gagal.
- Catat durasi migration dan jangan menjanjikan rollback schema otomatis.

Setelah gate awal lulus:

```powershell
php artisan up
```

## Setelah deployment

- [ ] `/up`, home, layanan, artikel, sitemap, robots, 404, dan asset memberi respons benar.
- [ ] Redirect HTTP→HTTPS, HSTS, CSP, cookie secure, dan trusted proxy benar.
- [ ] Registration, login, logout, verify email, dan reset password smoke test lulus.
- [ ] Admin dan staff menyelesaikan setup/challenge 2FA; customer tidak dipaksa 2FA.
- [ ] Customer Portal hanya menampilkan project milik customer.
- [ ] Staff hanya melihat assigned project; admin memperoleh scope yang sesuai.
- [ ] Consultation guest tersimpan sekali dan notification terkirim.
- [ ] Upload clean masuk private final path.
- [ ] Sampel uji EICAR pada prosedur aman ditolak/karantina; scanner failure fail-closed.
- [ ] Download clean berhasil dan akses lintas customer/staff ditolak.
- [ ] Queue aktif, backlog normal, dan `php artisan queue:failed` diperiksa.
- [ ] Scheduler heartbeat tercatat dan tiga maintenance command terjadwal.
- [ ] Mail provider menerima pesan uji tanpa membocorkan data sensitif.
- [ ] Dry-run retention dan purge direview sebelum apply pertama.
- [ ] Reconciliation checksum dijalankan pada storage environment yang terisolasi.
- [ ] Log, alert, APM, error rate, latency, CPU, memory, storage, dan database dipantau.
- [ ] Backup pascadeploy berhasil dan dapat dibaca.

Command operasional:

```powershell
php artisan schedule:list
php artisan queue:failed
php artisan jokiinlah:retention-evaluate --dry-run --limit=200
php artisan jokiinlah:purge --dry-run --limit=50
php artisan jokiinlah:files-reconcile --limit=1000 --checksum
```

## Rollback

1. Catat gejala, waktu, release, operator, dan decision maker.
2. Aktifkan maintenance mode dan hentikan/pause worker bila job dapat memperburuk kondisi.
3. Pertahankan log dan ambil backup baru sebelum tindakan korektif.
4. Alihkan application release ke artifact/commit sebelumnya yang telah diverifikasi.
5. Restart worker dan bersihkan/rebuild cache untuk release yang aktif.
6. Rollback database hanya jika migration `down()` telah direview aman.
7. Jika schema/data tidak reversibel, restore database dan private files dari titik
   konsisten yang sudah teruji—jangan menebak rollback manual.
8. Jalankan health, auth, 2FA, portal, panel, upload/download, queue, scheduler, dan log checks.
9. Tutup maintenance mode hanya setelah owner teknis menyetujui.
10. Buat incident report dan rencana koreksi sebelum mencoba deploy ulang.

Jangan memakai force push atau `git reset --hard` sebagai prosedur rollback deployment.
