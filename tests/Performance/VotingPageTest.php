<?php

use App\Models\{User, Student, Candidate, Position, ElectionCycle, Course, Setting};
use Livewire\Volt\Volt;
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
        'campaign_start' => now()->subDays(5),
        'campaign_end' => now()->subDay(),
        'filing_start' => now()->subDays(10),
        'filing_end' => now()->subDays(5),
    ]);

    Setting::updateOrCreate(['key' => 'allowVoting'], ['value' => true]);

    $position = Position::factory()->create([
        'election_cycle_id' => $cycle->id,
        'is_active' => true,
    ]);

    $this->candidateStudent = Student::factory()->create(['course_id' => $course->id]);
    $this->candidate = Candidate::factory()->create([
        'position_id' => $position->id,
        'student_id' => $this->candidateStudent->id,
        'election_cycle_id' => $cycle->id,
        'status' => 'approved',
    ]);

    $this->user = User::factory()->create();
    $this->user->assignRole('student');
    $this->student = Student::factory()->create([
        'user_id' => $this->user->id,
        'course_id' => $course->id,
        'has_voted' => false,
    ]);

    $this->actingAs($this->user);
});

test('voting page renders within threshold', function () {
    $threshold = config('performance.thresholds.response_time', 2.0);

    $durations = [];
    for ($i = 0; $i < 5; $i++) {
        $result = PerformanceHelper::measureResponseTime(
            fn () => $this->get(route('student.cast-vote')),
            'voting_page_render',
            ['iteration' => $i + 1]
        );

        $durations[] = $result['duration_ms'];
        $result['response']->assertStatus(200);
    }

    $stats = PerformanceHelper::computeStats(PerformanceHelper::getMetrics('voting_page_render'));
    expect($stats['avg'])->toBeLessThan($threshold * 1000);
});

test('vote submission is processed within threshold', function () {
    $threshold = config('performance.thresholds.response_time', 2.0);

    $durations = [];
    for ($i = 0; $i < 5; $i++) {
        Student::where('id', $this->student->id)->update(['has_voted' => false]);

        $result = PerformanceHelper::measureResponseTime(
            function () {
                $component = Volt::test('students.cast-vote');
                $component->set('selections', [$this->candidate->position_id => $this->candidate->id]);
                $component->call('nextPosition');
                $component->call('setStep', 3);
                return $component->call('submitVote');
            },
            'vote_submission',
            ['iteration' => $i + 1]
        );

        $durations[] = $result['duration_ms'];
    }

    $stats = PerformanceHelper::computeStats(PerformanceHelper::getMetrics('vote_submission'));
    expect($stats['avg'])->toBeLessThan($threshold * 1000);
});
