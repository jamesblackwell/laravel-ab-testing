<?php

use App\Models\User;
use Quizgecko\AbTesting\Services\AbTestingService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;

if (!function_exists('feature_flag')) {
    /**
     * Get the assigned variant for a feature flag and scope.
     *
     * @param string $featureName The name of the feature flag.
     * @param \App\Models\User|string|null $scope The scope (User model or abid string). Defaults to abid() if null.
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
     * @param \App\Models\User|string|null $scope The scope (User model or abid string). Defaults to abid() if null.
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
     * @param \App\Models\User|string|null $scope The scope (User model or abid string). Defaults to abid() if null.
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
     * @param \App\Models\User|string|null $scope The scope (User model or abid string). Defaults to abid() if null.
     * @return void
     */
    function experiment_secondary_conversion(string $experimentName, User|string|null $scope = null): void
    {
        App::make(AbTestingService::class)->trackConversion($experimentName, $scope, 'secondary');
    }
}

if (!function_exists('abid')) {
    /**
     * Get the unique A/B testing identifier (abid).
     *
     * Checks cookie, request header, and request attributes in that order.
     *
     * @return string|null
     */
    function abid(): ?string
    {
        // Ensure request() helper is available (might not be in some contexts like console)
        if (!function_exists('request')) {
            return null;
        }

        try {
            return request()->cookie('abid')
                ?? request()->header('abid') // Check header for abid (less common)
                ?? request()->attributes->get('abid')
                ?? null;
        } catch (\Exception $e) {
            // Log potential errors if request object is not fully available
            Log::warning('abid helper: Could not access request details.', ['error' => $e->getMessage()]);
            return null;
        }
    }
}

if (!function_exists('set_abid')) {
    /**
     * Set the unique A/B testing identifier (abid) cookie.
     *
     * Queues a long-lived, secure, httpOnly cookie.
     *
     * @param string $abid The identifier to set.
     * @param int $minutes Lifetime in minutes (defaults to 1 year).
     * @param string $path
     * @param string|null $domain
     * @param bool|null $secure
     * @param bool $httpOnly
     * @param bool $raw
     * @param string|null $sameSite
     * @return void
     */
    function set_abid(
        string $abid,
        int $minutes = 525600, // 1 year
        string $path = '/',
        ?string $domain = null,
        ?bool $secure = true,
        bool $httpOnly = true,
        bool $raw = false,
        ?string $sameSite = 'Lax' // Default to Lax for better security
    ): void {
        try {
            // Use config for cookie domain if available
            $domain = $domain ?? config('session.domain');
            // Use config for secure cookies if available
            $secure = $secure ?? config('session.secure');

            $cookie = Cookie::make('abid', $abid, $minutes, $path, $domain, $secure, $httpOnly, $raw, $sameSite);
            Cookie::queue($cookie);
        } catch (\Exception $e) {
            Log::error('set_abid helper: Failed to queue cookie.', [
                'error' => $e->getMessage(),
                'abid' => $abid // Be careful logging sensitive data
            ]);
        }
    }
}