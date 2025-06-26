<?php
namespace Cabanga\CoioteTurbo\Http;

use Illuminate\Contracts\Foundation\Application;
use Swoole\Http\Server;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Cabanga\CoioteTurbo\Core\AppFlusher;
use Cabanga\CoioteTurbo\Core\RequestBridge;
use Throwable;

class SwooleServer
{
    protected Application $app;
    protected array $config;

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
            'worker_num' => $workerNum,
            'daemonize' => $this->config['daemonize'],
            'log_file' => $logFile,
            'backlog' => (int) $this->config['backlog'],
        ]);

        // Register server event listeners using arrow functions to preserve '$this' context.
        $server->on('start', fn (Server $swooleServer) => $this->onServerStart($swooleServer));
        $server->on('request', fn (SwooleRequest $req, SwooleResponse $res) => $this->onRequest($req, $res));

        // This is a blocking call that starts the server.
        $server->start();
    }

    /**
     * Handles the logic for the 'start' event.
     *
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

        // Log that the server has successfully started. This is the source of truth.
        // This will write to the log file in daemon mode, or to the console in foreground mode.
        $this->app->make('log')->info(
            "Coiote Turbo server started successfully. Listening at http://{$this->config['host']}:{$this->config['port']}"
        );
    }

    /**
     * Handles the logic for the 'request' event.
     *
     * @param \Swoole\Http\Request $req
     * @param \Swoole\Http\Response $res
     */
    protected function onRequest(SwooleRequest $req, SwooleResponse $res): void
    {
        try {
            // Converts the Swoole request into a Laravel request
            $request = RequestBridge::convert($req);

            // Gets the HTTP kernel and handles the request
            $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
            $response = $kernel->handle($request);

            // Maps Laravel response to Swoole response (BEST PRACTICE)
            foreach ($response->headers->allPreserveCase() as $name => $values) {
                $res->header($name, implode(', ', $values));
            }
            $res->status($response->getStatusCode());
            $res->end($response->getContent());

            // Terminates the kernel lifecycle
            $kernel->terminate($request, $response);

        } catch (Throwable $e) {
            // On error, return a 500 Internal Server Error response
            $res->status(500);
            $res->end('Internal Server Error: ' . $e->getMessage());
            // Log the detailed exception for debugging.
            $this->app->make('log')->error($e);
        } finally {
            // MANDATORY CLEANUP to avoid memory leaks between requests
            AppFlusher::flush($this->app);
        }
    }
}