<?php

namespace Quizgecko\AbTesting\Services;

// Update namespace for Experiment model
use Quizgecko\AbTesting\Models\Experiment;
use App\Models\User; // Assuming User model remains in the main app
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;

class AbTestingService
{
    /**
     * Get the assigned variant for a feature flag and scope.
     *
     * @param string $featureName
     * @param User|string|null $scope If null, uses abid(). Can be User object or abid string.
     * @return mixed The resolved feature value (e.g., 'control', 'test', true, false).
     */
    public function getVariant(string $featureName, User|string|null $scope = null): mixed
    {
        $scope = $this->resolveScope($scope);
        // Always call Feature::for($scope)->value($featureName).
        // Pennant will return false if the scope is null and the feature definition doesn't handle null.
        // This 'false' will be treated as 'control' by normalizeVariantInput if needed.
        return Feature::for($scope)->value($featureName);
    }

    /**
     * Track a view for an experiment.
     *
     * Ensures a view is only tracked once per scope per experiment using caching.
     * Only tracks views for 'test' or 'control' variants.
     *
     * @param string $experimentName The name of the experiment (should match the feature flag name).
     * @param User|string|null $scope If null, uses abid().
     * @param string|bool|null $variant Explicit variant (optional). If provided, must be normalized ('test'/'control'). If null, it's fetched.
     * @return void
     */
    public function trackView(string $experimentName, User|string|null $scope = null, string|bool|null $variant = null): void
    {
        try {
            $scope = $this->resolveScope($scope);

            if (empty($scope)) {
                Log::info('AbTestingService::trackView: Skipping view tracking due to empty scope.', ['experimentName' => $experimentName]);
                return;
            }

            $cacheKey = $this->getCacheKey($experimentName, $scope, 'view');

            // Check if the cache is already set (view already tracked for this scope)
            if (Cache::has($cacheKey)) {
                return;
            }

            if ($variant === null) {
                $featureResult = $this->getVariant($experimentName, $scope);
                // Normalize boolean results or use string directly
                $resolvedVariant = is_bool($featureResult) ? ($featureResult ? 'test' : 'control') : (string) $featureResult;
            } else {
                // If variant is explicitly passed, normalize it
                $resolvedVariant = $this->normalizeVariantInput($variant);
            }

            // Only increment views and set cache if the variant is 'test' or 'control'
            if (in_array($resolvedVariant, ['test', 'control'])) {
                Experiment::incrementViews($experimentName, $resolvedVariant);
                // Cache that this scope has viewed this experiment variant
                $cacheDuration = config('ab-testing.cache_duration_days', 90);
                Cache::put($cacheKey, $resolvedVariant, now()->addDays($cacheDuration)); // Store the variant seen
            } else {
                Log::debug('AbTestingService::trackView: View not tracked for non-standard variant.', [
                    'experimentName' => $experimentName,
                    'variant' => $resolvedVariant,
                    'scope' => $this->getScopeIdentifier($scope),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('AbTestingService::trackView: Error tracking view', [
                'experimentName' => $experimentName,
                'variant' => $variant ?? 'not_provided',
                'scope' => $this->getScopeIdentifier($scope),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString() // More detailed logging
            ]);
        }
    }

    /**
     * Track a conversion for an experiment.
     *
     * Ensures a conversion is only tracked if the view was previously tracked,
     * and only once per conversion type per scope per experiment using caching.
     * Only tracks conversions for 'test' or 'control' variants.
     *
     * @param string $experimentName The name of the experiment.
     * @param User|string|null $scope If null, uses abid().
     * @param string $conversionType 'primary' or 'secondary'.
     * @return void
     */
    public function trackConversion(string $experimentName, User|string|null $scope = null, string $conversionType = 'primary'): void
    {
        try {
            $scope = $this->resolveScope($scope);

            if (empty($scope)) {
                Log::info('AbTestingService::trackConversion: Skipping conversion tracking due to empty scope.', ['experimentName' => $experimentName, 'type' => $conversionType]);
                return;
            }

            $viewCacheKey = $this->getCacheKey($experimentName, $scope, 'view');
            $conversionCacheKey = $this->getCacheKey($experimentName, $scope, 'conv-' . $conversionType);

            // 1. Check if this specific conversion type has already been tracked
            if (Cache::has($conversionCacheKey)) {
                // Optional: Log info if needed for debugging repeated calls
                // Log::debug('AbTestingService::trackConversion: Conversion already tracked for this type, skipping.', [...]);
                return;
            }

            $variantSeen = null; // Initialize variantSeen

            // 2. Check if the user was actually part of the experiment (view was tracked), if required by config
            if (config('ab-testing.require_view_to_convert', true)) {
                $variantSeen = Cache::get($viewCacheKey);
                if (!$variantSeen) {
                    // If view wasn't tracked (or cache expired), we shouldn't track the conversion
                    // as we can't reliably attribute it to a variant group.
                    Log::info('AbTestingService::trackConversion: Experiment view not found in cache for scope (required), skipping conversion.', [
                        'experimentName' => $experimentName,
                        'scope' => $this->getScopeIdentifier($scope),
                        'conversionType' => $conversionType,
                    ]);
                    return;
                }
            } else {
                // If view is not required, try to get it from cache anyway for attribution,
                // but don't prevent conversion if it's missing.
                $variantSeen = Cache::get($viewCacheKey);
                // Optional: Log if view wasn't found but conversion is proceeding
                if (!$variantSeen) {
                    Log::info('AbTestingService::trackConversion: Experiment view not found in cache, but proceeding with conversion as not required.', [
                        'experimentName' => $experimentName,
                        'scope' => $this->getScopeIdentifier($scope),
                        'conversionType' => $conversionType,
                    ]);
                }
            }

            // If variantSeen is still null here (meaning view check was skipped and cache was empty),
            // we must determine the variant now to attribute the conversion.
            // This is less reliable as the user might see a different variant now than when they converted.
            if ($variantSeen === null) {
                $featureResult = $this->getVariant($experimentName, $scope);
                $variantSeen = $this->normalizeVariantInput($featureResult);
                Log::warning('AbTestingService::trackConversion: Had to re-evaluate variant for conversion tracking as view was not required or cache missed.', [
                    'experimentName' => $experimentName,
                    'scope' => $this->getScopeIdentifier($scope),
                    'evaluatedVariant' => $variantSeen,
                    'conversionType' => $conversionType,
                ]);
            }

            // We have the variant the user likely saw (either from cache or re-evaluated).
            // Only increment conversions if the determined variant was 'test' or 'control'.
            if (in_array($variantSeen, ['test', 'control'])) {
                Experiment::incrementConversions($experimentName, $variantSeen, $conversionType);

                // Set a cache entry to mark this user as converted for this specific type
                $cacheDuration = config('ab-testing.cache_duration_days', 90);
                Cache::put($conversionCacheKey, true, now()->addDays($cacheDuration));

                Log::info('AbTestingService::trackConversion: Conversion tracked successfully.', [
                    'experimentName' => $experimentName,
                    'scope' => $this->getScopeIdentifier($scope),
                    'variant' => $variantSeen,
                    'conversionType' => $conversionType,
                ]);
            } else {
                // This case should be rare if trackView filters correctly, but log just in case.
                Log::debug('AbTestingService::trackConversion: Conversion attempted for scope that viewed a non-standard variant.', [
                    'experimentName' => $experimentName,
                    'scope' => $this->getScopeIdentifier($scope),
                    'variantSeen' => $variantSeen,
                    'conversionType' => $conversionType,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('AbTestingService::trackConversion: Error tracking conversion', [
                'experimentName' => $experimentName,
                'scope' => $this->getScopeIdentifier($scope),
                'conversionType' => $conversionType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Track a secondary conversion.
     */
    public function trackSecondaryConversion(string $experimentName, User|string|null $scope = null): void
    {
        $this->trackConversion($experimentName, $scope, 'secondary');
    }

    /**
     * Resolve the scope, defaulting to abid() if null or invalid.
     *
     * @param User|string|null $scope
     * @return User|string|null The resolved scope (User object or abid string), or null if abid is unavailable.
     */
    protected function resolveScope(User|string|null $scope): User|string|null
    {
        if ($scope instanceof User) {
            return $scope;
        }
        if (is_string($scope) && !empty($scope)) {
            return $scope; // Assume it's a valid abid string if passed explicitly
        }
        // If null or invalid, try to get abid automatically
        if (function_exists('abid')) { // Check if abid() helper exists
            return abid();
        }
        Log::warning('AbTestingService: abid() helper function not available or auto_abid_handling is disabled.');
        return null; // Return null if scope cannot be resolved
    }

    /**
     * Normalize variant input to 'test' or 'control' string.
     *
     * @param string|bool $variant
     * @return string
     */
    protected function normalizeVariantInput(string|bool $variant): string
    {
        if (is_bool($variant)) {
            return $variant ? 'test' : 'control';
        }
        if (strtolower($variant) === 'true')
            return 'test';
        if (strtolower($variant) === 'false')
            return 'control';
        return (string) $variant; // Cast to string otherwise
    }

    /**
     * Generate a unique cache key for tracking.
     *
     * @param string $experimentName
     * @param User|string $scope
     * @param string $type 'view' or 'conv-primary' or 'conv-secondary'
     * @return string
     */
    protected function getCacheKey(string $experimentName, User|string $scope, string $type): string
    {
        $scopeId = $this->getScopeIdentifier($scope);
        $prefix = config('ab-testing.cache_prefix', '');
        return "{$prefix}{$type}-" . Str::slug($experimentName) . '-' . $scopeId;
    }

    /**
     * Get a consistent string identifier for the scope.
     *
     * @param User|string|null $scope
     * @return string
     */
    protected function getScopeIdentifier(User|string|null $scope): string
    {
        if ($scope instanceof User) {
            return 'user-' . $scope->id;
        }
        // Use 'abid-' prefix for non-user scopes
        return 'abid-' . (string) $scope;
    }
}