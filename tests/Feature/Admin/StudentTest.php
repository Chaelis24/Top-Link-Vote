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
    $student = Student::factory()->create([
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'course' => 'IT'
    ]);

    Volt::test('admin.students')
        ->call('editStudent', $student->id)
        ->set('editForm.course', 'HRMT')
        ->set('editForm.status', 'suspended')
        ->call('updateStudent')
        ->assertHasNoErrors();

    expect($student->fresh()->course)->toBe('HRMT')
        ->and($student->fresh()->status)->toBe('suspended');
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
