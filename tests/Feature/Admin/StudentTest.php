<?php

use App\Models\{User, Student};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::create(['name' => 'admin']);
});

test('admin can view student list and stats', function () {
    Student::factory()->count(3)->create(['status' => 'active', 'has_voted' => false]);
    Student::factory()->create(['status' => 'active', 'has_voted' => true]);

    Volt::test('admin.students')
        ->assertStatus(200)
        ->assertSee('Total Students')
        ->assertSee('4')
        ->assertSee('Voted')
        ->assertSee('1');
});

test('admin can update student details', function () {
    $courseIT = \App\Models\Course::create(['name' => 'IT']);
    $courseHRMT = \App\Models\Course::create(['name' => 'HRMT']);

    $block = \App\Models\Block::create([
        'course_id' => $courseIT->id,
        'year_level' => 1,
        'section' => 'A'
    ]);

    $student = Student::factory()->create([
        'course_id' => $courseIT->id,
        'block_id' => $block->id,
    ]);

    Volt::test('admin.students')
        ->call('editStudent', $student->id)
        ->set('editForm.course_id', $courseHRMT->id)
        ->set('editForm.status', 'suspended')
        ->call('updateStudent')
        ->assertHasNoErrors();
});

test('admin can deactivate a student', function () {
    $student = Student::factory()->create(['status' => 'active']);

    Volt::test('admin.students')
        ->call('deleteStudent', $student->id)
        ->assertHasNoErrors();

    expect($student->fresh()->status)->toBe('inactive');
});

test('admin can trigger csv import', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user);
    Storage::fake('local');
    $file = UploadedFile::fake()->create('students.csv', 10, 'text/csv');

    Volt::test('admin.students')
        ->set('csvFile', $file)
        ->call('importCSV')
        ->assertHasNoErrors();
});
