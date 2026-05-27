<?php

use App\Models\User;
use App\Models\ElectionCycle;
use Livewire\Volt\Volt;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']);

    $this->admin = \App\Models\User::factory()->create();

    $this->admin->assignRole('admin');

    $this->actingAs($this->admin);
});

test('admin can view dashboard and see charts', function () {
    // 1. Setup
    ElectionCycle::factory()->create(['status' => 'active']);
    $this->actingAs($this->admin);

    // 2. Test
    Volt::test('admin.dashboard')
        ->assertStatus(200)
        ->assertSee('Total Voters')
        ->assertSee('Total Candidates')
        ->assertSee('Voter Turnout')

        ->assertSee('Voter Turnout Trends')
        ->assertSee('Year Level Breakdown')

        ->assertSee('IT Department')
        ->assertSee('HRMT Department')
        ->assertSee('ECT Department')
        ->assertSee('HST Department')

        ->assertSee('Live Vote Tallying');
});

test('non-admin cannot access admin dashboard', function () {
    $student = User::factory()->create();

    $this->actingAs($student);

    $this->get('/admin/dashboard')->assertStatus(403);
});
