<?php

namespace Tests\Load;

use App\Models\{User, Student, Course, Block, Setting};
use Tests\Helpers\PerformanceHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('load');

beforeEach(function () {
    PerformanceHelper::reset();
});

test('simulates 10 concurrent users', function () {
    runLoadTest($this, 10);
});

test('simulates 50 concurrent users', function () {
    runLoadTest($this, 50);
});

test('simulates 100 concurrent users', function () {
    runLoadTest($this, 100);
});

function runLoadTest($testCase, int $concurrentUsers): void
{
    $threshold = config('performance.thresholds.response_time', 2.0);
    $requestsPerUser = 2;

    $course = Course::factory()->create();
    $block = Block::factory()->create(['course_id' => $course->id]);
    Setting::updateOrCreate(['key' => 'allowVoting'], ['value' => true]);

    $errors = 0;
    $durations = [];

    for ($i = 0; $i < $concurrentUsers; $i++) {
        $user = User::factory()->create();
        $user->assignRole('student');
        Student::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'block_id' => $block->id,
        ]);

        $testCase->actingAs($user);

        for ($r = 0; $r < $requestsPerUser; $r++) {
            $result = PerformanceHelper::measureResponseTime(
                fn () => $testCase->get(route('student.dashboard')),
                "load_test_{$concurrentUsers}_users",
                ['user_index' => $i, 'request' => $r]
            );

            $durations[] = $result['duration_ms'];

            if ($result['response']->status() >= 400) {
                $errors++;
            }
        }
    }

    $avgDuration = count($durations) > 0 ? array_sum($durations) / count($durations) : 0;
    $totalRequests = $concurrentUsers * $requestsPerUser;
    $errorRate = $totalRequests > 0 ? ($errors / $totalRequests) * 100 : 0;

    PerformanceHelper::recordMetric("load_{$concurrentUsers}_users", [
        'type' => 'load_test',
        'concurrent_users' => $concurrentUsers,
        'total_requests' => $totalRequests,
        'avg_response_ms' => round($avgDuration, 2),
        'error_rate' => round($errorRate, 2),
        'success_rate' => round(100 - $errorRate, 2),
        'threshold' => $threshold * 1000,
        'threshold_breached' => $avgDuration > ($threshold * 1000) || $errorRate > 1,
    ]);

    expect($avgDuration)->toBeLessThan($threshold * 1000);
    expect($errorRate)->toBeLessThanOrEqual(1);
}
