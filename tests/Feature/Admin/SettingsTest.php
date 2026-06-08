<?php

use App\Models\User;
use Livewire\Volt\Volt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('admin can update profile information', function () {
    Volt::test('admin.settings')
        ->set('name', 'New Admin Name')
        ->set('email', 'admin@example.com')
        ->call('updateProfile')
        ->assertHasNoErrors();
});

test('admin can update password', function () {
    $oldPassword = 'password123';
    $this->user->password = Hash::make($oldPassword);
    $this->user->save();

    Volt::test('admin.settings')
        ->set('current_password', $oldPassword)
        ->set('password', 'new-secure-password')
        ->set('password_confirmation', 'new-secure-password')
        ->call('updatePassword')
        ->assertHasNoErrors();
});
