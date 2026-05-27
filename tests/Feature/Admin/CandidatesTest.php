<?php

use App\Models\ElectionCycle;
use App\Models\Student;
use App\Models\Candidate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Volt\Volt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->cycle = ElectionCycle::factory()->create(['status' => 'active']);
    $this->actingAs(User::factory()->create());
});

test('admin can view candidates page', function () {
    $this->actingAs(User::factory()->create());

    $component = Volt::test('admin.candidates');

    $component->assertSee('Manage');
    $component->assertSee('Candidates Profile');
});

test('candidates page renders correctly when empty', function () {
    Volt::test('admin.candidates')
        ->assertSee('No candidate found');
});

test('admin can see candidate in the table', function () {
    $student = Student::factory()->create([
        'first_name' => 'Juan',
        'last_name' => 'Cruz'
    ]);

    Candidate::factory()->create([
        'student_id' => $student->id,
        'election_cycle_id' => $this->cycle->id
    ]);

    Volt::test('admin.candidates')
        ->assertSee('Juan Cruz');
});

test('admin can search for candidates', function () {
    $student = Student::factory()->create(['first_name' => 'Alice']);
    Candidate::factory()->create([
        'student_id' => $student->id,
        'election_cycle_id' => $this->cycle->id
    ]);

    Volt::test('admin.candidates')
        ->set('search', 'Alice')
        ->assertSee('Alice');
});

test('admin can import candidates via csv', function () {
    Storage::fake('public');

    // Kailangan ng student na may specific ID na babasahin ng import script
    $student = Student::factory()->create(['student_id' => '1234567890']);

    // CSV format base sa logic ng iyong import: $row[0] = student_id, $row[1] = position_name
    $csvContent = "student_id,position_name\n1234567890,President";
    $file = UploadedFile::fake()->createWithContent('candidates.csv', $csvContent);

    Volt::test('admin.candidates')
        ->set('csvFile', $file)
        ->call('importCandidates')
        ->assertHasNoErrors();

    // I-verify sa database base sa ginawa ng import logic
    $this->assertDatabaseHas('candidates', [
        'student_id' => $student->id,
        'election_cycle_id' => $this->cycle->id,
        'status' => 'approved'
    ]);
});
