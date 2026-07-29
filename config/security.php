<?php

return [
    'trusted_proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_PROXIES', '')),
    ))),

    'headers' => [
        'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
        'csp_report_only' => (bool) env('CSP_REPORT_ONLY', false),
    ],

    'malware' => [
        'enabled' => (bool) env('MALWARE_SCANNER_ENABLED', false),
        'driver' => env('MALWARE_SCANNER_DRIVER', 'clamav'),
        'host' => env('MALWARE_SCANNER_HOST', '127.0.0.1'),
        'port' => (int) env('MALWARE_SCANNER_PORT', 3310),
        'timeout' => (int) env('MALWARE_SCANNER_TIMEOUT', 10),
        'fake_status' => env('MALWARE_SCANNER_FAKE_STATUS', 'clean'),
    ],
];
