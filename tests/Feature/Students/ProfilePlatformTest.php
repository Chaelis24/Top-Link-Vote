<?php

use App\Models\User;
use App\Models\Candidate;
use Livewire\Volt\Volt;

test('candidate cannot update platform when voting is started', function () {
    $user = User::factory()->has(Candidate::factory())->create();
    $this->actingAs($user);

    Volt::test('students.profile-platforms')
        ->set('tagline', 'New Tagline')
        ->call('updatePlatform')
        ->assertDispatched('swal');
});
