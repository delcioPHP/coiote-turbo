<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Server Host
    |--------------------------------------------------------------------------
    |
    | The IP address the server will bind to. Use '0.0.0.0' to accept
    | connections from any network interface (recommended for Docker).
    |
    */
    'host' => env('COIOTE_HOST', '127.0.0.1'),

    /*
    |--------------------------------------------------------------------------
    | Server Port
    |--------------------------------------------------------------------------
    |
    | The port the server should listen on for new connections.
    |
    */
    'port' => env('COIOTE_PORT', 9000),

    /*
    |--------------------------------------------------------------------------
    | Worker Processes
    |--------------------------------------------------------------------------
    |
    | The number of worker processes to spawn to handle incoming requests.
    | Setting this to 'auto' will use the number of available CPU cores.
    | modes: auto, 1, 2, 3, 4...
    |
    */
    'workers' => env('COIOTE_WORKERS', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Daemonize
    |--------------------------------------------------------------------------
    |
    | Set to 'true' to run the server in the background (production mode).
    | Set to 'false' to run in the foreground (development mode).
    |
    */
    'daemonize' => env('COIOTE_DAEMONIZE', false),

    /*
    |--------------------------------------------------------------------------
    | Server Backlog
    |--------------------------------------------------------------------------
    |
    | The maximum length of the queue of pending connections. For high-traffic
    | sites, increasing this value can improve performance during traffic spikes.
    | Note: This value cannot exceed the system's 'net.core.somaxconn' limit.
    |
    */
    'backlog' => env('COIOTE_BACKLOG', 1024),

    /*
    |--------------------------------------------------------------------------
    | Log and PID File Paths
    |--------------------------------------------------------------------------
    |
    | The locations to store the Swoole server log file and the master PID file.
    | It's recommended to use the storage_path() helper.
    |
    */
    'log_file' => storage_path('logs/coiote-turbo.log'),
    'pid_file' => storage_path('framework/coiote/coiote-turbo.pid'),

    /*
    |--------------------------------------------------------------------------
    | State Flushing
    |--------------------------------------------------------------------------
    |
    | A list of Laravel services that should be "forgotten" and re-instantiated
    | between each request to prevent memory leaks and state contamination.
    |
    */
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