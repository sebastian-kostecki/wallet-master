<?php

declare(strict_types=1);

return [
    'chunk_size' => (int) env('IMPORT_CHUNK_SIZE', 500),

    'progress_broadcast_interval_seconds' => (int) env('IMPORT_PROGRESS_BROADCAST_INTERVAL', 1),

    'transfer_tokens' => [
        'przelew własny',
        'przelew wewn',
        'transfer',
        'własny',
        'between accounts',
    ],
];
