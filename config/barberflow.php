<?php

use App\Support\LocalRealtimeConfiguration;

return [
    // Business dates use this timezone; persisted timestamps remain UTC.
    'timezone' => env('BARBERFLOW_TIMEZONE', 'Asia/Makassar'),
    // Explicit local activation; ignored entirely in testing and production.
    'realtime_local' => env('APP_ENV', 'production') === 'local'
        ? LocalRealtimeConfiguration::read(storage_path('app/private/realtime-local.json'))
        : null,
];
