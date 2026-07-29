<?php

namespace App\Console\Commands;

use App\Services\Reconciliation\FileReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ReconcilePrivateFilesCommand extends Command
{
    protected $signature = 'jokiinlah:files-reconcile
        {--limit=500 : Batas record dan file per eksekusi}
        {--checksum : Hitung ulang checksum penuh}
        {--repair-state : Perbaiki state physical_deleted yang dapat dibuktikan}
        {--quarantine-orphans : Pindahkan orphan ke karantina tanpa menghapus}';

    protected $description = 'Laporkan inkonsistensi database dan private storage tanpa auto-delete.';

    public function handle(FileReconciler $reconciler): int
    {
        $lock = Cache::lock('jokiinlah:files-reconcile', 3600);

        if (! $lock->get()) {
            $this->error('Reconciliation sedang berjalan.');

            return self::FAILURE;
        }

        try {
            $result = $reconciler->reconcile(
                max(1, (int) $this->option('limit')),
                (bool) $this->option('checksum'),
                (bool) $this->option('repair-state'),
                (bool) $this->option('quarantine-orphans'),
            );
        } finally {
            $lock->release();
        }

        $this->table(['Diperiksa', 'Mismatch', 'State diperbaiki', 'Orphan dikarantina'], [[
            $result['checked'],
            $result['mismatches'],
            $result['repaired'],
            $result['quarantined'],
        ]]);

        foreach (array_slice($result['issues'], 0, 25) as $issue) {
            $this->warn($issue);
        }

        return self::SUCCESS;
    }
}
