<?php

use App\Models\{User, Student, Position, Candidate, ElectionCycle, Setting, Course};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

test('student can cast a vote successfully', function () {
    $course = Course::factory()->create();

    $cycle = ElectionCycle::factory()->create([
        'status' => 'active',
        'voting_start' => now()->subHour(),
        'voting_end' => now()->addHour(),
        'filing_start' => now()->subDays(5),
        'filing_end' => now()->subDay(),
        'campaign_start' => now()->subDay(),
        'campaign_end' => now()->addHour(),
    ]);
    Setting::updateOrCreate(['key' => 'allowVoting'], ['value' => true]);

    $studentUser = User::factory()->create();
    $student = Student::factory()->create([
        'user_id' => $studentUser->id,
        'course_id' => $course->id,
        'has_voted' => false,
    ]);

    $candidateStudent = Student::factory()->create(['course_id' => $course->id]);

    $position = Position::factory()->create(['election_cycle_id' => $cycle->id]);
    $candidate = Candidate::factory()->create([
        'position_id' => $position->id,
        'student_id' => $candidateStudent->id,
        'election_cycle_id' => $cycle->id,
    ]);

    $this->actingAs($studentUser);

    $component = Volt::test('students.cast-vote')
        ->set('selections', [$position->id => $candidate->id])
        ->call('nextPosition')
        ->call('setStep', 3)
        ->call('submitVote');

    $component->assertDispatched('swal', [
        'title' => 'Success!',
        'text' => 'Your vote has been cast.',
        'icon' => 'success',
        'timer' => 4000,
    ]);

    $this->assertDatabaseHas('votes', [
        'student_id' => $student->id,
        'candidate_id' => $candidate->id,
    ]);

    $this->assertTrue($student->fresh()->has_voted);
    $this->assertEquals(1, $candidate->fresh()->votes_count);
});
