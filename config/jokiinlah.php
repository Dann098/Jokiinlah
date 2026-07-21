<?php

return [
    'display_timezone' => env('DISPLAY_TIMEZONE', 'Asia/Jakarta'),
    'upload_max_size' => (int) env('UPLOAD_MAX_SIZE', 20480),
    'allowed_file_extensions' => [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'zip', 'rar',
        'jpg', 'jpeg', 'png', 'webp',
    ],
    'default_retention_days' => (int) env('DEFAULT_RETENTION_DAYS', 180),
    'whatsapp_number' => env('WHATSAPP_NUMBER'),
    'admin_notification_email' => env('ADMIN_NOTIFICATION_EMAIL'),
    'project_code_prefix' => env('PROJECT_CODE_PREFIX', 'PRJ'),
    'consultation_code_prefix' => env('CONSULTATION_CODE_PREFIX', 'CNS'),
];
