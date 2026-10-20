<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('permission', 'pages::permission.index')->name('permission')->can('viewAny', Permission::class);
    Route::livewire('role', 'pages::role.index')->name('role')->can('viewAny', Role::class);
    Route::livewire('user', 'pages::user.index')->name('user')->can('viewAny', User::class);
});

require __DIR__.'/settings.php';
