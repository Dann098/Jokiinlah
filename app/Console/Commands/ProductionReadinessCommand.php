<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProductionReadinessCommand extends Command
{
    protected $signature = 'jokiinlah:readiness';

    protected $description = 'Periksa guard konfigurasi minimum sebelum deployment production.';

    public function handle(): int
    {
        $checks = [
            ['APP_ENV production', app()->environment('production')],
            ['APP_DEBUG nonaktif', ! config('app.debug')],
            ['APP_URL HTTPS', str_starts_with((string) config('app.url'), 'https://')],
            ['Session secure cookie', config('session.secure') === true],
            ['Session HTTP-only', config('session.http_only') === true],
            ['Queue bukan sync', config('queue.default') !== 'sync'],
            ['Failed jobs aktif', config('queue.failed.driver') !== 'null'],
            ['Malware scanner aktif', config('security.malware.enabled') === true],
            ['Driver scanner ClamAV', config('security.malware.driver') === 'clamav'],
            ['Private disk bukan public', config('jokiinlah.private_disk') !== 'public'],
            ['Mail bukan log/array', ! in_array(config('mail.default'), ['log', 'array'], true)],
        ];

        $rows = array_map(
            fn (array $check): array => [$check[0], $check[1] ? 'LULUS' : 'GAGAL'],
            $checks,
        );
        $this->table(['Pemeriksaan', 'Status'], $rows);

        $failed = count(array_filter($checks, fn (array $check): bool => ! $check[1]));

        if ($failed > 0) {
            $this->error("Readiness gagal pada {$failed} pemeriksaan. Deployment production harus ditunda.");

            return self::FAILURE;
        }

        $this->info('Guard konfigurasi minimum lulus. Tetap verifikasi scanner, worker, scheduler, HTTPS, dan backup pada server.');

        return self::SUCCESS;
    }
}
