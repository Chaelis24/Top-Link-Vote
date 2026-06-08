<?php

use Livewire\Volt\Volt;

test('dashboard displays correct voting status', function () {
    $user = \App\Models\User::factory()
        ->has(\App\Models\Student::factory()->state([
            'first_name' => 'Juan',
            'middle_name' => 'Dela',
            'last_name' => 'Cruz'
        ]))
        ->create();

    $this->actingAs($user);

    Volt::test('students.dashboard')
        ->assertStatus(200)
        ->assertSee('Juan');
});
