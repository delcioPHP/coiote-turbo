<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Server Settings
    |--------------------------------------------------------------------------
    |
    | Basic settings for the Swoole HTTP server.
    |
    */

    // The IP address the server will bind to. Use '0.0.0.0' to accept
    // connections from any network interface (recommended for Docker).
    'host' => env('COIOTE_HOST', '127.0.0.1'),

    // The port the server should listen on for new connections.
    'port' => env('COIOTE_PORT', 9000),

    // Number of worker processes to spawn to handle incoming requests.
    // Setting this to 'auto' will use the number of available CPU cores.
    // EX: auto or 1  or  2 or 3 ...
    'workers' => env('COIOTE_WORKERS', 'auto'),

    // Set to 'true' to run the server in the background (production mode).
    'daemonize' => env('COIOTE_DAEMONIZE', false),

    // Log file for Swoole
    'log_file' => storage_path('logs/coiote-turbo.log'),

    // File to store the PID of the main process
    'pid_file' => storage_path('framework/coiote/coiote-turbo.pid'),

    /*
    |--------------------------------------------------------------------------
    | Performance & Tuning
    |--------------------------------------------------------------------------
    |
    | Advanced settings to fine-tune the server's performance and resource usage.
    |
    */

    // The maximum length of the queue for pending connections. For high-traffic
    // sites, increasing this can improve performance during traffic spikes.
    'backlog' => env('COIOTE_BACKLOG', 1024),

    // The maximum number of concurrent connections the server will accept.
    'max_conn' => env('COIOTE_MAX_CONN', 10000),

    // The maximum number of requests a worker will handle before being automatically
    // restarted. This helps to prevent memory leaks in long-running applications.
    // Set to 0 to disable.
    'max_request' => env('COIOTE_MAX_REQUEST', 100000),

    // The dispatch mode for incoming requests. Mode 2 (fixed) is generally best for HTTP.
    'dispatch_mode' => env('COIOTE_DISPATCH_MODE', 2),

    // Disables Nagle's algorithm, which can reduce latency for some applications.
    'open_tcp_nodelay' => env('COIOTE_TCP_NODELAY', true),

    // The maximum time (in seconds) a worker will wait for a response from a backend.
    'max_wait_time' => env('COIOTE_MAX_WAIT_TIME', 5),

    // Allows all workers to listen on the same port, improving scaling on multi-core
    // systems.
    'enable_reuse_port' => env('COIOTE_REUSE_PORT', true),

    // Enables asynchronous, non-blocking reloads of workers.
    'reload_async' => env('COIOTE_RELOAD_ASYNC', true),

    /*
    |--------------------------------------------------------------------------
    | Protocol & Parsing
    |--------------------------------------------------------------------------
    |
    | Settings related to network protocols, compression, and request parsing.
    |
    */

    // Enables gzip/brotli compression for HTTP responses.
    'http_compression' => env('COIOTE_COMPRESSION', true),

    // The compression level to use (1-9). Level 6 is a good balance.
    'compression_level' => env('COIOTE_COMPRESSION_LEVEL', 6),

    // Enable HTTP/2 protocol support. Requires an SSL certificate.
    'open_http2_protocol' => env('COIOTE_HTTP2', false),

    // Let Swoole automatically parse POST request bodies.
    'http_parse_post' => env('COIOTE_PARSE_POST', true),

    // Let Swoole automatically parse file uploads.
    'http_parse_files' => env('COIOTE_PARSE_FILES', true),

    /*
    |--------------------------------------------------------------------------
    | Buffer & Memory Settings
    |--------------------------------------------------------------------------
    |
    | Configure memory buffers for network operations.
    |
    */

    // The output buffer size for each response.
    'buffer_output_size' => env('COIOTE_BUFFER_OUTPUT_SIZE', 4 * 1024 * 1024), // 4MB

    // The TCP socket buffer size.
    'socket_buffer_size' => env('COIOTE_SOCKET_BUFFER_SIZE', 2 * 1024 * 1024), // 2MB

    // The maximum size for a single network packet.
    'package_max_length' => env('COIOTE_MAX_PACKAGE', 8 * 1024 * 1024), // 8MB

    /*
    |--------------------------------------------------------------------------
    | Coroutine & Hooking
    |--------------------------------------------------------------------------
    |
    | Settings to enable and control Swoole's native coroutine capabilities.
    |
    */

    'enable_coroutine' => env('COIOTE_COROUTINE', true),

    // Flags to automatically hook native PHP I/O functions to be coroutine-friendly.
    'hook_flags' => 'SWOOLE_HOOK_ALL',

    /*
    |--------------------------------------------------------------------------
    | Static File Handling
    |--------------------------------------------------------------------------
    |
    | Let Swoole serve static files directly for better performance.
    | It's often better to let a dedicated web server (like Nginx) handle this.
    |
    */

    'enable_static_handler' => env('COIOTE_STATIC_HANDLER', false),
    'document_root' => public_path(),


    /*
    |--------------------------------------------------------------------------
    | Package Specific Settings
    |--------------------------------------------------------------------------
    |
    | Custom settings for Coiote Turbo's own features.
    |
    */

    // Enable built-in performance metrics logging.
    'enable_metrics' => env('COIOTE_METRICS', false),

    // Show detailed error messages. Should be false in production.
    'debug' => env('APP_DEBUG', false),

    // Enable warming of application caches on worker start.
    'warmup_views' => env('COIOTE_WARMUP_VIEWS', true),
    'warmup_app_cache' => env('COIOTE_WARMUP_APP_CACHE', false),

    // Enable health checks for external services on worker start.
    'check_external_services' => env('COIOTE_CHECK_EXTERNAL', false),
    'external_services' => [
        // 'my_api' => 'https://api.example.com/health',
    ],


    /*
    |--------------------------------------------------------------------------
    | Scheduler Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for the built-in scheduler runner.
    |
    */
    'scheduler' => [
        // Set to true to enable the 'coiote:schedule' command.
        'enabled' => env('COIOTE_SCHEDULER_ENABLED', true),

        'log' => [
            // Set to true to enable logging for the scheduler.
            'enabled' => env('COIOTE_SCHEDULER_LOG_ENABLED', true),

            // The path for the scheduler's dedicated log file.
            'path' => storage_path('logs/scheduler-coiote.log'),

            // Minimum log level (e.g., 'debug', 'info', 'warning', 'error').
            'level' => env('COIOTE_SCHEDULER_LOG_LEVEL', 'info'),
        ],
    ],


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
        'blade.compiler',
    ],
];
