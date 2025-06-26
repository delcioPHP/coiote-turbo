<?php

return [
    // IP address the server will listen on
    'host' => env('COIOTE_HOST', '127.0.0.1'),

    // Port
    'port' => env('COIOTE_PORT', 9000),

    // Number of workers to handle requests.
    // Suggestion: 2x the number of CPU cores. Use 'auto' to set automatically.
    'workers' => env('COIOTE_WORKERS', 'auto'),

    // Run the server in background (daemon mode)
    'daemonize' => env('COIOTE_DAEMONIZE', false),

    // Log file for Swoole
    'log_file' => storage_path('logs/coiote-turbo.log'),

    // File to store the PID of the main process
    'pid_file' => storage_path('storage/app/coiote-turbo.pid'),

    'flush' => [
        // Core Laravel Services
        'auth',
        'auth.driver',
        'translator',
        'session',
        'session.store',
        'view',
        'view.finder',
        'events',
        'router',
        'routes',
        'url',
        'redirect',
        'request',
        \Illuminate\Http\Request::class,
        \Illuminate\Contracts\Auth\Guard::class,
        'blade.compiler',
    ],
];
