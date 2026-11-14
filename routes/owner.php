<?php

use App\Http\Controllers\Owner\AttendanceController;
use App\Http\Controllers\Owner\DashboardController;
use App\Http\Controllers\Owner\InteractionController;
use App\Http\Controllers\Owner\MemoryCollectionController;
use App\Http\Controllers\Owner\MemoryController;
use App\Http\Controllers\Owner\MemoryExportController;
use App\Http\Controllers\Owner\MemorySettingsController;
use App\Http\Controllers\Owner\ProfileController;
use App\Http\Controllers\Owner\StoryController;
use App\Http\Controllers\Owner\TimelineController;
use App\Http\Controllers\Owner\WishController;
use Illuminate\Support\Facades\Route;

Route::prefix('owner')->name('owner.')->middleware(['auth', 'auth.session', 'active', 'role:owner'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('wishes', [WishController::class, 'index'])->name('wishes.index');
    Route::get('wishes/favorites', [WishController::class, 'favorites'])->name('wishes.favorites');
    Route::get('wishes/download', [WishController::class, 'download'])->name('wishes.download');
    Route::get('wishes/{message}', [WishController::class, 'show'])->name('wishes.show');
    Route::post('wishes/{message}/favorite', [WishController::class, 'favorite'])->name('wishes.favorite');
    Route::post('wishes/{message}/moderate', [WishController::class, 'moderate'])->name('wishes.moderate');

    Route::get('attendance', AttendanceController::class)->name('attendance');

    // Memory space. Every literal segment is declared before memories/{asset}
    // so the wildcard never swallows a static route.
    Route::get('memories/pending', [MemoryController::class, 'pending'])->name('memories.pending');

    Route::get('memories/collections', [MemoryCollectionController::class, 'index'])->name('memories.collections.index');
    Route::post('memories/collections', [MemoryCollectionController::class, 'store'])->name('memories.collections.store');
    Route::patch('memories/collections/{collection}', [MemoryCollectionController::class, 'update'])->name('memories.collections.update');
    Route::delete('memories/collections/{collection}', [MemoryCollectionController::class, 'destroy'])->name('memories.collections.destroy');
    Route::post('memories/collections/{collection}/archive', [MemoryCollectionController::class, 'archive'])->name('memories.collections.archive');
    Route::post('memories/collections/{collection}/unarchive', [MemoryCollectionController::class, 'unarchive'])->name('memories.collections.unarchive');
    Route::post('memories/collections/{collection}/items', [MemoryCollectionController::class, 'addItem'])->name('memories.collections.items.store');
    Route::post('memories/collections/attach', [MemoryCollectionController::class, 'attach'])->name('memories.collections.attach');
    Route::delete('memories/collections/{collection}/items/{type}/{item}', [MemoryCollectionController::class, 'removeItem'])->name('memories.collections.items.destroy');

    Route::get('memories/timeline', [MemoryController::class, 'timeline'])->name('memories.timeline');

    Route::get('memories/settings', [MemorySettingsController::class, 'edit'])->name('memories.settings.edit');
    Route::put('memories/settings', [MemorySettingsController::class, 'update'])->name('memories.settings.update');
    Route::post('memories/settings/close', [MemorySettingsController::class, 'close'])->name('memories.settings.close');
    Route::post('memories/settings/reopen', [MemorySettingsController::class, 'reopen'])->name('memories.settings.reopen');

    Route::post('memories/share', [MemorySettingsController::class, 'enableShare'])->name('memories.share.enable');
    Route::delete('memories/share', [MemorySettingsController::class, 'revokeShare'])->name('memories.share.revoke');

    Route::get('memories/export', [MemoryExportController::class, 'index'])->name('memories.export.index');
    Route::post('memories/export', [MemoryExportController::class, 'store'])->name('memories.export.store');
    Route::get('memories/export/{job}', [MemoryExportController::class, 'show'])->name('memories.export.show');
    Route::get('memories/export/{job}/download', [MemoryExportController::class, 'download'])->name('memories.export.download')->middleware('throttle:30,1');

    Route::get('memories', [MemoryController::class, 'index'])->name('memories.index');
    Route::get('memories/{asset}', [MemoryController::class, 'show'])->name('memories.show');
    Route::get('memories/{asset}/download', [MemoryController::class, 'download'])->name('memories.download')->middleware('throttle:30,1');
    Route::post('memories/albums', [MemoryController::class, 'storeAlbum'])->name('memories.albums.store');
    Route::delete('memories/albums/{album}', [MemoryController::class, 'destroyAlbum'])->name('memories.albums.destroy');
    Route::post('memories/{asset}/moderate', [MemoryController::class, 'moderate'])->name('memories.moderate');

    Route::get('timeline', TimelineController::class)->name('timeline');
    Route::get('interactions', InteractionController::class)->name('interactions');
    Route::get('story', StoryController::class)->name('story');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->middleware('throttle:6,1')->name('profile.update');
    Route::post('profile/email/resend', [ProfileController::class, 'resendEmail'])->middleware('throttle:3,1')->name('profile.email.resend');
    Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');
});
