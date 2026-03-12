<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Volt::route('/', 'pages.auth.login')->name('login');
Volt::route('/verify-account', 'pages.auth.verify-account')->name('verify-account');
Volt::route('/forgot-password', 'pages.auth.forgot-password')->name('forgot-password');

Route::middleware(['auth', 'role:student'])
    ->prefix('students')
    ->group(function () {

        Volt::route('/dashboard', 'students.dashboard')
            ->name('student.dashboard');

        Volt::route('/profile', 'students.profile')
            ->name('student.profile');

        Volt::route('/profile-candidates', 'students.profile-candidates')
            ->name('student.candidates');

        Volt::route('/platforms', 'students.platforms')
            ->name('student.platforms');

        Volt::route('/cast-vote', 'students.cast-vote')
            ->name('student.cast-vote');
    });


Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__ . '/auth.php';
require __DIR__ . '/admin.php';
