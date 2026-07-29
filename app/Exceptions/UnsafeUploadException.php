<?php

namespace App\Exceptions;

use App\Enums\MalwareScanStatus;
use RuntimeException;

class UnsafeUploadException extends RuntimeException
{
    public function __construct(
        public readonly MalwareScanStatus $status,
        public readonly string $reasonCode,
    ) {
        parent::__construct(match ($status) {
            MalwareScanStatus::Infected => 'Berkas ditolak karena terdeteksi berbahaya.',
            default => 'Berkas belum dapat diverifikasi keamanannya. Silakan coba kembali nanti.',
        });
    }
}
