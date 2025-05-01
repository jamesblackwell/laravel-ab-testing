<?php

namespace Quizgecko\AbTesting\Tests\Unit;

use Quizgecko\AbTesting\Models\Experiment; // Updated
use App\Models\User; // Assuming User model remains in the main app
use Quizgecko\AbTesting\Services\AbTestingService; // Updated
use Illuminate\Support\Facades\Cache;
use Laravel\Pennant\Feature;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Orchestra\Testbench\TestCase as OrchestraTestCase; // Use Testbench TestCase

/**
 * Custom test class that extends AbTestingService 
 * and makes the resolveScope method public for testing
 */
class TestAbTestingService extends AbTestingService
{
    // Note: This override might need adjustment depending on how the package handles default scope.
    // If the package's resolveScope handles qgid internally, this might not be necessary
    // or might need to use the actual package mechanism if available in tests.
    public function resolveScope(User|string|null $scope): User|string|null
    {
        return $scope ?? 'test-qgid-123';
    }
}

// Use Testbench TestCase for package testing
class AbTestingServiceTest extends OrchestraTestCase
{
    // Use Mockery integration for automatic teardown
    use MockeryPHPUnitIntegration;

    // Load the package service provider
    protected function getPackageProviders($app)
    {
        return [
            \Quizgecko\AbTesting\Providers\AbTestingServiceProvider::class,
        ];
    }

    // Define environment setup if needed (e.g., for facades)
    protected function getEnvironmentSetUp($app)
    {
        // Setup facade aliases if needed
        $app['config']->set('app.aliases', [
            'Cache' => \Illuminate\Support\Facades\Cache::class,
            'Feature' => \Laravel\Pennant\Feature::class,
        ]);

        // Setup cache driver for testing
        $app['config']->set('cache.default', 'array');

        // Mock the Experiment model if needed for alias
        if (!class_exists('Experiment')) {
            class_alias(Experiment::class, 'Experiment');
        }
    }


