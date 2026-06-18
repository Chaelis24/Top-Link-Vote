<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Performance');

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Load');

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Stress');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Performance Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeUnderThreshold', function (float $thresholdMs) {
    return $this->toBeLessThan($thresholdMs);
});

expect()->extend('toHaveErrorRateUnder', function (float $maxErrorRate) {
    return expect($this->value)->toBeLessThanOrEqual($maxErrorRate);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function performanceHelper(): \Tests\Helpers\PerformanceHelper
{
    return new \Tests\Helpers\PerformanceHelper;
}

function runConcurrentRequests(int $count, callable $requestFn): array
{
    $results = [];
    for ($i = 0; $i < $count; $i++) {
        $results[] = $requestFn($i);
    }
    return $results;
}

function measureResponseTime(callable $fn): float
{
    $start = microtime(true);
    $fn();
    return (microtime(true) - $start) * 1000;
}

function formatDuration(float $ms): string
{
    if ($ms < 1000) {
        return round($ms, 2) . ' ms';
    }
    return round($ms / 1000, 2) . ' s';
}

function performanceReportPath(string $filename = ''): string
{
    $path = storage_path('app/performance-reports');
    if ($filename) {
        $path .= '/' . $filename;
    }
    return $path;
}
