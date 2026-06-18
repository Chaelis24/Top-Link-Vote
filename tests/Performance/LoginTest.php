<?php

use App\Models\User;
use Tests\Helpers\PerformanceHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('performance');

beforeEach(function () {
    PerformanceHelper::reset();
    $this->user = User::factory()->create([
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
    ]);
});

test('login page loads within threshold', function () {
    $threshold = config('performance.thresholds.response_time', 2.0);

    $result = PerformanceHelper::measureResponseTime(
        fn () => $this->get(route('login')),
        'login_page'
    );

    $result['response']->assertStatus(200);
    expect($result['duration_ms'])->toBeLessThan($threshold * 1000);
});

test('login submission is processed within threshold', function () {
    $threshold = config('performance.thresholds.response_time', 2.0);

    $durations = [];
    for ($i = 0; $i < 5; $i++) {
        $result = PerformanceHelper::measureResponseTime(
            fn () => $this->post(route('login'), [
                'email' => $this->user->email,
                'password' => 'password',
            ]),
            'login_submission',
            ['iteration' => $i + 1]
        );

        $durations[] = $result['duration_ms'];
    }

    $stats = PerformanceHelper::computeStats(PerformanceHelper::getMetrics('login_submission'));
    expect($stats['avg'])->toBeLessThan($threshold * 1000);
});
