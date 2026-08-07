<?php

namespace App\Exceptions;

use RuntimeException;

final class WordToPdfConverterUnavailable extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Layanan konversi tidak tersedia.');
    }
}
