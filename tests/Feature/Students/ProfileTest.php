<?php

use App\Models\User;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'student']);
});

test('authenticated user can see profile page', function () {
    $user = User::factory()->create();
    $user->assignRole('student');
    $this->actingAs($user);

    Volt::test('students.profile')
        ->assertStatus(200);
});

test('profile can be updated', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id, 'phone' => '']);
    $user->assignRole('student');
    $this->actingAs($user);

    Volt::test('students.profile')
        ->set('phone', '09123456789')
        ->call('saveProfile')
        ->assertDispatched('swal');

    $this->assertEquals('09123456789', $student->fresh()->phone);
});
