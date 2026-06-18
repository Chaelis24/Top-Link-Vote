<?php

use App\Models\{User, Student, Course};
use Tests\Helpers\PerformanceHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('performance');

beforeEach(function () {
    PerformanceHelper::reset();
});

test('force-logout endpoint is performant', function () {
    $threshold = config('performance.thresholds.response_time', 2.0);

    $course = Course::factory()->create();
    $user = User::factory()->create();
    $user->assignRole('student');
    Student::factory()->create([
        'user_id' => $user->id,
        'course_id' => $course->id,
    ]);
    $this->actingAs($user);

    $result = PerformanceHelper::measureResponseTime(
        fn () => $this->get(route('force.logout')),
        'api_force_logout'
    );

    expect($result['duration_ms'])->toBeLessThan($threshold * 1000);
});

test('secure-logout endpoint is performant', function () {
    $threshold = config('performance.thresholds.response_time', 2.0);

    $course = Course::factory()->create();
    $user = User::factory()->create();
    $user->assignRole('student');
    Student::factory()->create([
        'user_id' => $user->id,
        'course_id' => $course->id,
    ]);
    $this->actingAs($user);

    $result = PerformanceHelper::measureResponseTime(
        fn () => $this->get(route('secure.logout')),
        'api_secure_logout'
    );

    expect($result['duration_ms'])->toBeLessThan($threshold * 1000);
});

test('login page GET is performant', function () {
    $threshold = config('performance.thresholds.response_time', 2.0);

    $durations = [];
    for ($i = 0; $i < 10; $i++) {
        $result = PerformanceHelper::measureResponseTime(
            fn () => $this->get(route('login')),
            'api_login_page',
            ['iteration' => $i + 1]
        );

        $durations[] = $result['duration_ms'];
        $result['response']->assertStatus(200);
    }

    $stats = PerformanceHelper::computeStats(PerformanceHelper::getMetrics('api_login_page'));
    expect($stats['avg'])->toBeLessThan($threshold * 1000);
});
