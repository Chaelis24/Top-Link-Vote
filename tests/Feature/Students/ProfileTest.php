<?php

use App\Models\User;
use App\Models\Student;
use Livewire\Volt\Volt;

test('authenticated user can see profile page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('students.profile')
        ->assertStatus(200);
});

test('profile can be updated', function () {
    $user = User::factory()->has(Student::factory())->create();
    $this->actingAs($user);

    Volt::test('students.profile')
        ->set('phone', '09123456789')
        ->call('saveProfile')
        ->assertDispatched('swal');

    $this->assertEquals('09123456789', $user->fresh()->student->phone);
});
