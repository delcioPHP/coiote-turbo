<?php
namespace Cabanga\CoioteTurbo\Core;

use Illuminate\Foundation\Application;
class AppFlusher
{
    /**
     * Flush the state of the application.
     * @param Application $app
     */
    public static function flush(Application $app): void
    {
        // Get the list of services to flush from the config file.
        $servicesToFlush = $app['config']->get('coioteTurbo.flush', []);

        foreach ($servicesToFlush as $service) {
            $app->forgetInstance($service);
        }

        // Additionally, some services require special reset logic.
        // For example, resetting the configuration repository if it was changed.
        // This can be added here as the tool matures.

        // Example: Resetting the configuration (optional, but powerful)
        // if (isset($app['config.original'])) {
        //    $app->instance('config', clone $app['config.original']);
        // }
    }

    /**
     * Can be called once when the worker starts to save the original config.
     * @param Application $app
     */
    public static function saveOriginalConfig(Application $app): void
    {
        // $app->instance('config.original', clone $app['config']);
    }
}