    public function test_get_variant_returns_feature_value()
    {
        $testScope = 'test-scope-123';

        // Arrange
        // Mock the service directly from the package namespace
        $service = Mockery::mock(AbTestingService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('resolveScope')
            ->with('some-scope')
            ->once()
            ->andReturn($testScope);

        // Use the Feature facade provided by Testbench environment
        Feature::shouldReceive('for')
            ->with($testScope)
            ->once()
            ->andReturnSelf(); // Allow chaining ->value()

        Feature::shouldReceive('value')
            ->with('test-feature')
            ->once()
            ->andReturn('test-variant');

        // Act
        $result = $service->getVariant('test-feature', 'some-scope');

        // Assert
        $this->assertEquals('test-variant', $result);
    }

    public function test_track_view_sets_cache_and_increments_views()
    {
        $testScope = 'view-scope-456';
        $experimentName = 'view-experiment';
        $cacheKey = 'exp-view-experiment-view-scope-456'; // Keep cache key consistent if service generates it this way

        // Arrange
        $service = Mockery::mock(AbTestingService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();

        // Expect resolveScope and getVariant to be called
        $service->shouldReceive('resolveScope')->once()->with(null)->andReturn($testScope); // Assuming null scope defaults
        $service->shouldReceive('getVariant')->once()->with($experimentName, $testScope)->andReturn('test');

        // Mock getCacheKey directly or ensure the service calculates it consistently
        // If getCacheKey is protected, you might need to mock it or test its output separately.
        // For simplicity, let's assume the key generation is stable or tested elsewhere.
        // We will mock Cache directly based on the expected key.

        // Mock Cache facade interactions
        Cache::shouldReceive('getCacheKey')->once()->with($experimentName, $testScope, 'view')->andReturn($cacheKey);
        Cache::shouldReceive('has')->with($cacheKey)->once()->andReturn(false);
        Cache::shouldReceive('put')->with($cacheKey, 'test', Mockery::any())->once();

        // Mock the Experiment model from the package
        $experimentMock = Mockery::mock('alias:' . Experiment::class);
        $experimentMock->shouldReceive('incrementViews')->with($experimentName, 'test')->once();

        // Act
        $service->trackView($experimentName); // Pass null scope to trigger default resolution

        // Assertions are checked by Mockery
        $this->assertTrue(true);
    }

    public function test_track_view_does_nothing_if_already_cached()
    {
        $testScope = 'cached-view-scope';
        $experimentName = 'cached-view-exp';
        $cacheKey = 'exp-cached-view-exp-cached-view-scope'; // Keep cache key consistent

        // Arrange
        $service = Mockery::mock(AbTestingService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('resolveScope')->once()->with(null)->andReturn($testScope);

        // Mock getCacheKey directly if needed
        Cache::shouldReceive('getCacheKey')->once()->with($experimentName, $testScope, 'view')->andReturn($cacheKey);
        Cache::shouldReceive('has')->with($cacheKey)->once()->andReturn(true); // Indicate cache hit

        // Ensure other methods are NOT called
        $service->shouldNotReceive('getVariant');
        Cache::shouldNotReceive('put');
        Mockery::mock('alias:' . Experiment::class)->shouldNotReceive('incrementViews');

        // Act
        $service->trackView($experimentName);

        // Assert
        $this->assertTrue(true);
    }

    public function test_track_conversion_increments_and_caches()
    {
        $testScope = 'conversion-scope-789';
        $experimentName = 'conversion-exp';
        $viewCacheKey = 'exp-conversion-exp-conversion-scope-789';
        $convCacheKey = 'exp-conv-primary-conversion-exp-conversion-scope-789';

        // Arrange
        $service = Mockery::mock(AbTestingService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('resolveScope')->once()->with(null)->andReturn($testScope);

        // Mock Cache facade interactions based on expected keys
        Cache::shouldReceive('getCacheKey')->once()->with($experimentName, $testScope, 'view')->andReturn($viewCacheKey);
        Cache::shouldReceive('getCacheKey')->once()->with($experimentName, $testScope, 'conv-primary')->andReturn($convCacheKey);

        Cache::shouldReceive('has')->with($convCacheKey)->once()->andReturn(false); // Not converted yet
        Cache::shouldReceive('get')->with($viewCacheKey)->once()->andReturn('control'); // Get variant from view cache
        Cache::shouldReceive('put')->with($convCacheKey, true, Mockery::any())->once(); // Set conversion cache

        // Mock the Experiment model from the package
        $experimentMock = Mockery::mock('alias:' . Experiment::class);
        $experimentMock->shouldReceive('incrementConversions')->with($experimentName, 'control', 'primary')->once();

        // Act
        $service->trackConversion($experimentName);

        // Assert
        $this->assertTrue(true);
    }

    public function test_track_conversion_skips_when_no_view_in_cache()
    {
        $testScope = 'no-view-scope';
        $experimentName = 'no-view-exp';
        $viewCacheKey = 'exp-no-view-exp-no-view-scope';
        $convCacheKey = 'exp-conv-primary-no-view-exp-no-view-scope';

        // Arrange
        $service = Mockery::mock(AbTestingService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('resolveScope')->once()->with(null)->andReturn($testScope);

        // Mock Cache facade interactions
        Cache::shouldReceive('getCacheKey')->once()->with($experimentName, $testScope, 'view')->andReturn($viewCacheKey);
        Cache::shouldReceive('getCacheKey')->once()->with($experimentName, $testScope, 'conv-primary')->andReturn($convCacheKey);

        Cache::shouldReceive('get')->with($viewCacheKey)->once()->andReturn(null); // View does NOT exist in cache
        Cache::shouldReceive('has')->with($convCacheKey)->once()->andReturn(false); // Ensure conversion cache check happens

        // Ensure conversion tracking methods are NOT called
        Cache::shouldNotReceive('put');
        Mockery::mock('alias:' . Experiment::class)->shouldNotReceive('incrementConversions');

        // Act
        $service->trackConversion($experimentName);

        // Assert
        $this->assertTrue(true);
    }

    public function test_track_conversion_skips_if_already_converted()
    {
        $testScope = 'already-converted-scope';
        $experimentName = 'already-converted-exp';
        $viewCacheKey = 'exp-already-converted-exp-already-converted-scope';
        $convCacheKey = 'exp-conv-primary-already-converted-exp-already-converted-scope';

        // Arrange
        $service = Mockery::mock(AbTestingService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('resolveScope')->once()->with(null)->andReturn($testScope);

        // Mock Cache facade interactions
        Cache::shouldReceive('getCacheKey')->once()->with($experimentName, $testScope, 'view')->andReturn($viewCacheKey);
        Cache::shouldReceive('getCacheKey')->once()->with($experimentName, $testScope, 'conv-primary')->andReturn($convCacheKey);

        Cache::shouldReceive('has')->with($convCacheKey)->once()->andReturn(true); // Conversion exists

        // Ensure other methods are NOT called
        Cache::shouldNotReceive('get')->with($viewCacheKey); // Don't need to check view cache if conversion exists
        Cache::shouldNotReceive('put');
        Mockery::mock('alias:' . Experiment::class)->shouldNotReceive('incrementConversions');

        // Act
        $service->trackConversion($experimentName);

        // Assert
        $this->assertTrue(true);
    }
}