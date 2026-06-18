<?php

use App\Models\{User, Student, Vote, Candidate, Position, ElectionCycle, Course, Role};
use Tests\Helpers\PerformanceHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('performance');

beforeEach(function () {
    PerformanceHelper::reset();
});

test('concurrent reads perform within threshold', function () {
    $threshold = config('performance.thresholds.response_time', 2.0);

    User::factory(50)->create();
    $firstUser = User::first();

    PerformanceHelper::startMeasurement('bulk_user_read');
    $users = User::all();
    $result = PerformanceHelper::endMeasurement('bulk_user_read', ['count' => $users->count()]);

    expect($result['duration_ms'])->toBeLessThan($threshold * 1000);
    expect($users->count())->toBe(50);
});

test('vote insertion throughput is acceptable', function () {
    $threshold = config('performance.thresholds.response_time', 2.0);

    $course = Course::factory()->create();
    $cycle = ElectionCycle::factory()->create([
        'status' => 'active',
        'voting_start' => now()->subHour(),
        'voting_end' => now()->addHour(),
    ]);
    $position = Position::factory()->create(['election_cycle_id' => $cycle->id]);
    $candidateStudent = Student::factory()->create(['course_id' => $course->id]);
    $candidate = Candidate::factory()->create([
        'position_id' => $position->id,
        'student_id' => $candidateStudent->id,
        'election_cycle_id' => $cycle->id,
    ]);

    $students = [];
    for ($i = 0; $i < 50; $i++) {
        $user = User::factory()->create();
        $students[] = Student::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'has_voted' => false,
        ]);
    }

    PerformanceHelper::startMeasurement('vote_bulk_insert');
    $votes = [];
    foreach ($students as $student) {
        $votes[] = Vote::create([
            'student_id' => $student->id,
            'candidate_id' => $candidate->id,
            'position_id' => $position->id,
            'election_cycle_id' => $cycle->id,
            'reference_number' => fake()->uuid(),
            'voted_at' => now(),
        ]);
    }
    $insertResult = PerformanceHelper::endMeasurement('vote_bulk_insert', ['count' => count($votes)]);

    expect($insertResult['duration_ms'])->toBeLessThan($threshold * 1000);

    PerformanceHelper::startMeasurement('vote_count_query');
    $count = Vote::where('candidate_id', $candidate->id)->count();
    $countResult = PerformanceHelper::endMeasurement('vote_count_query');

    expect($countResult['duration_ms'])->toBeLessThan($threshold * 1000);
    expect($count)->toBe(50);
});

test('complex join queries are performant', function () {
    $threshold = config('performance.thresholds.response_time', 2.0);

    $course = Course::factory()->create();
    $cycle = ElectionCycle::factory()->create(['status' => 'active']);
    $position = Position::factory()->create(['election_cycle_id' => $cycle->id]);

    Student::factory(30)->create(['course_id' => $course->id]);
    Candidate::factory(15)->create([
        'position_id' => $position->id,
        'election_cycle_id' => $cycle->id,
    ]);

    PerformanceHelper::startMeasurement('complex_join_query');
    $results = DB::table('candidates')
        ->join('students', 'candidates.student_id', '=', 'students.id')
        ->join('positions', 'candidates.position_id', '=', 'positions.id')
        ->join('election_cycles', 'candidates.election_cycle_id', '=', 'election_cycles.id')
        ->where('candidates.status', 'approved')
        ->select('candidates.*', 'students.first_name', 'students.last_name', 'positions.name as position_name')
        ->get();
    $joinResult = PerformanceHelper::endMeasurement('complex_join_query', ['count' => $results->count()]);

    expect($joinResult['duration_ms'])->toBeLessThan($threshold * 1000);
});
