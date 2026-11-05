<?php

use App\Http\Controllers\Owner\AttendanceController;
use App\Http\Controllers\Owner\DashboardController;
use App\Http\Controllers\Owner\InteractionController;
use App\Http\Controllers\Owner\MemoryController;
use App\Http\Controllers\Owner\ProfileController;
use App\Http\Controllers\Owner\StoryController;
use App\Http\Controllers\Owner\TimelineController;
use App\Http\Controllers\Owner\WishController;
use Illuminate\Support\Facades\Route;

Route::prefix('owner')->name('owner.')->middleware(['auth', 'active', 'role:owner'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('wishes', [WishController::class, 'index'])->name('wishes.index');
    Route::get('wishes/favorites', [WishController::class, 'favorites'])->name('wishes.favorites');
    Route::get('wishes/download', [WishController::class, 'download'])->name('wishes.download');
    Route::get('wishes/{message}', [WishController::class, 'show'])->name('wishes.show');
    Route::post('wishes/{message}/favorite', [WishController::class, 'favorite'])->name('wishes.favorite');

    Route::get('attendance', AttendanceController::class)->name('attendance');

    Route::get('memories', [MemoryController::class, 'index'])->name('memories.index');
    Route::get('memories/{asset}', [MemoryController::class, 'show'])->name('memories.show');
    Route::get('memories/{asset}/download', [MemoryController::class, 'download'])->name('memories.download');
    Route::post('memories/albums', [MemoryController::class, 'storeAlbum'])->name('memories.albums.store');
    Route::delete('memories/albums/{album}', [MemoryController::class, 'destroyAlbum'])->name('memories.albums.destroy');

    Route::get('timeline', TimelineController::class)->name('timeline');
    Route::get('interactions', InteractionController::class)->name('interactions');
    Route::get('story', StoryController::class)->name('story');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');
});
