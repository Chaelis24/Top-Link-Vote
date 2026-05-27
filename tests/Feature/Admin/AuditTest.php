<?php

use App\Models\ActivityLog;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::create(['name' => 'admin']);
});


test('admin can view audit trail page', function () {
    ActivityLog::factory()->create([
        'description' => 'User logged in',
        'action' => 'Login'
    ]);

    Volt::test('admin.audit-trail')
        ->assertStatus(200)
        ->assertSee('User logged in');
});

test('admin can search audit logs', function () {
    ActivityLog::factory()->create(['description' => 'Deleted a student']);
    ActivityLog::factory()->create(['description' => 'Approved a candidate']);

    Volt::test('admin.audit-trail')
        ->set('search', 'Deleted')
        ->assertSee('Deleted a student');
});
