<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\EmailChangeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Workspace\WorkspaceController;
use App\Http\Controllers\Workspace\WorkspaceInvitationController;
use App\Http\Controllers\Workspace\WorkspaceMemberController;
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

// Invitation acceptance
Route::get('/invitations/{token}', [WorkspaceInvitationController::class, 'show'])
    ->middleware('throttle:30,1')->name('invitations.workspace.accept');
Route::post('/invitations/{token}/accept', [WorkspaceInvitationController::class, 'accept'])
    ->middleware(['auth', 'throttle:10,1'])->name('invitations.workspace.accept.store');
Route::post('/invitations/{token}/register', [WorkspaceInvitationController::class, 'register'])
    ->middleware(['guest', 'throttle:5,1'])->name('invitations.workspace.accept.register');

// Workspace member + invitation management (context-based)
Route::prefix('workspace/{organization}')->name('workspace.')
    ->middleware(['auth', 'auth.session', 'active', 'org.context', 'org.member'])
    ->group(function () {
        Route::get('/', [WorkspaceMemberController::class, 'index'])->name('members.index');
        Route::post('/switch', [WorkspaceMemberController::class, 'switch'])->name('switch');
        Route::put('/members/{user}', [WorkspaceMemberController::class, 'updateMember'])->name('members.update');
        Route::delete('/members/{user}', [WorkspaceMemberController::class, 'revokeMember'])->name('members.destroy');
        Route::post('/invitations', [WorkspaceMemberController::class, 'storeStaffInvitation'])->name('invitations.store');
        Route::post('/invitations/{invitation}/revoke', [WorkspaceMemberController::class, 'revokeInvitation'])->name('invitations.revoke');
        Route::post('/invitations/{invitation}/resend', [WorkspaceMemberController::class, 'resendInvitation'])->name('invitations.resend');
        Route::post('/couple-invitations', [WorkspaceMemberController::class, 'storeCoupleInvitation'])->name('couple-invitations.store');
    });

require __DIR__.'/admin.php';
require __DIR__.'/owner.php';
require __DIR__.'/guest.php';
