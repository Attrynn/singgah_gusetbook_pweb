<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CheckinController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\GiftAccountController;
use App\Http\Controllers\Admin\GuestbookController;
use App\Http\Controllers\Admin\GuestCategoryController;
use App\Http\Controllers\Admin\GuestController;
use App\Http\Controllers\Admin\GuestGroupController;
use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\OwnerController;
use App\Http\Controllers\Admin\PollController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RsvpController;
use App\Http\Controllers\Admin\SeatingController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SongController;
use App\Http\Controllers\Admin\GameController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', 'active', 'role:superadmin'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('events/{event}/preview', [EventController::class, 'preview'])->name('events.preview');
    Route::post('events/{event}/status', [EventController::class, 'status'])->name('events.status');
    Route::resource('events', EventController::class);

    Route::resource('owners', OwnerController::class)->except(['show']);

    Route::get('guests/import', [GuestController::class, 'importForm'])->name('guests.import');
    Route::post('guests/import', [GuestController::class, 'import'])->name('guests.import.store');
    Route::get('guests/export', [GuestController::class, 'export'])->name('guests.export');
    Route::post('guests/bulk', [GuestController::class, 'bulk'])->name('guests.bulk');
    Route::post('guests/{guest}/regenerate', [GuestController::class, 'regenerate'])->name('guests.regenerate');
    Route::resource('guests', GuestController::class);

    Route::resource('categories', GuestCategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('groups', GuestGroupController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('invitations', [InvitationController::class, 'index'])->name('invitations.index');
    Route::get('invitations/{guest}', [InvitationController::class, 'show'])->name('invitations.show');
    Route::get('invitations/{guest}/preview', [InvitationController::class, 'preview'])->name('invitations.preview');
    Route::get('invitations/{guest}/whatsapp', [InvitationController::class, 'whatsapp'])->name('invitations.whatsapp');
    Route::post('invitations/{guest}/shared', [InvitationController::class, 'shared'])->name('invitations.shared');
    Route::post('invitations/{guest}/regenerate', [InvitationController::class, 'regenerate'])->name('invitations.regenerate');

    Route::get('rsvps/export', [RsvpController::class, 'export'])->name('rsvps.export');
    Route::get('rsvps', [RsvpController::class, 'index'])->name('rsvps.index');
    Route::put('rsvps/{guest}', [RsvpController::class, 'update'])->name('rsvps.update');

    Route::get('checkins', [CheckinController::class, 'index'])->name('checkins.index');
    Route::get('checkins/scanner', [CheckinController::class, 'scanner'])->name('checkins.scanner');
    Route::post('checkins/scan', [CheckinController::class, 'scan'])->name('checkins.scan');
    Route::post('checkins/manual', [CheckinController::class, 'manual'])->name('checkins.manual');

    Route::get('guestbook/export', [GuestbookController::class, 'export'])->name('guestbook.export');
    Route::get('guestbook', [GuestbookController::class, 'index'])->name('guestbook.index');
    Route::post('guestbook/{message}/moderate', [GuestbookController::class, 'moderate'])->name('guestbook.moderate');
    Route::post('guestbook/{message}/pin', [GuestbookController::class, 'pin'])->name('guestbook.pin');
    Route::delete('guestbook/{message}', [GuestbookController::class, 'destroy'])->name('guestbook.destroy');

    Route::get('media', [MediaController::class, 'index'])->name('media.index');
    Route::post('media', [MediaController::class, 'store'])->name('media.store');
    Route::post('media/{asset}/moderate', [MediaController::class, 'moderate'])->name('media.moderate');
    Route::post('media/{asset}/feature', [MediaController::class, 'feature'])->name('media.feature');
    Route::delete('media/{asset}', [MediaController::class, 'destroy'])->name('media.destroy');
    Route::post('albums', [MediaController::class, 'storeAlbum'])->name('albums.store');
    Route::delete('albums/{album}', [MediaController::class, 'destroyAlbum'])->name('albums.destroy');
    Route::post('albums/{album}/attach', [MediaController::class, 'attach'])->name('albums.attach');

    Route::get('seating', [SeatingController::class, 'index'])->name('seating.index');
    Route::post('seating/tables', [SeatingController::class, 'storeTable'])->name('seating.tables.store');
    Route::put('seating/tables/{table}', [SeatingController::class, 'updateTable'])->name('seating.tables.update');
    Route::delete('seating/tables/{table}', [SeatingController::class, 'destroyTable'])->name('seating.tables.destroy');
    Route::post('seating/assign', [SeatingController::class, 'assign'])->name('seating.assign');
    Route::delete('seating/assignments/{assignment}', [SeatingController::class, 'unassign'])->name('seating.unassign');

    Route::get('interactions/songs', [SongController::class, 'index'])->name('interactions.songs');
    Route::put('interactions/songs/{song}', [SongController::class, 'update'])->name('interactions.songs.update');

    Route::resource('interactions/polls', PollController::class)->except(['show'])->names([
        'index' => 'interactions.polls.index',
        'create' => 'interactions.polls.create',
        'store' => 'interactions.polls.store',
        'edit' => 'interactions.polls.edit',
        'update' => 'interactions.polls.update',
        'destroy' => 'interactions.polls.destroy',
    ]);
    Route::post('interactions/polls/{poll}/toggle', [PollController::class, 'toggle'])->name('interactions.polls.toggle');

    Route::resource('interactions/games', GameController::class)->except(['show'])->names([
        'index' => 'interactions.games.index',
        'create' => 'interactions.games.create',
        'store' => 'interactions.games.store',
        'edit' => 'interactions.games.edit',
        'update' => 'interactions.games.update',
        'destroy' => 'interactions.games.destroy',
    ]);
    Route::post('interactions/games/{game}/toggle', [GameController::class, 'toggle'])->name('interactions.games.toggle');
    Route::post('interactions/games/{game}/questions', [GameController::class, 'storeQuestion'])->name('interactions.games.questions.store');
    Route::delete('interactions/games/{game}/questions/{question}', [GameController::class, 'destroyQuestion'])->name('interactions.games.questions.destroy');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('reports/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');

    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('audit-logs', AuditLogController::class)->name('audit-logs.index');

    Route::get('events/{event}/gifts', [GiftAccountController::class, 'index'])->name('events.gifts.index');
    Route::post('events/{event}/gifts', [GiftAccountController::class, 'store'])->name('events.gifts.store');
    Route::put('gifts/{gift}', [GiftAccountController::class, 'update'])->name('gifts.update');
    Route::delete('gifts/{gift}', [GiftAccountController::class, 'destroy'])->name('gifts.destroy');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');
});
