<?php

namespace Tests\Stress;

use App\Models\{User, Student, Vote, Candidate, Position, ElectionCycle, Course, Block};
use Illuminate\Support\Facades\DB;
use Tests\Helpers\PerformanceHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('stress');

beforeEach(function () {
    PerformanceHelper::reset();
});

test('handles concurrent database reads efficiently', function () {
    $threshold = config('performance.thresholds.response_time', 2.0);

    $course = Course::factory()->create();
    $block = Block::factory()->create(['course_id' => $course->id]);
    Student::factory(100)->create(['course_id' => $course->id, 'block_id' => $block->id]);

    DB::beginTransaction();
    try {
        PerformanceHelper::startMeasurement('concurrent_reads');
        $results = DB::select('SELECT * FROM students WHERE course_id = ?', [$course->id]);
        $readResult = PerformanceHelper::endMeasurement('concurrent_reads', ['rows' => count($results)]);

        expect($readResult['duration_ms'])->toBeLessThan($threshold * 1000);
        expect(count($results))->toBe(100);
    } finally {
        DB::rollBack();
    }
});

test('handles bulk vote insertion throughput', function () {
    $threshold = config('performance.thresholds.response_time', 2.0);
    $batchSize = 100;

    $course = Course::factory()->create();
    $block = Block::factory()->create(['course_id' => $course->id]);
    $cycle = ElectionCycle::factory()->create(['status' => 'active']);
    $position = Position::factory()->create(['election_cycle_id' => $cycle->id]);
    $candidateStudent = Student::factory()->create(['course_id' => $course->id, 'block_id' => $block->id]);
    $candidate = Candidate::factory()->create([
        'position_id' => $position->id,
        'student_id' => $candidateStudent->id,
        'election_cycle_id' => $cycle->id,
    ]);

    $students = [];
    for ($i = 0; $i < $batchSize; $i++) {
        $user = User::factory()->create();
        $students[] = Student::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'block_id' => $block->id,
            'has_voted' => false,
        ]);
    }

    PerformanceHelper::startMeasurement('bulk_vote_insert');
    $now = now();
    $insertData = [];
    foreach ($students as $student) {
        $insertData[] = [
            'student_id' => $student->id,
            'candidate_id' => $candidate->id,
            'position_id' => $position->id,
            'election_cycle_id' => $cycle->id,
            'reference_number' => (string) \Illuminate\Support\Str::uuid(),
            'voted_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
    Vote::insert($insertData);
    $insertResult = PerformanceHelper::endMeasurement('bulk_vote_insert', ['count' => $batchSize]);

    PerformanceHelper::startMeasurement('vote_count_query');
    $count = Vote::where('candidate_id', $candidate->id)->count();
    $countResult = PerformanceHelper::endMeasurement('vote_count_query');

    expect($insertResult['duration_ms'])->toBeLessThan($threshold * 1000);
    expect($countResult['duration_ms'])->toBeLessThan($threshold * 1000);
    expect($count)->toBe($batchSize);
});

test('query bottleneck detection - unoptimized queries', function () {
    $threshold = config('performance.thresholds.response_time', 2.0);

    $course = Course::factory()->create();
    $block = Block::factory()->create(['course_id' => $course->id]);
    Student::factory(50)->create(['course_id' => $course->id, 'block_id' => $block->id]);
    $cycle = ElectionCycle::factory()->create(['status' => 'active']);
    $position = Position::factory()->create(['election_cycle_id' => $cycle->id]);
    Candidate::factory(20)->create([
        'position_id' => $position->id,
        'election_cycle_id' => $cycle->id,
    ]);

    PerformanceHelper::startMeasurement('nested_loop_query');
    $students = Student::where('course_id', $course->id)->get();
    $candidates = Candidate::where('election_cycle_id', $cycle->id)->get();
    $result = [];
    foreach ($students as $student) {
        foreach ($candidates as $candidate) {
            if ($student->id === $candidate->student_id) {
                $result[] = ['student' => $student->id, 'candidate' => $candidate->id];
            }
        }
    }
    $queryResult = PerformanceHelper::endMeasurement('nested_loop_query', [
        'students' => $students->count(),
        'candidates' => $candidates->count(),
        'matches' => count($result),
    ]);

    expect($queryResult['duration_ms'])->toBeLessThan($threshold * 1000 * 2);
});

test('lock contention under concurrent vote inserts', function () {
    $threshold = config('performance.thresholds.response_time', 2.0);

    $course = Course::factory()->create();
    $block = Block::factory()->create(['course_id' => $course->id]);
    $cycle = ElectionCycle::factory()->create(['status' => 'active']);
    $position = Position::factory()->create(['election_cycle_id' => $cycle->id]);

    $candidateStudent = Student::factory()->create(['course_id' => $course->id, 'block_id' => $block->id]);
    $candidate = Candidate::factory()->create([
        'position_id' => $position->id,
        'student_id' => $candidateStudent->id,
        'election_cycle_id' => $cycle->id,
    ]);

    $numVoters = 30;
    $successfulInserts = 0;

    for ($i = 0; $i < $numVoters; $i++) {
        $user = User::factory()->create();
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'block_id' => $block->id,
            'has_voted' => false,
        ]);

        PerformanceHelper::startMeasurement("vote_insert_{$i}");
        try {
            $vote = Vote::create([
                'student_id' => $student->id,
                'candidate_id' => $candidate->id,
                'position_id' => $position->id,
                'election_cycle_id' => $cycle->id,
                'reference_number' => (string) \Illuminate\Support\Str::uuid(),
                'voted_at' => now(),
            ]);
            PerformanceHelper::endMeasurement("vote_insert_{$i}", ['success' => true]);
            $successfulInserts++;
        } catch (\Exception $e) {
            PerformanceHelper::endMeasurement("vote_insert_{$i}", ['success' => false, 'error' => $e->getMessage()]);
        }
    }

    expect($successfulInserts)->toBe($numVoters);
});
