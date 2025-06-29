<?php
namespace Cabanga\CoioteTurbo\Http;

use Illuminate\Contracts\Foundation\Application;
use Swoole\Http\Server;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Cabanga\CoioteTurbo\Core\AppFlusher;
use Cabanga\CoioteTurbo\Core\RequestBridge;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SwooleServer
{
    /**
     * The Laravel application instance.
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected Application $app;

    /**
     * The server configuration array.
     * @var array
     */
    protected array $config;

    /**
     * A pool of pre-warmed kernels, keyed by worker ID.
     * @var array
     */
    protected array $kernelPool = [];

    /**
     * The pre-warmed kernel instance for the current worker.
     * @var \Illuminate\Contracts\Http\Kernel|null
     */
    protected ?HttpKernel $kernel = null;

    /**
     * Optional metrics for performance monitoring.
     * @var array
     */
    protected array $metrics = [
        'requests_count' => 0,
        'total_time' => 0,
        'memory_peak' => 0
    ];

    public function __construct(Application $app, array $config)
    {
        $this->app = $app;
        $this->config = $config;
    }

    /**
     * Initializes and starts the Swoole HTTP server.
     */
    public function start(): void
    {
        $server = new Server(
            $this->config['host'],
            $this->config['port'],
            SWOOLE_PROCESS
        );

        $workerNum = $this->config['workers'] === 'auto'
            ? swoole_cpu_num() * 2
            : (int) $this->config['workers'];

        // Ensure the log directory exists before setting it.
        $logFile = $this->config['log_file'];
        $directoryLog = dirname($logFile);
        if (! is_dir($directoryLog)) {
            mkdir($directoryLog, 0755, true);
        }

        $server->set([
            // Core Settings
            'worker_num' => $workerNum,
            'daemonize' => $this->config['daemonize'],
            'log_file' => $logFile,
            'backlog' => (int) ($this->config['backlog'] ?? 1024),

            // Advanced Performance & Tuning Options from Config
            'max_request' => $this->config['max_request'] ?? 0,
            'max_conn' => $this->config['max_conn'] ?? 10000,
            'dispatch_mode' => $this->config['dispatch_mode'] ?? 2,
            'max_wait_time' => $this->config['max_wait_time'] ?? 3,
            'reload_async' => $this->config['reload_async'] ?? true,
            'enable_reuse_port' => $this->config['enable_reuse_port'] ?? true,
            'open_tcp_nodelay' => $this->config['open_tcp_nodelay'] ?? true,

            // Buffer and Compression Settings
//            'buffer_output_size' => $this->config['buffer_output_size'] ?? (2 * 1024 * 1024), // 2MB
//            'socket_buffer_size' => $this->config['socket_buffer_size'] ?? (128 * 1024 * 1024), // 128MB
//            'package_max_length' => $this->config['package_max_length'] ?? (8 * 1024 * 1024), // 8MB
//            'http_compression' => $this->config['http_compression'] ?? true,
//            'compression_level' => $this->config['compression_level'] ?? 1,

            // Coroutine and Hooking Settings
            'enable_coroutine' => $this->config['enable_coroutine'] ?? true,
            'hook_flags' => $this->config['hook_flags'] ?? SWOOLE_HOOK_ALL,

            // Protocol and Static Handler Settings
            'open_http2_protocol' => $this->config['open_http2_protocol'] ?? false,
            'http_parse_post' => $this->config['http_parse_post'] ?? true,
            'http_parse_files' => $this->config['http_parse_files'] ?? true,
            'enable_static_handler' => $this->config['enable_static_handler'] ?? false,
            'document_root' => $this->config['document_root'] ?? $this->getPublicPath(),
        ]);

        // Register server event listeners using arrow functions to preserve '$this' context.
        $server->on('start', fn (Server $swooleServer) => $this->onServerStart($swooleServer));
        $server->on('workerStart', fn (Server $server, int $workerId) => $this->onWorkerStart($server, $workerId));
        $server->on('workerStop', fn (Server $server, int $workerId) => $this->onWorkerStop($server, $workerId));
        $server->on('request', fn (SwooleRequest $req, SwooleResponse $res) => $this->onRequest($req, $res));

        // This is a blocking call that starts the server.
        $server->start();
    }

    /**
     * Helper to get the application's public path.
     * @return string
     */
    protected function getPublicPath(): string
    {
        return $this->app->publicPath();
    }

    /**
     * Helper to get configurations via the Laravel Container.
     * This method uses array access which is more robust in forked processes.
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getConfig(string $key, $default = null)
    {
        try {
            // THE FIX: Use array access for core services like 'config'.
            return $this->app['config']->get($key, $default);
        } catch (Throwable $e) {
            $this->app['log']->warning("Failed to get config '{$key}': " . $e->getMessage());
            return $default;
        }
    }

    /**
     * Handles the logic for the 'start' event. This runs in the MASTER process.
     * @param \Swoole\Http\Server $swooleServer
     */
    protected function onServerStart(Server $swooleServer): void
    {
        // Ensure PID directory exists and write PID file.
        $pidFile = $this->config['pid_file'];
        $directoryPid = dirname($pidFile);
        if (! is_dir($directoryPid)) {
            mkdir($directoryPid, 0755, true);
        }
        file_put_contents($pidFile, $swooleServer->master_pid);

        // Uses the master process's application instance for logging.
        $this->app['log']->info(
            "Coiote Turbo server started. Master PID: {$swooleServer->master_pid}. Listening at http://{$this->config['host']}:{$this->config['port']}"
        );
    }

    /**
     * Worker initialization logic.
     * This runs once for each worker process when it starts.
     * @param \Swoole\Http\Server $server
     * @param int $workerId
     */
    protected function onWorkerStart(Server $server, int $workerId): void
    {
        // Pre-warm the HTTP Kernel instance for this worker to reuse across requests.
        $this->kernel = $this->app->make(HttpKernel::class);

        // Log worker start.
        $this->app['log']->debug("Worker {$workerId} started and kernel pre-warmed.");

        // Worker 0 can be used for special, one-time initialization tasks.
        if ($workerId === 0) {
            $this->initializeWorkerZeroTasks();
        }
    }

    /**
     * Worker cleanup logic.
     * This runs once when a worker process is stopped.
     * @param \Swoole\Http\Server $server
     * @param int $workerId
     */
    protected function onWorkerStop(Server $server, int $workerId): void
    {
        // Clean up this worker's entry in the kernel pool.
        unset($this->kernelPool[$workerId]);

        // Log final metrics if enabled.
        if ($this->config['enable_metrics'] ?? false) {
            $this->logFinalMetrics($workerId);
        }

        $this->app['log']->debug("Worker {$workerId} stopped.");
    }

    /**
     * Gets the pre-warmed kernel for the current worker.
     * Implements a fallback to a pool if the pre-warmed instance is not available.
     * @return \Illuminate\Contracts\Http\Kernel
     */
    protected function getKernel(): HttpKernel
    {
        // Prefer the instance pre-warmed in onWorkerStart for better performance.
        if ($this->kernel !== null) {
            return $this->kernel;
        }

        // Fallback to a per-worker pool. This is less efficient but robust.
        $workerId = function_exists('swoole_get_worker_id') ? swoole_get_worker_id() : 0;

        if (!isset($this->kernelPool[$workerId])) {
            $this->kernelPool[$workerId] = $this->app->make(HttpKernel::class);
        }

        return $this->kernelPool[$workerId];
    }

    /**
     * Handles the logic for each incoming HTTP request.
     * @param \Swoole\Http\Request $req
     * @param \Swoole\Http\Response $res
     */
    protected function onRequest(SwooleRequest $req, SwooleResponse $res): void
    {
        $startTime = microtime(true);

        try {
            // Convert the Swoole request to a Laravel request.
            $request = RequestBridge::convert($req);

            // Get the kernel and handle the request.
            $kernel = $this->getKernel();
            $response = $kernel->handle($request);

            // Send the response to the client.
            $this->sendOptimizedResponse($res, $response);

            // Terminate the request using the same kernel instance.
            $kernel->terminate($request, $response);

        } catch (Throwable $e) {
            $this->handleError($res, $e);
        } finally {
            // MANDATORY CLEANUP to prevent memory leaks between requests.
            AppFlusher::flush($this->app);

            // Record metrics if enabled.
            if ($this->config['enable_metrics'] ?? false) {
                $this->recordMetrics($startTime);
            }
        }
    }

    /**
     * Sends the Laravel response to the Swoole client in an optimized way.
     * @param \Swoole\Http\Response $res
     * @param mixed $response
     */
    protected function sendOptimizedResponse(SwooleResponse $res, $response): void
    {
        // Set all headers from the Laravel response.
        foreach ($response->headers->allPreserveCase() as $name => $values) {
            $headerValue = is_array($values) ? implode(', ', $values) : $values;
            $res->header($name, $headerValue);
        }

        // Set the HTTP status code.
        $res->status($response->getStatusCode());

        // Handle different response types efficiently.
        if ($response instanceof StreamedResponse) {
            // For large, streamed responses, capture output and send.
            ob_start();
            $response->sendContent();
            $content = ob_get_clean();
            $res->end($content);
        } else {
            // For normal responses, send the content directly.
            $res->end($response->getContent());
        }
    }

    /**
     * Handles exceptions that occur during a request.
     * @param \Swoole\Http\Response $res
     * @param \Throwable $e
     */
    protected function handleError(SwooleResponse $res, Throwable $e): void
    {
        $res->status(500);

        // In production, do not expose detailed error messages to the client.
        $errorMessage = $this->config['debug'] ?? false
            ? 'Internal Server Error: ' . $e->getMessage()
            : 'Internal Server Error';

        $res->end($errorMessage);

        // Log the full error for debugging purposes.
        $this->app['log']->error('Swoole Request Error', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => mb_substr($e->getTraceAsString(), 0, 2000),
        ]);
    }

    /**
     * A simple metrics recording system.
     * @param float $startTime
     */
    protected function recordMetrics(float $startTime): void
    {
        $this->metrics['requests_count']++;
        $this->metrics['total_time'] += (microtime(true) - $startTime);
        $this->metrics['memory_peak'] = max($this->metrics['memory_peak'], memory_get_peak_usage(true));

        // Log metrics periodically (e.g., every 1000 requests).
        $logFrequency = $this->config['metrics_log_frequency'] ?? 1000;
        if ($logFrequency > 0 && $this->metrics['requests_count'] % $logFrequency === 0) {
            $avgTime = $this->metrics['total_time'] / $this->metrics['requests_count'];
            $this->app['log']->info('Performance Metrics', [
                'requests' => $this->metrics['requests_count'],
                'avg_response_time' => round($avgTime * 1000, 2) . 'ms',
                'memory_peak' => round($this->metrics['memory_peak'] / 1024 / 1024, 2) . 'MB',
            ]);
        }
    }

    /**
     * Logs the final metrics when a worker process stops.
     * @param int $workerId
     */
    protected function logFinalMetrics(int $workerId): void
    {
        if ($this->metrics['requests_count'] > 0) {
            $avgTime = $this->metrics['total_time'] / $this->metrics['requests_count'];
            $this->app['log']->info("Final metrics for worker {$workerId}", [
                'total_requests' => $this->metrics['requests_count'],
                'avg_response_time' => round($avgTime * 1000, 2) . 'ms',
                'memory_peak' => round($this->metrics['memory_peak'] / 1024 / 1024, 2) . 'MB',
            ]);
        }
    }

    /**
     * Special, one-time initialization tasks performed by Worker 0.
     */
    protected function initializeWorkerZeroTasks(): void
    {
        try {
            $this->warmupApplicationCaches();
            $this->ensureRoutesCached();
            $this->validateCriticalConfig();
            $this->checkExternalServices();

            $this->app['log']->info('Worker 0 initialization tasks completed successfully.');
        } catch (Throwable $e) {
            $this->app['log']->error('Worker 0 initialization failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Warms up critical application caches.
     */
    protected function warmupApplicationCaches(): void
    {
        try {
            $console = $this->app->make(\Illuminate\Contracts\Console\Kernel::class);

            // Cache Laravel's configuration files.
            if (method_exists($this->app, 'configurationIsCached') && !$this->app->configurationIsCached()) {
                $console->call('config:cache');
            }

            // Cache view/template files.
            if ($this->config['warmup_views'] ?? true) {
                $console->call('view:cache');
            }

            // Example of warming up application-specific cache.
            if ($this->config['warmup_app_cache'] ?? false) {
                $cache = $this->app['cache'];
                $cache->remember('app.critical_settings', 3600, function() {
                    return [
                        'features' => $this->getConfig('features', []),
                        'api_endpoints' => $this->getConfig('api.endpoints', []),
                    ];
                });
            }

            $this->app['log']->debug('Application caches warmed up.');
        } catch (Throwable $e) {
            $this->app['log']->warning('Cache warmup failed.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Validates database connections.
     */
    protected function validateDatabaseConnections(): void
    {
        try {
            $connections = $this->getConfig('database.connections', []);

            foreach ($connections as $name => $config) {
                if ($config['driver'] ?? null) {
                    $this->app['db']->connection($name)->reconnect();
                    $this->app['log']->debug("Database connection '{$name}' validated.");
                }
            }
        } catch (Throwable $e) {
            $this->app['log']->warning('Database validation failed.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Ensures that routes are cached for production.
     */
    protected function ensureRoutesCached(): void
    {
        try {
            if (method_exists($this->app, 'routesAreCached') && !$this->app->routesAreCached()) {
                $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->call('route:cache');
                $this->app['log']->debug('Routes cached.');
            }
        } catch (Throwable $e) {
            $this->app['log']->warning('Route caching failed.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Validates critical application/Swoole configurations.
     */
    protected function validateCriticalConfig(): void
    {
        $criticalSettings = [
            'app.key' => 'Application key must be set.',
            'app.env' => 'Environment must be defined.',
        ];

        foreach ($criticalSettings as $key => $message) {
            if (empty($this->getConfig($key))) {
                throw new \RuntimeException("Critical config missing: {$message}");
            }
        }

        // Validate required Swoole configurations from the package config.
        $requiredSwooleConfigs = ['host', 'port', 'workers'];
        foreach ($requiredSwooleConfigs as $config) {
            if (!isset($this->config[$config])) {
                throw new \RuntimeException("Required Swoole config missing: {$config}");
            }
        }

        $this->app['log']->debug('Critical configuration validated.');
    }

    /**
     * Performs a health check on critical external services.
     */
    protected function checkExternalServices(): void
    {
        if (!($this->config['check_external_services'] ?? false)) {
            return;
        }

        $services = $this->config['external_services'] ?? [];

        foreach ($services as $name => $url) {
            try {
                // Use a quick timeout to avoid delaying startup.
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 3,
                        'method' => 'HEAD',
                    ],
                ]);

                $headers = @get_headers($url, false, $context);
                if ($headers && strpos($headers[0], '200') !== false) {
                    $this->app['log']->debug("External service '{$name}' is healthy.");
                } else {
                    $this->app['log']->warning("External service '{$name}' might be down.");
                }
            } catch (Throwable $e) {
                $this->app['log']->warning("Failed to check external service '{$name}'.", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
