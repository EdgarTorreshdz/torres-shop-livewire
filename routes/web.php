<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// Reference implementation of a role-gated admin section — see
// resources/views/livewire/admin/users-index.blade.php. Concrete projects
// built from this template replace/extend this group with their own
// admin routes, keeping the same `role:` (or `permission:`) middleware
// pattern.
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Volt::route('/usuarios', 'admin.users-index')->name('users');
});

require __DIR__.'/auth.php';
