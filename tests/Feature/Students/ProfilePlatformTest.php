<?php

use App\Models\User;
use App\Models\Candidate;
use App\Models\ElectionCycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('candidate cannot update platform when voting is started', function () {
    Role::firstOrCreate(['name' => 'candidate']);

    ElectionCycle::factory()->create([
        'status' => 'active',
        'voting_start' => now()->subDay(),
        'voting_end' => now()->addDay(),
    ]);

    $user = User::factory()->has(Candidate::factory())->create();
    $user->assignRole('candidate');
    $this->actingAs($user);

    Volt::test('students.profile-platforms')
        ->set('tagline', 'New Tagline')
        ->call('updatePlatform')
        ->assertDispatched('swal');
});
