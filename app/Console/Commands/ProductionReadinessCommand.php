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
            ['LibreOffice binary tersedia', $this->libreOfficeAvailable()],
            ['Isolasi LibreOffice diverifikasi', config('converter.sandbox_verified') === true],
            ['Ekstensi ZIP aktif', extension_loaded('zip')],
            ['Ekstensi DOM XML aktif', extension_loaded('dom')],
            ['Cache lock production shared', $this->cacheSupportsSharedLocks()],
            ['Workspace konversi privat', $this->conversionWorkspaceIsPrivate()],
            ['Workspace konversi writable', $this->conversionWorkspaceIsWritable()],
            ['Process execution PHP aktif', $this->processExecutionAvailable()],
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

    private function libreOfficeAvailable(): bool
    {
        $binary = trim((string) config('converter.libreoffice_binary'));
        if ($binary === '' || ! is_file($binary)) {
            return false;
        }

        return PHP_OS_FAMILY === 'Windows' || is_executable($binary);
    }

    private function conversionWorkspaceIsPrivate(): bool
    {
        $privateRoot = realpath(storage_path('app/private'));
        $conversionRoot = realpath((string) config('converter.temporary_directory'));

        if ($privateRoot === false || $conversionRoot === false) {
            return false;
        }

        $privateRoot = rtrim(str_replace('\\', '/', $privateRoot), '/');
        $conversionRoot = rtrim(str_replace('\\', '/', $conversionRoot), '/');

        $probe = $conversionRoot;
        while (strlen($probe) >= strlen($privateRoot)) {
            if (is_link($probe)) {
                return false;
            }

            if (strcasecmp($probe, $privateRoot) === 0) {
                break;
            }

            $parent = dirname($probe);
            if ($parent === $probe) {
                return false;
            }
            $probe = $parent;
        }

        return strcasecmp($conversionRoot, $privateRoot) === 0
            || str_starts_with(strtolower($conversionRoot), strtolower($privateRoot).'/');
    }

    private function conversionWorkspaceIsWritable(): bool
    {
        $root = (string) config('converter.temporary_directory');
        $probe = is_dir($root) ? $root : dirname($root);

        return is_dir($probe) && is_writable($probe);
    }

    private function processExecutionAvailable(): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return function_exists('proc_open') && ! in_array('proc_open', $disabled, true);
    }

    private function cacheSupportsSharedLocks(): bool
    {
        return in_array(config('cache.default'), ['database', 'redis', 'memcached', 'dynamodb'], true);
    }
}
