<?php

namespace App\Console\Commands;

use App\Services\Retention\RetentionEvaluator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class EvaluateRetentionCommand extends Command
{
    protected $signature = 'jokiinlah:retention-evaluate
        {--dry-run : Hanya laporkan tanpa mengubah state}
        {--limit=100 : Batas jumlah record per eksekusi}';

    protected $description = 'Evaluasi record terhapus yang telah melewati masa retensi.';

    public function handle(RetentionEvaluator $evaluator): int
    {
        $lock = Cache::lock('jokiinlah:retention-evaluate', 900);

        if (! $lock->get()) {
            $this->error('Evaluasi retensi sedang berjalan.');

            return self::FAILURE;
        }

        try {
            $result = $evaluator->evaluate(
                max(1, (int) $this->option('limit')),
                (bool) $this->option('dry-run'),
            );
        } finally {
            $lock->release();
        }

        $this->table(['Ditemukan', 'Diperbarui', 'Gagal', 'Mode'], [[
            $result['found'],
            $result['updated'],
            $result['failed'],
            $this->option('dry-run') ? 'dry-run' : 'apply',
        ]]);

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
