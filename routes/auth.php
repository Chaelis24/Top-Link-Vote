<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware('guest')->group(function () {
    Volt::route('/', 'pages.auth.login')->name('login');

    //Admin Route
    Volt::route('/admin-login', 'pages.auth.admin.admin-login')->name('admin.login');
    Volt::route('/admin-forgot-password', 'pages.auth.admin.forgot-password')->name('admin.forgot-password');
    Volt::route('/admin-reset-password/{token}', 'pages.auth.admin.reset-password')->name('admin.password.reset');

    //Student Route
    Volt::route('/verify-account', 'pages.auth.students.verify-account')->name('verify-account');
    Volt::route('/forgot-password', 'pages.auth.students.forgot-password')->name('forgot-password');
    Volt::route('/reset-password/{token}', 'pages.auth.students.reset-password')->name('password.reset');
});
