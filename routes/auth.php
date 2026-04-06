<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware('guest')->group(function () {
    Volt::route('/', 'pages.auth.login')->name('login');
    Volt::route('/admin-login', 'pages.auth.admin-login')->name('admin.login');
    Volt::route('/verify-account', 'pages.auth.verify-account')->name('verify-account');
    Volt::route('/forgot-password', 'pages.auth.forgot-password')->name('forgot-password');
});
