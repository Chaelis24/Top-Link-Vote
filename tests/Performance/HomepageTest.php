<?php

use Tests\Helpers\PerformanceHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('performance');

beforeEach(function () {
    PerformanceHelper::setCategory('homepage');
    PerformanceHelper::reset();
});

test('homepage response time is under threshold', function () {
    $threshold = config('performance.thresholds.response_time', 2.0);

    $durations = [];
    for ($i = 0; $i < 10; $i++) {
        $result = PerformanceHelper::measureResponseTime(
            fn () => $this->get(route('login')),
            'homepage',
            ['iteration' => $i + 1]
        );

        $durations[] = $result['duration_ms'];
        $result['response']->assertStatus(200);
    }

    $stats = PerformanceHelper::computeStats(PerformanceHelper::getMetrics('homepage'));

    expect($stats['avg'])->toBeLessThan($threshold * 1000);
    expect($stats['max'])->toBeLessThan($threshold * 1000 * 1.5);
});
