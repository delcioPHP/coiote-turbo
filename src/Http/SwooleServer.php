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

    public function start(): void
    {
        $server = new Server($this->config['host'], $this->config['port']);

        $workerNum = $this->config['workers'] === 'auto'
            ? swoole_cpu_num() * 2
            : (int) $this->config['workers'];

        $server->set([
            'worker_num' => $workerNum,
            'daemonize' => $this->config['daemonize'],
            'log_file' => $this->config['log_file'],
        ]);

        $server->on('start', function ($server) {
            file_put_contents($this->config['pid_file'], $server->master_pid);
        });

        $server->on('request', function (SwooleRequest $req, SwooleResponse $res) {
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
                // Ideally, use Laravel's exception handler here to log the error
            } finally {
                // MANDATORY CLEANUP to avoid memory leaks
                AppFlusher::flush($this->app);
            }
        });

        $server->start();
    }
}
