<?php
declare(strict_types=1);
namespace Mcpuishor\ApcCourierDriver\Providers;

use Illuminate\Support\ServiceProvider;
use Mcpuishor\ApcCourierDriver\ApcCourier;

class ApcCourierDriverServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/apc-courier-driver.php' => config_path('apc-courier-driver.php'),
        ]);

    }

    public function register()
    {
        $this->app->bind('apc-courier-driver', fn()=> new ApcCourier());
    }
}
