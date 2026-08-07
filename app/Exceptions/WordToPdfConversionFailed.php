<?php

namespace App\Exceptions;

use RuntimeException;

final class WordToPdfConversionFailed extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Dokumen belum berhasil dikonversi.');
    }
}
