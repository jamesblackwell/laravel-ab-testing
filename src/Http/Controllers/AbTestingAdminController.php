<?php

namespace Quizgecko\AbTesting\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Quizgecko\AbTesting\Models\Experiment;
use Illuminate\Support\Str;

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
}