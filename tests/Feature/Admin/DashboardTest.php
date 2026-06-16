<?php

use App\Models\Course;
use App\Models\ElectionCycle;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin']);
    $this->admin = \App\Models\User::factory()->create();
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);
});

test('admin can view dashboard and see charts', function () {
    ElectionCycle::factory()->create(['status' => 'active']);
    $this->actingAs($this->admin);

    Course::create(['name' => 'IT Department']);
    Course::create(['name' => 'HRMT Department']);
    Course::create(['name' => 'ECT Department']);
    Course::create(['name' => 'HST Department']);

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
