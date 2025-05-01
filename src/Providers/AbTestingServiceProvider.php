<?php

namespace Quizgecko\AbTesting\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Routing\Router;
use Quizgecko\AbTesting\Services\AbTestingService;
use Quizgecko\AbTesting\Http\Middleware\GenerateAbidMiddleware;

class AbTestingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge the package config file
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/ab-testing.php',
            'ab-testing'
        );

        $this->app->singleton(AbTestingService::class, function ($app) {
            return new AbTestingService();
        });
    }

    public function boot(Router $router): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ab-testing');
        $this->registerRoutes();

        if (config('ab-testing.auto_abid_handling', true)) {
            $router->pushMiddlewareToGroup('web', GenerateAbidMiddleware::class);
        }

        if ($this->app->runningInConsole()) {
            // Publish Config
            $this->publishes([
                __DIR__ . '/../../config/ab-testing.php' => config_path('ab-testing.php'),
            ], 'ab-testing-config');

            // Publish Views
            $this->publishes([
                __DIR__ . '/../../resources/views' => resource_path('views/vendor/ab-testing'),
            ], 'ab-testing-views');

            // Publish Migrations
            $this->publishes([
                __DIR__ . '/../../database/migrations/' => database_path('migrations'),
            ], 'ab-testing-migrations');
        }
    }

    protected function registerRoutes(): void
    {
        Route::group($this->routeConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        });
    }

    protected function routeConfiguration(): array
    {
        return [
            'prefix' => config('ab-testing.route_prefix', 'admin/ab'), // Default prefix from config
            'middleware' => config('ab-testing.route_middleware', ['web']), // Default middleware from config
        ];
    }
}