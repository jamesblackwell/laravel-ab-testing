<?php

namespace Quizgecko\AbTesting\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Quizgecko\AbTesting\Services\AbTestingService;

class AbTestingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AbTestingService::class, function ($app) {
            return new AbTestingService();
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ab-testing');
        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../resources/views' => resource_path('views/vendor/ab-testing'),
            ], 'ab-testing-views');

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
            // Add prefix, middleware etc. if needed in the future
            // 'prefix' => config('ab-testing.route_prefix', 'admin'),
            // 'middleware' => config('ab-testing.route_middleware', ['web', 'auth', 'can:viewAdmin']), // Example
            'middleware' => ['web', 'auth', 'can:viewAdmin'] // Assuming default admin guard/middleware
        ];
    }
}