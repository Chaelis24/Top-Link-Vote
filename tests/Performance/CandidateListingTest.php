<?php

use App\Models\{User, Student, Candidate, Position, ElectionCycle, Course};
use Tests\Helpers\PerformanceHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('performance');

beforeEach(function () {
    PerformanceHelper::reset();

    $course = Course::factory()->create();
    $cycle = ElectionCycle::factory()->create([
        'status' => 'active',
        'voting_start' => now()->subHour(),
        'voting_end' => now()->addHour(),
    ]);

    $position = Position::factory()->create([
        'election_cycle_id' => $cycle->id,
        'is_active' => true,
    ]);

    Candidate::factory(3)->create([
        'position_id' => $position->id,
        'election_cycle_id' => $cycle->id,
        'status' => 'approved',
    ]);

    $this->user = User::factory()->create();
    $this->user->assignRole('student');
    Student::factory()->create([
        'user_id' => $this->user->id,
        'course_id' => $course->id,
    ]);

    $this->actingAs($this->user);
});

test('candidate listing page loads within threshold', function () {
    $threshold = config('performance.thresholds.response_time', 2.0);

    $durations = [];
    for ($i = 0; $i < 5; $i++) {
        $result = PerformanceHelper::measureResponseTime(
            fn () => $this->get(route('student.cast-vote')),
            'candidate_listing',
            ['iteration' => $i + 1]
        );

        $durations[] = $result['duration_ms'];
    }

    $stats = PerformanceHelper::computeStats(PerformanceHelper::getMetrics('candidate_listing'));
    expect($stats['avg'])->toBeLessThan($threshold * 1000);
});

test('admin candidate list loads within threshold', function () {
    $threshold = config('performance.thresholds.response_time', 2.0);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $result = PerformanceHelper::measureResponseTime(
        fn () => $this->get(route('admin.candidates')),
        'admin_candidate_listing'
    );

    $result['response']->assertStatus(200);
    expect($result['duration_ms'])->toBeLessThan($threshold * 1000);
});

test('candidate listing with many candidates is performant', function () {
    $threshold = config('performance.thresholds.response_time', 2.0);

    $cycle = ElectionCycle::factory()->create([
        'status' => 'active',
        'voting_start' => now()->subHour(),
        'voting_end' => now()->addHour(),
    ]);

    $position = Position::factory()->create([
        'election_cycle_id' => $cycle->id,
        'is_active' => true,
    ]);

    Candidate::factory(20)->create([
        'position_id' => $position->id,
        'election_cycle_id' => $cycle->id,
        'status' => 'approved',
    ]);

    $result = PerformanceHelper::measureResponseTime(
        fn () => $this->get(route('student.cast-vote')),
        'candidate_listing_bulk'
    );

    expect($result['duration_ms'])->toBeLessThan($threshold * 1000);
});
