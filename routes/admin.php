<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {

    Volt::route('/dashboard', 'admin.dashboard')->name('admin.dashboard');
    Volt::route('/candidates', 'admin.candidates')->name('admin.candidates');
    Volt::route('/election-cycle', 'admin.election-cycle')->name('admin.election-cycle');
    Volt::route('/platforms', 'admin.platforms')->name('admin.platforms');
    Volt::route('/positions', 'admin.positions')->name('admin.positions');
    Volt::route('/profiles', 'admin.profiles')->name('admin.profiles');
    Volt::route('/students', 'admin.students')->name('admin.students');
});
