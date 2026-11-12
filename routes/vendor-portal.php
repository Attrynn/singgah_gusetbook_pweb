<?php

use App\Http\Controllers\VendorPortal\VendorPortalController;
use App\Http\Controllers\VendorPortal\VendorPortalInvitationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Milestone 7 — External vendor portal
|--------------------------------------------------------------------------
|
| The portal reuses the existing authentication stack. A vendor contact must
| hold an active vendor portal membership AND an explicit engagement access
| grant before any engagement resource is returned. Every controller action
| re-validates that scope server-side.
|
*/

Route::prefix('vendor-portal')->name('vendor-portal.')
    ->middleware(['auth', 'auth.session', 'active'])
    ->group(function (): void {
        Route::get('/', [VendorPortalController::class, 'index'])->name('index');
        Route::get('engagements/{engagement}', [VendorPortalController::class, 'show'])->name('engagements.show');
        Route::get('documents/{document}/download', [VendorPortalController::class, 'download'])->name('documents.download');
        Route::post('engagements/{engagement}/deliverables/{deliverable}/submissions', [VendorPortalController::class, 'submitDeliverable'])->name('deliverables.submit');
        Route::post('engagements/{engagement}/communications', [VendorPortalController::class, 'storeCommunication'])->name('communications.store');
        Route::post('engagements/{engagement}/change-requests', [VendorPortalController::class, 'storeChangeRequest'])->name('change-requests.store');
        Route::post('schedule/{reference}/confirm', [VendorPortalController::class, 'confirmSchedule'])->name('schedule.confirm');
        Route::post('logistics/{requirement}/confirm', [VendorPortalController::class, 'confirmLogistics'])->name('logistics.confirm');
        Route::post('invitations/{token}/accept', [VendorPortalInvitationController::class, 'accept'])->name('invitations.accept');
        Route::get('invitations/{token}', [VendorPortalInvitationController::class, 'show'])->name('invitations.show');
    });
