<?php

use App\Models\{User, Student, Position, Candidate, ElectionCycle, Setting};
use Livewire\Volt\Volt;

test('student can cast a vote successfully', function () {
    $cycle = ElectionCycle::factory()->create(['status' => 'active', 'voting_end' => now()->addHour()]);
    Setting::updateOrCreate(['key' => 'allowVoting'], ['value' => true]);

    $studentUser = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $studentUser->id, 'has_voted' => false]);

    $position = Position::factory()->create(['election_cycle_id' => $cycle->id]);
    $candidate = Candidate::factory()->create(['position_id' => $position->id]);

    $this->actingAs($studentUser);

    $component = Volt::test('students.cast-vote')
        ->set('selections', [$position->id => $candidate->id])
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
