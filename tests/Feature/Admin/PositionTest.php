<?php

use App\Models\{User, ElectionCycle, Position};
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $role = Role::create(['name' => 'admin']);
    $user = User::factory()->create();
    $user->assignRole($role);
    $this->actingAs($user);
});

test('admin can create a position', function () {
    ElectionCycle::factory()->create(['status' => 'active']);

    Volt::test('admin.positions')
        ->set('name', 'President')
        ->set('max_winners', 1)
        ->set('priority', 1)
        ->call('savePosition')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('positions', [
        'name' => 'President',
        'max_winners' => 1
    ]);
});

test('admin can delete a position', function () {
    $position = Position::factory()->create();

    Volt::test('admin.positions')
        ->call('deletePosition', $position->id)
        ->assertHasNoErrors();
});

test('validation prevents saving invalid position', function () {
    ElectionCycle::factory()->create(['status' => 'active']);

    Volt::test('admin.positions')
        ->set('name', '')
        ->call('savePosition')
        ->assertHasErrors('name');
});
