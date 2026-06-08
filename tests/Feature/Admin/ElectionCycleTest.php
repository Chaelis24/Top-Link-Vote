<?php

use App\Models\ElectionCycle;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::create(['name' => 'admin']);
});

test('admin can update election cycle timeline', function () {
    $cycle = ElectionCycle::factory()->create(['status' => 'active']);

    $cycle = ElectionCycle::factory()->create([
        'filing_start' => now()->subDays(2),
        'filing_end' => now()->subDays(1),
        'campaign_start' => now()->subDays(1),
        'campaign_end' => now()->addDays(1),
        'voting_start' => now()->addDays(1),
        'voting_end' => now()->addDays(2),
    ]);


    Volt::test('admin.election-cycle')
        ->set('filing_start', now()->format('Y-m-d'))
        ->set('filing_end', now()->addDays(1)->format('Y-m-d'))
        ->set('campaign_start', now()->addDays(2)->format('Y-m-d'))
        ->set('campaign_end', now()->addDays(3)->format('Y-m-d'))
        ->set('start_date', now()->addDays(4)->format('Y-m-d'))
        ->set('end_date', now()->addDays(5)->format('Y-m-d'))
        ->call('updateDates')
        ->assertHasNoErrors();
});

test('admin can toggle settings', function () {
    Volt::test('admin.election-cycle')
        ->call('toggleSetting', 'showResults')
        ->assertHasNoErrors();
});
