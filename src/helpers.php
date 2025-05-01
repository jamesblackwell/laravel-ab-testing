<?php

use App\Models\User;
use Quizgecko\AbTesting\Services\AbTestingService;
use Illuminate\Support\Facades\App;

if (!function_exists('feature_flag')) {
    /**
     * Get the assigned variant for a feature flag and scope.
     *
     * @param string $featureName The name of the feature flag.
     * @param \App\Models\User|string|null $scope The scope (User model or qgid string). Defaults to qgid() if null.
     * @return mixed The resolved feature value (e.g., 'control', 'test', true, false).
     */
    function feature_flag(string $featureName, User|string|null $scope = null): mixed
    {
        return App::make(AbTestingService::class)->getVariant($featureName, $scope);
    }
}

if (!function_exists('experiment_view')) {
    /**
     * Track a view for an experiment.
     *
     * @param string $experimentName The name of the experiment.
     * @param \App\Models\User|string|null $scope The scope (User model or qgid string). Defaults to qgid() if null.
     * @param string|bool|null $variant Optional explicit variant ('test'/'control'). If null, fetched automatically.
     * @return void
     */
    function experiment_view(string $experimentName, User|string|null $scope = null, string|bool|null $variant = null): void
    {
        App::make(AbTestingService::class)->trackView($experimentName, $scope, $variant);
    }
}

if (!function_exists('experiment_conversion')) {
    /**
     * Track a primary conversion for an experiment.
     *
     * @param string $experimentName The name of the experiment.
     * @param \App\Models\User|string|null $scope The scope (User model or qgid string). Defaults to qgid() if null.
     * @return void
     */
    function experiment_conversion(string $experimentName, User|string|null $scope = null): void
    {
        App::make(AbTestingService::class)->trackConversion($experimentName, $scope, 'primary');
    }
}

if (!function_exists('experiment_secondary_conversion')) {
    /**
     * Track a secondary conversion for an experiment.
     *
     * @param string $experimentName The name of the experiment.
     * @param \App\Models\User|string|null $scope The scope (User model or qgid string). Defaults to qgid() if null.
     * @return void
     */
    function experiment_secondary_conversion(string $experimentName, User|string|null $scope = null): void
    {
        App::make(AbTestingService::class)->trackConversion($experimentName, $scope, 'secondary');
    }
}