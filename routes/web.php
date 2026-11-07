<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\EmailChangeController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::controller(LoginController::class)->group(function () {
    Route::get('/login', 'show')->name('login');
    Route::post('/login', 'login')->middleware('throttle:10,1')->name('login.attempt');
    Route::post('/logout', 'logout')->middleware('auth')->name('logout');
});

Route::get('/email/verify/{token}', [EmailChangeController::class, 'confirm'])
    ->middleware('throttle:10,1')
    ->name('email.change.confirm');

require __DIR__.'/admin.php';
require __DIR__.'/owner.php';
require __DIR__.'/guest.php';
