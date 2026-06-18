<?php

namespace Tests\Stress;

use App\Models\{User, Student, Course, Block};
use Tests\Helpers\PerformanceHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('stress');

beforeEach(function () {
    PerformanceHelper::reset();
});

test('application handles gradually increasing load', function () {
    $threshold = config('performance.thresholds.response_time', 2.0);
    $responseTimeThreshold = config('performance.stress.unacceptable_response_time', 5.0);
    $errorThreshold = config('performance.stress.error_threshold_percent', 5.0);

    $course = Course::factory()->create();
    $block = Block::factory()->create(['course_id' => $course->id]);
    $levels = [5, 10, 25, 50, 100];
    $breakingPoint = null;
    $maxStableUsers = 0;

    foreach ($levels as $numUsers) {
        $errors = 0;
        $durations = [];

        $users = [];
        for ($i = 0; $i < $numUsers; $i++) {
            $user = User::factory()->create();
            $user->assignRole('student');
            Student::factory()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'block_id' => $block->id,
            ]);
            $users[] = $user;
        }

        foreach ($users as $user) {
            $this->actingAs($user);

            $result = PerformanceHelper::measureResponseTime(
                fn () => $this->get(route('student.dashboard')),
                "stress_level_{$numUsers}",
                ['concurrent_users' => $numUsers]
            );

            $durations[] = $result['duration_ms'];

            if ($result['response']->status() >= 500) {
                $errors++;
            }
        }

        $avgDuration = count($durations) > 0 ? array_sum($durations) / count($durations) : 0;
        $maxDuration = count($durations) > 0 ? max($durations) : 0;
        $errorRate = count($users) > 0 ? ($errors / count($users)) * 100 : 0;

        PerformanceHelper::recordMetric("stress_level_{$numUsers}", [
            'concurrent_users' => $numUsers,
            'avg_response_ms' => round($avgDuration, 2),
            'max_response_ms' => round($maxDuration, 2),
            'error_rate' => round($errorRate, 2),
            'threshold_breached' => $avgDuration > ($responseTimeThreshold * 1000) || $errorRate > $errorThreshold,
        ]);

        if ($avgDuration > ($responseTimeThreshold * 1000) || $errorRate > $errorThreshold) {
            if ($breakingPoint === null) {
                $breakingPoint = $numUsers;
            }
        } else {
            $maxStableUsers = $numUsers;
        }

        if ($avgDuration > ($threshold * 1000 * 3)) {
            break;
        }
    }

    PerformanceHelper::recordMetric('stress_summary', [
        'max_stable_users' => $maxStableUsers,
        'breaking_point' => $breakingPoint ?? 'not_reached',
        'response_time_threshold_ms' => $responseTimeThreshold * 1000,
        'error_threshold_percent' => $errorThreshold,
    ]);

    expect($maxStableUsers)->toBeGreaterThan(0);
});

test('recovery after high load is successful', function () {
    $course = Course::factory()->create();
    $numUsers = 30;

    $users = [];
    for ($i = 0; $i < $numUsers; $i++) {
        $user = User::factory()->create();
        $user->assignRole('student');
        Student::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);
        $users[] = $user;
    }

    foreach ($users as $user) {
        $this->actingAs($user);
        $this->get(route('student.dashboard'));
    }

    $recoveryUser = User::factory()->create();
    $recoveryUser->assignRole('student');
    Student::factory()->create([
        'user_id' => $recoveryUser->id,
        'course_id' => $course->id,
    ]);
    $this->actingAs($recoveryUser);

    $result = PerformanceHelper::measureResponseTime(
        fn () => $this->get(route('student.dashboard')),
        'recovery_request'
    );

    expect($result['response']->status())->toBe(200);
    expect($result['duration_ms'])->toBeLessThan(config('performance.thresholds.response_time', 2.0) * 1000);
});
