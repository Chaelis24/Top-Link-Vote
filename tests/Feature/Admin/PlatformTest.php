<?php

use App\Models\{Platform, Candidate, ElectionCycle, Position, Role};
use Livewire\Volt\Volt;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::create(['name' => 'admin']);
});

test('admin can view platforms and see candidate names', function () {
    $cycle = ElectionCycle::factory()->create(['status' => 'active']);
    $position = Position::factory()->create(['election_cycle_id' => $cycle->id]);
    $candidate = Candidate::factory()->create(['election_cycle_id' => $cycle->id, 'position_id' => $position->id]);
    $platform = Platform::factory()->create(['candidate_id' => $candidate->id, 'title' => 'My Manifesto']);

    Volt::test('admin.platforms')
        ->assertStatus(200)
        ->assertSee('My Manifesto')
        ->assertSee($candidate->student->first_name);
});
