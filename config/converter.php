<?php

return [
    'libreoffice_binary' => env('LIBREOFFICE_BINARY'),
    'word_to_pdf_max_mb' => max(1, (int) env('WORD_TO_PDF_MAX_MB', 10)),
    'word_to_pdf_timeout' => max(1, (int) env('WORD_TO_PDF_TIMEOUT', 60)),
    'temporary_directory' => storage_path('app/private/conversions'),
    'cleanup_after_minutes' => 120,
];
