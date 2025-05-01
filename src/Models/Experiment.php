<?php

namespace Quizgecko\AbTesting\Models;

use Illuminate\Database\Eloquent\Model;
use MathPHP\Probability\Distribution\Continuous\ChiSquared;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * 
 *
 * @property int $id
 * @property string $experiment_name
 * @property string $variant
 * @property int $total_views
 * @property int $conversions
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $secondary_conversions
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Experiment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Experiment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Experiment query()
 * @mixin \Eloquent
 */
class Experiment extends Model
{
    protected $guarded = ['id'];

    public static function incrementViews(string $experimentName, string|bool $variant)
    {
        $variant = self::normalizeVariant($variant);

        // Use firstOrCreate to ensure the record exists
        $experiment = static::firstOrCreate(
            ['experiment_name' => $experimentName, 'variant' => $variant],
            ['total_views' => 0] // Default to 0 if creating
        );

        // Now atomically increment the views for the found/created record
        $affectedRows = static::where('id', $experiment->id)
            ->increment('total_views');

        if ($affectedRows === 0) {
            Log::warning('Failed to increment experiment views', [
                'experiment_id' => $experiment->id,
                'experiment_name' => $experimentName,
                'variant' => $variant
            ]);
        }

        // Optionally reload the model if the updated count is needed immediately
        // $experiment->refresh();

        return $experiment;
    }

    public static function incrementConversions(string $experimentName, string|bool $variant, string $conversionType = 'primary')
    {
        $variant = self::normalizeVariant($variant);

        if (empty($variant)) {
            Log::error("Experiment::incrementConversions: Empty variant provided", [
                'experimentName' => $experimentName,
                'variant' => $variant,
                'conversionType' => $conversionType
            ]);
            return null;
        }

        $column = $conversionType === 'secondary' ? 'secondary_conversions' : 'conversions';

        // Use firstOrCreate to ensure the record exists
        $experiment = static::firstOrCreate(
            ['experiment_name' => $experimentName, 'variant' => $variant],
            [$column => 0] // Default to 0 if creating
        );

        // Now atomically increment the conversions for the found/created record
        $affectedRows = static::where('id', $experiment->id)
            ->increment($column);

        if ($affectedRows === 0) {
            Log::warning('Failed to increment experiment conversions', [
                'experiment_id' => $experiment->id,
                'experiment_name' => $experimentName,
                'variant' => $variant,
                'column' => $column
            ]);
        }

        // Optionally reload the model if the updated count is needed immediately
        // $experiment->refresh();

        return $experiment;
    }

    private static function normalizeVariant(string|bool $variant): string
    {
        if (is_bool($variant)) {
            return $variant ? 'test' : 'control';
        }
        // Ensure common variations like 'true'/'false' strings are handled if they somehow occur
        if (strtolower($variant) === 'true')
            return 'test';
        if (strtolower($variant) === 'false')
            return 'control';

        return $variant;
    }

