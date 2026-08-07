<?php

namespace App\Exceptions;

use RuntimeException;

final class WordToPdfConversionTimedOut extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Konversi melewati batas waktu.');
    }
}
