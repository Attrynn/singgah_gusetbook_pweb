<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\EmailChangeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Workspace\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::controller(LoginController::class)->group(function () {
    Route::get('/login', 'show')->name('login');
    Route::post('/login', 'login')->middleware('throttle:10,1')->name('login.attempt');
    Route::post('/logout', 'logout')->middleware('auth')->name('logout');
});

Route::get('/email/verify/{token}', [EmailChangeController::class, 'show'])
    ->middleware('throttle:10,1')
    ->name('email.change.confirm');

Route::post('/email/verify/{token}', [EmailChangeController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('email.change.store');

// Workspace registration
Route::get('/register', [RegisterController::class, 'show'])
    ->middleware('guest')
    ->name('register');
Route::post('/register', [RegisterController::class, 'store'])
    ->middleware(['guest', 'throttle:5,1'])
    ->name('register.store');

// Create additional workspace (authenticated users)
Route::prefix('workspace')->name('workspace.')->middleware(['auth', 'auth.session', 'active'])->group(function () {
    Route::get('/create', [WorkspaceController::class, 'create'])->name('create');
    Route::post('/create', [WorkspaceController::class, 'store'])->name('store');
});

require __DIR__.'/admin.php';
require __DIR__.'/owner.php';
require __DIR__.'/guest.php';
