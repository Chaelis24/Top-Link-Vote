<?php

namespace Tests\Stress;

use App\Models\{User, Student, Candidate, Position, ElectionCycle, Course, Block, Setting};
use Livewire\Volt\Volt;
use Tests\Helpers\PerformanceHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('stress');

beforeEach(function () {
    PerformanceHelper::reset();
});

test('complete election workflow handles multiple simultaneous submissions', function () {
    $threshold = config('performance.thresholds.response_time', 2.0);
    $numUsers = 20;

    $course = Course::factory()->create();
    $block = Block::factory()->create(['course_id' => $course->id]);
    $cycle = ElectionCycle::factory()->create([
        'status' => 'active',
        'voting_start' => now()->subHour(),
        'voting_end' => now()->addHour(),
        'campaign_start' => now()->subDays(5),
        'campaign_end' => now()->subDay(),
        'filing_start' => now()->subDays(10),
        'filing_end' => now()->subDays(5),
    ]);
    Setting::updateOrCreate(['key' => 'allowVoting'], ['value' => true]);

    $position = Position::factory()->create(['election_cycle_id' => $cycle->id]);
    $candidateStudent = Student::factory()->create(['course_id' => $course->id, 'block_id' => $block->id]);
    $candidate = Candidate::factory()->create([
        'position_id' => $position->id,
        'student_id' => $candidateStudent->id,
        'election_cycle_id' => $cycle->id,
        'status' => 'approved',
    ]);

    $users = [];
    for ($i = 0; $i < $numUsers; $i++) {
        $user = User::factory()->create();
        $user->assignRole('student');
        Student::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'block_id' => $block->id,
            'has_voted' => false,
        ]);
        $users[] = $user;
    }

    $benchmarks = [];

    foreach ($users as $index => $user) {
        $this->actingAs($user);

        $loginResult = PerformanceHelper::measureResponseTime(
            fn () => $this->get(route('student.dashboard')),
            'workflow_login',
            ['user' => $index]
        );
        $loginResult['response']->assertStatus(200);

        $browseResult = PerformanceHelper::measureResponseTime(
            fn () => $this->get(route('student.cast-vote')),
            'workflow_browse',
            ['user' => $index]
        );
        $browseResult['response']->assertStatus(200);

        $voteResult = PerformanceHelper::measureResponseTime(
            function () use ($candidate) {
                $component = Volt::test('students.cast-vote');
                $component->set('selections', [$candidate->position_id => $candidate->id]);
                $component->call('nextPosition');
                $component->call('setStep', 3);
                return $component->call('submitVote');
            },
            'workflow_vote',
            ['user' => $index]
        );

        $benchmarks[] = [
            'user' => $index,
            'login_ms' => $loginResult['duration_ms'],
            'browse_ms' => $browseResult['duration_ms'],
            'vote_ms' => $voteResult['duration_ms'],
        ];
    }

    foreach (['workflow_login', 'workflow_browse', 'workflow_vote'] as $metric) {
        $stats = PerformanceHelper::computeStats(PerformanceHelper::getMetrics($metric));
        expect($stats['avg'])->toBeLessThan($threshold * 1000);
    }

    $voteCount = \App\Models\Vote::count();
    expect($voteCount)->toBe($numUsers);
});

test('admin dashboard access under load is performant', function () {
    $threshold = config('performance.thresholds.response_time', 2.0);

    $course = Course::factory()->create();
    $cycle = ElectionCycle::factory()->create(['status' => 'active']);
    $position = Position::factory()->create(['election_cycle_id' => $cycle->id]);

    Candidate::factory(10)->create([
        'position_id' => $position->id,
        'election_cycle_id' => $cycle->id,
        'status' => 'approved',
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $durations = [];
    for ($i = 0; $i < 10; $i++) {
        $result = PerformanceHelper::measureResponseTime(
            fn () => $this->get(route('admin.dashboard')),
            'admin_dashboard_stress',
            ['iteration' => $i]
        );

        $durations[] = $result['duration_ms'];
        $result['response']->assertStatus(200);
    }

    $stats = PerformanceHelper::computeStats(PerformanceHelper::getMetrics('admin_dashboard_stress'));
    expect($stats['avg'])->toBeLessThan($threshold * 1000);
    expect($stats['p95'])->toBeLessThan($threshold * 1000 * 1.2);
});
