<?php
namespace Cabanga\CoioteTurbo\Core;

use Illuminate\Contracts\Foundation\Application;
use Throwable;

class AppFlusher
{
    /**
     * Flush stateful services from the application container.
     * This method iterates through a configurable list of services and removes
     * their instances from the container to prevent state leakage between requests.
     *
     * @param Application $app The Laravel application instance.
     */
    public static function flush(Application $app): void
    {
        // Get the list of services to flush from the configuration file.
        $servicesToFlush = $app['config']->get('coioteturbo.flush', []);

        foreach ($servicesToFlush as $service) {
            try {
                // Only forget the instance if it has been previously resolved.
                // This prevents errors from trying to flush a service that was never used.
                if ($app->resolved($service)) {
                    $app->forgetInstance($service);
                }
            } catch (Throwable $e) {
                // In case of an unexpected error during the flush, log it
                // and continue, ensuring the application doesn't crash.
                $app['log']->warning("Could not flush service '{$service}' from container.", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
