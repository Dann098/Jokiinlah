<?php

namespace App\Console\Commands;

use App\Services\WordToPdf\ConversionWorkspaceCleaner;
use Illuminate\Console\Command;

final class CleanupConversionsCommand extends Command
{
    protected $signature = 'jokiinlah:conversion-cleanup {--dry-run : Tampilkan workspace tanpa menghapus}';

    protected $description = 'Bersihkan workspace konversi Word ke PDF yang sudah kedaluwarsa.';

    public function handle(ConversionWorkspaceCleaner $cleaner): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subMinutes(max(1, (int) config('converter.cleanup_after_minutes')))->timestamp;
        $workspaces = $cleaner->staleWorkspaces($cutoff);

        $this->info($dryRun ? 'Mode dry-run: tidak ada file yang dihapus.' : 'Pembersihan workspace konversi dimulai.');

        $deleted = 0;
        foreach ($workspaces as $workspace) {
            if (! $dryRun && $cleaner->delete($workspace)) {
                $deleted++;
            }
        }

        $this->info(sprintf(
            '%d workspace kedaluwarsa ditemukan; %d dihapus.',
            count($workspaces),
            $deleted,
        ));

        return self::SUCCESS;
    }
}