    /**
     * Calculate significance stats for the experiment this variant belongs to.
     *
     * This method should ideally be called on an Experiment instance,
     * but it fetches all variants for the calculation.
     */
    public function calculateSignificance(): array
    {
        $variants = static::where('experiment_name', $this->experiment_name)->get();

        if ($variants->count() < 2) {
            return [
                'p_value' => null,
                'significant' => false,
                'p_value_secondary' => null,
                'significant_secondary' => false,
                'error' => 'Not enough variants for significance calculation (' . $variants->count() . ' found)',
                'variants' => [],
            ];
        }

        $totalViews = $variants->sum('total_views');
        $totalConversions = $variants->sum('conversions');
        $totalSecondaryConversions = $variants->sum('secondary_conversions');

        // Avoid division by zero if total views is 0
        if ($totalViews == 0) {
            return [
                'p_value' => null,
                'significant' => false,
                'p_value_secondary' => null,
                'significant_secondary' => false,
                'error' => 'No views recorded for this experiment',
                'variants' => $variants->mapWithKeys(function ($variant) {
                    return [
                        $variant->variant => [
                            'views' => 0,
                            'conversions' => $variant->conversions,
                            'secondary_conversions' => $variant->secondary_conversions,
                            'conversion_rate' => 0,
                            'secondary_conversion_rate' => 0,
                            'improvement' => 0,
                            'secondary_improvement' => 0,
                        ]
                    ];
                })->all(),
            ];
        }

        $chiSquaredPrimary = 0;
        $chiSquaredSecondary = 0;
        $results = [];
        $degreesOfFreedom = max(1, count($variants) - 1);

        // Pre-calculate overall rates
        $overallPrimaryRate = $totalConversions / $totalViews;
        $overallSecondaryRate = $totalSecondaryConversions / $totalViews;

        foreach ($variants as $variant) {
            $expectedPrimary = $variant->total_views * $overallPrimaryRate;
            $observedPrimary = $variant->conversions;
            $expectedSecondary = $variant->total_views * $overallSecondaryRate;
            $observedSecondary = $variant->secondary_conversions;

            // Calculate Chi-squared components for primary conversions
            // Observed successes
            $chiSquaredPrimary += ($expectedPrimary > 0) ? pow($observedPrimary - $expectedPrimary, 2) / $expectedPrimary : 0;
            // Observed failures (non-conversions)
            $expectedPrimaryFailures = $variant->total_views - $expectedPrimary;
            $observedPrimaryFailures = $variant->total_views - $observedPrimary;
            $chiSquaredPrimary += ($expectedPrimaryFailures > 0) ? pow($observedPrimaryFailures - $expectedPrimaryFailures, 2) / $expectedPrimaryFailures : 0;

            // Calculate Chi-squared components for secondary conversions
            // Observed successes
            $chiSquaredSecondary += ($expectedSecondary > 0) ? pow($observedSecondary - $expectedSecondary, 2) / $expectedSecondary : 0;
            // Observed failures
            $expectedSecondaryFailures = $variant->total_views - $expectedSecondary;
            $observedSecondaryFailures = $variant->total_views - $observedSecondary;
            $chiSquaredSecondary += ($expectedSecondaryFailures > 0) ? pow($observedSecondaryFailures - $expectedSecondaryFailures, 2) / $expectedSecondaryFailures : 0;

            // Store variant results
            $results[$variant->variant] = [
                'views' => $variant->total_views,
                'conversions' => $variant->conversions,
                'secondary_conversions' => $variant->secondary_conversions,
                'conversion_rate' => $variant->total_views > 0 ? $variant->conversions / $variant->total_views : 0,
                'secondary_conversion_rate' => $variant->total_views > 0 ? $variant->secondary_conversions / $variant->total_views : 0,
            ];
        }

        try {
            $chiSquaredDistribution = new ChiSquared($degreesOfFreedom);
            $pValuePrimary = $chiSquaredPrimary > 0 ? 1 - $chiSquaredDistribution->cdf($chiSquaredPrimary) : 1.0; // P-value is 1 if chi-squared is 0
            $pValueSecondary = $chiSquaredSecondary > 0 ? 1 - $chiSquaredDistribution->cdf($chiSquaredSecondary) : 1.0;
        } catch (\Exception $e) {
            Log::error('ChiSquared Calculation Error', ['message' => $e->getMessage(), 'df' => $degreesOfFreedom, 'chi_primary' => $chiSquaredPrimary, 'chi_secondary' => $chiSquaredSecondary]);
            $pValuePrimary = null;
            $pValueSecondary = null;
        }

        // Calculate improvements relative to control (if 'control' variant exists)
        $controlPrimaryRate = $results['control']['conversion_rate'] ?? null;
        $controlSecondaryRate = $results['control']['secondary_conversion_rate'] ?? null;

        foreach ($results as $variantName => $data) {
            // Primary Improvement
            if ($controlPrimaryRate !== null) {
                $results[$variantName]['improvement'] = $controlPrimaryRate != 0
                    ? (($data['conversion_rate'] - $controlPrimaryRate) / $controlPrimaryRate) * 100
                    : ($data['conversion_rate'] > 0 ? INF : 0); // Indicate infinite improvement if control is 0 and variant is > 0
            } else {
                $results[$variantName]['improvement'] = null; // Cannot calculate improvement without control
            }

            // Secondary Improvement
            if ($controlSecondaryRate !== null) {
                $results[$variantName]['secondary_improvement'] = $controlSecondaryRate != 0
                    ? (($data['secondary_conversion_rate'] - $controlSecondaryRate) / $controlSecondaryRate) * 100
                    : ($data['secondary_conversion_rate'] > 0 ? INF : 0);
            } else {
                $results[$variantName]['secondary_improvement'] = null;
            }
        }

        return [
            'p_value' => $pValuePrimary,
            'significant' => $pValuePrimary !== null && $pValuePrimary < 0.05,
            'p_value_secondary' => $pValueSecondary,
            'significant_secondary' => $pValueSecondary !== null && $pValueSecondary < 0.05,
            'variants' => $results,
        ];
    }
}