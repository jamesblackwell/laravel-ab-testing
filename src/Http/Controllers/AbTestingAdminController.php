<?php

namespace Quizgecko\AbTesting\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Quizgecko\AbTesting\Models\Experiment;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\User; // Ensure User model is imported (or use config)

class AbTestingAdminController extends Controller
{
    /**
     * Display the A/B testing dashboard.
     */
    public function index(Request $request): View
    {
        $experiments = Experiment::query()
            ->select('experiment_name', 'variant', 'total_views', 'conversions', 'secondary_conversions', 'created_at', 'updated_at')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('experiment_name')
            ->map(function ($group, $experimentName) {
                // Use the first variant (usually 'control' or the one created first) 
                // to trigger the significance calculation for the whole group.
                $firstVariant = $group->first();
                if (!$firstVariant) {
                    return [
                        'stats' => ['error' => 'No variant data found for ' . $experimentName, 'variants' => []],
                        'active' => false,
                        'created_at' => null,
                        'updated_at' => null,
                        'duration' => 'N/A',
                    ];
                }

                $stats = $firstVariant->calculateSignificance();

                // Determine the overall timespan of the experiment group
                $firstCreatedAt = $group->min('created_at');
                $lastUpdatedAt = $group->max('updated_at');

                $term = 'hours';
                $duration = $firstCreatedAt && $lastUpdatedAt ? (int) $firstCreatedAt->diffInHours($lastUpdatedAt) : 0;

                if ($duration > 48) { // Switch to days after 2 days
                    $duration = $firstCreatedAt && $lastUpdatedAt ? (int) $firstCreatedAt->diffInDays($lastUpdatedAt) : 0;
                    $term = 'days';
                }

                $durationForHumans = $duration . ' ' . Str::plural($term, $duration);

                return [
                    'stats' => $stats,
                    // Consider active if any variant was updated recently
                    'active' => $lastUpdatedAt?->gt(now()->subHours(6)) ?? false,
                    'created_at' => $firstCreatedAt,
                    'updated_at' => $lastUpdatedAt,
                    'duration' => $durationForHumans,
                ];
            });

        // Use the package's view namespace
        return view('ab-testing::admin.ab', [
            'experiments' => $experiments,
        ]);
    }

    /**
     * Display the A/B testing debugger view and handle lookups.
     */
    public function debug(Request $request): View
    {
        $distinctExperiments = Experiment::distinct()->orderBy('experiment_name')->pluck('experiment_name');
        $lookupData = null;

        $experimentName = $request->input('experiment_name');
        $scopeIdentifier = $request->input('scope_identifier'); // e.g., "123" or "anonymous_id_abc"

        if ($experimentName && $scopeIdentifier) {
            $lookupData = [];

            // --- Cache Lookup ---
            $cachePrefix = config('ab-testing.cache_prefix', '');
            $baseCacheKey = Str::slug($experimentName) . '-' . $scopeIdentifier; // Use identifier directly for cache

            $cacheKeys = [
                'view' => "{$cachePrefix}view-" . $baseCacheKey,
                'conv-primary' => "{$cachePrefix}conv-primary-" . $baseCacheKey,
                'conv-secondary' => "{$cachePrefix}conv-secondary-" . $baseCacheKey,
            ];

            $lookupData['cache'] = [];
            foreach ($cacheKeys as $type => $key) {
                $lookupData['cache'][$type] = [
                    'key' => $key,
                    'exists' => Cache::has($key),
                    'value' => Cache::get($key),
                ];
            }

            // --- Pennant Feature Lookup ---
            // Determine scope format for Pennant query
            $userModel = config('auth.providers.users.model', User::class);
            $pennantScope = is_numeric($scopeIdentifier) ? $userModel . '|' . $scopeIdentifier : $scopeIdentifier;

            // Use DB Facade to query the features table directly
            $featureRecord = DB::table('features')
                ->where('name', $experimentName)
                ->where('scope', $pennantScope)
                ->first();

            $lookupData['feature'] = [
                'scope_searched' => $pennantScope,
                'record_found' => (bool) $featureRecord,
                'value' => $featureRecord?->value,
                'assigned_at' => $featureRecord?->created_at,
            ];
        }

        // Use the package's view namespace
        return view('ab-testing::admin.debug', [
            'distinctExperiments' => $distinctExperiments,
            'lookupData' => $lookupData,
            'inputExperimentName' => $experimentName, // Pass back input for repopulation
            'inputScopeIdentifier' => $scopeIdentifier,
        ]);
    }
}