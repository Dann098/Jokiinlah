<?php

return [
    'libreoffice_binary' => env('LIBREOFFICE_BINARY'),
    'word_to_pdf_max_mb' => max(1, (int) env('WORD_TO_PDF_MAX_MB', 10)),
    'word_to_pdf_timeout' => max(1, (int) env('WORD_TO_PDF_TIMEOUT', 60)),
    'word_to_pdf_expanded_max_mb' => max(10, (int) env('WORD_TO_PDF_EXPANDED_MAX_MB', 100)),
    'word_to_pdf_output_max_mb' => max(1, (int) env('WORD_TO_PDF_OUTPUT_MAX_MB', 50)),
    'word_to_pdf_archive_max_entries' => 2000,
    'sandbox_verified' => filter_var(env('WORD_TO_PDF_SANDBOX_VERIFIED', false), FILTER_VALIDATE_BOOL),
    'temporary_directory' => storage_path('app/private/conversions'),
    'cleanup_after_minutes' => 120,
];
