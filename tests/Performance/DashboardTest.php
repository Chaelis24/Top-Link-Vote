<?php

use App\Models\User;
use App\Models\Student;
use App\Models\Course;
use App\Models\Block;
use Tests\Helpers\PerformanceHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('performance');

beforeEach(function () {
    PerformanceHelper::reset();

    $course = Course::factory()->create();
    $block = Block::factory()->create(['course_id' => $course->id]);

    $this->user = User::factory()->create();
    $this->user->assignRole('student');
    Student::factory()->create([
        'user_id' => $this->user->id,
        'course_id' => $course->id,
        'block_id' => $block->id,
    ]);

    $this->actingAs($this->user);
});

test('student dashboard loads within threshold', function () {
    $threshold = config('performance.thresholds.response_time', 2.0);

    $durations = [];
    for ($i = 0; $i < 5; $i++) {
        $result = PerformanceHelper::measureResponseTime(
            fn () => $this->get(route('student.dashboard')),
            'student_dashboard',
            ['iteration' => $i + 1]
        );

        $durations[] = $result['duration_ms'];
        $result['response']->assertStatus(200);
    }

    $stats = PerformanceHelper::computeStats(PerformanceHelper::getMetrics('student_dashboard'));
    expect($stats['avg'])->toBeLessThan($threshold * 1000);
});

test('admin dashboard loads within threshold', function () {
    $threshold = config('performance.thresholds.response_time', 2.0);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $durations = [];
    for ($i = 0; $i < 5; $i++) {
        $result = PerformanceHelper::measureResponseTime(
            fn () => $this->get(route('admin.dashboard')),
            'admin_dashboard',
            ['iteration' => $i + 1]
        );

        $durations[] = $result['duration_ms'];
    }

    $stats = PerformanceHelper::computeStats(PerformanceHelper::getMetrics('admin_dashboard'));
    expect($stats['avg'])->toBeLessThan($threshold * 1000);
});
