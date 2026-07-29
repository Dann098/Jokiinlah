<?php

namespace App\Console\Commands;

use App\Services\Retention\TwoPhasePurger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class PurgeRetentionCommand extends Command
{
    protected $signature = 'jokiinlah:purge
        {--dry-run : Hanya laporkan tanpa menghapus file atau record}
        {--limit=50 : Batas jumlah record per eksekusi}';

    protected $description = 'Jalankan two-phase purge secara idempotent.';

    public function handle(TwoPhasePurger $purger): int
    {
        $lock = Cache::lock('jokiinlah:purge', 1800);

        if (! $lock->get()) {
            $this->error('Purge sedang berjalan.');

            return self::FAILURE;
        }

        try {
            $result = $purger->purge(
                max(1, (int) $this->option('limit')),
                (bool) $this->option('dry-run'),
            );
        } finally {
            $lock->release();
        }

        $this->table(['Ditemukan', 'Fisik selesai', 'Record dipurge', 'Gagal', 'Mode'], [[
            $result['found'],
            $result['physical_deleted'],
            $result['purged'],
            $result['failed'],
            $this->option('dry-run') ? 'dry-run' : 'apply',
        ]]);

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
