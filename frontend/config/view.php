<?php

return [
    'paths' => [
        resource_path('views'),
    ],

    'compiled' => env(
        'VIEW_COMPILED_PATH',
        realpath(storage_path('framework/views'))
    ),

    // Production deploys precompile Blade views. Disabling timestamp checks
    // prevents PHP-FPM from mutating root-built cache files at request time.
    'check_cache_timestamps' => env('VIEW_CHECK_CACHE_TIMESTAMPS', true),
];
