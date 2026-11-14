<?php

use App\Http\Controllers\Guest\GuestGalleryController;
use App\Http\Controllers\Guest\GuestGameController;
use App\Http\Controllers\Guest\GuestGiftController;
use App\Http\Controllers\Guest\GuestGuestbookController;
use App\Http\Controllers\Guest\GuestInteractionController;
use App\Http\Controllers\Guest\GuestInvitationController;
use App\Http\Controllers\Guest\GuestMemoryController;
use App\Http\Controllers\Guest\GuestPassController;
use App\Http\Controllers\Guest\GuestRsvpController;
use App\Http\Controllers\Guest\LiveWallController;
use App\Http\Controllers\MediaDeliveryController;
use App\Http\Controllers\MemoryShareController;
use Illuminate\Support\Facades\Route;

Route::get('/e/{slug}', [GuestInvitationController::class, 'landing'])->name('guest.landing');

// Signed delivery for private event media. The controller re-checks the
// asset and event access policy on every request, so withdrawing approval,
// archiving the event or deleting it denies access even for an issued URL.
Route::get('/media/{asset}', MediaDeliveryController::class)
    ->middleware('signed')
    ->name('media.show');
Route::get('/wall/{slug}', [LiveWallController::class, 'show'])->name('live-wall.show');
Route::get('/wall/{slug}/messages', [LiveWallController::class, 'messages'])->name('live-wall.messages');

// Public, revocable memory share link. A missing, revoked, expired or
// disabled token resolves to 404 in the controller, as does a soft-deleted
// or archived event.
Route::get('m/{token}', [MemoryShareController::class, 'show'])
    ->middleware('throttle:memory-public')
    ->name('memory.public');

Route::prefix('invite/{token}')->name('guest.')->middleware('guest.token')->group(function () {
    Route::get('/', [GuestInvitationController::class, 'show'])->name('invitation');
    Route::post('shared', [GuestInvitationController::class, 'markShared'])->name('shared');

    Route::post('rsvp', [GuestRsvpController::class, 'store'])->middleware('throttle:guest-actions')->name('rsvp.store');

    Route::get('pass', [GuestPassController::class, 'show'])->name('pass');
    Route::get('pass/download', [GuestPassController::class, 'download'])->name('pass.download');

    Route::post('guestbook', [GuestGuestbookController::class, 'store'])->middleware('throttle:guestbook')->name('guestbook.store');

    Route::get('gallery', [GuestGalleryController::class, 'index'])->name('gallery');
    Route::post('gallery', [GuestGalleryController::class, 'store'])->middleware('throttle:guest-uploads')->name('gallery.store');

    Route::get('memories', [GuestMemoryController::class, 'index'])->name('memories');
    Route::post('memories', [GuestMemoryController::class, 'store'])->middleware('throttle:memory-contribute')->name('memories.store');
    Route::post('memories/message', [GuestMemoryController::class, 'storeMessage'])->middleware('throttle:guestbook')->name('memories.message');

    Route::get('gift', [GuestGiftController::class, 'index'])->name('gift');

    Route::get('interactions', [GuestInteractionController::class, 'index'])->name('interactions');
    Route::post('song-request', [GuestInteractionController::class, 'song'])->middleware('throttle:guest-actions')->name('song.store');
    Route::post('poll/{poll}', [GuestInteractionController::class, 'vote'])->middleware('throttle:guest-poll')->name('poll.vote');

    Route::get('game/{game}', [GuestGameController::class, 'show'])->name('game.show');
    Route::post('game/{game}', [GuestGameController::class, 'submit'])->middleware('throttle:guest-actions')->name('game.submit');
});
