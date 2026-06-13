<?php

use Illuminate\Support\Facades\{Route, Session, Auth};
use Livewire\Volt\Volt;

Route::middleware(['auth', 'role:student|candidate'])
    ->prefix('students')
    ->group(function () {

        Volt::route('/dashboard', 'students.dashboard')
            ->name('student.dashboard');

        Volt::route('/profile', 'students.profile')
            ->name('student.profile');

        Volt::route('/profile-platforms', 'students.profile-platforms')
            ->name('student.platforms');

        Volt::route('/cast-vote', 'students.cast-vote')
            ->name('student.cast-vote');
    });

Route::middleware('auth')->get('/force-logout', function () {
    $isAdmin = auth()->user()?->hasRole('admin');
    Auth::logout();
    Session::invalidate();
    Session::regenerateToken();
    return redirect()->route($isAdmin ? 'admin.login' : 'login');
})->name('force.logout');

require __DIR__ . '/auth.php';
require __DIR__ . '/admin.php';
