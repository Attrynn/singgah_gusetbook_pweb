<?php

use App\Http\Controllers\Workspace\WorkspaceEventVendorController;
use App\Http\Controllers\Workspace\WorkspaceEventVendorCoordinationController;
use App\Http\Controllers\Workspace\WorkspaceEventVendorDeliverableController;
use App\Http\Controllers\Workspace\WorkspaceEventVendorDocumentController;
use App\Http\Controllers\Workspace\WorkspaceEventVendorEngagementController;
use App\Http\Controllers\Workspace\WorkspaceEventVendorLogisticsController;
use App\Http\Controllers\Workspace\WorkspaceEventVendorPortalController;
use App\Http\Controllers\Workspace\WorkspaceEventVendorReportController;
use App\Http\Controllers\Workspace\WorkspaceEventVendorSourcingController;
use App\Http\Controllers\Workspace\WorkspaceVendorCatalogController;
use App\Http\Controllers\Workspace\WorkspaceVendorDirectoryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Milestone 7 — Vendor & service provider management
|--------------------------------------------------------------------------
|
| Organization-scoped vendor directory lives at /workspace/{organization}/vendors.
| Event-scoped sourcing, engagements, documents, deliverables, logistics,
| coordination and reporting live under /workspace/{organization}/events/{event}/vendors.
|
*/

Route::prefix('workspace/{organization}')->name('workspace.')
    ->middleware(['auth', 'auth.session', 'active', 'org.context', 'org.member'])
    ->group(function (): void {
        // ------------------------------------------------------------------
        // Organization vendor directory
        // ------------------------------------------------------------------
        Route::get('vendors', [WorkspaceVendorDirectoryController::class, 'index'])->name('vendors.index');
        Route::get('vendors/create', [WorkspaceVendorDirectoryController::class, 'create'])->name('vendors.create');
        Route::post('vendors', [WorkspaceVendorDirectoryController::class, 'store'])->name('vendors.store');
        Route::get('vendors/{vendor}', [WorkspaceVendorDirectoryController::class, 'show'])->name('vendors.show');
        Route::get('vendors/{vendor}/edit', [WorkspaceVendorDirectoryController::class, 'edit'])->name('vendors.edit');
        Route::put('vendors/{vendor}', [WorkspaceVendorDirectoryController::class, 'update'])->name('vendors.update');
        Route::post('vendors/{vendor}/status', [WorkspaceVendorDirectoryController::class, 'status'])->name('vendors.status');
        Route::post('vendors/{vendor}/archive', [WorkspaceVendorDirectoryController::class, 'archive'])->name('vendors.archive');
        Route::post('vendors/{vendor}/restore', [WorkspaceVendorDirectoryController::class, 'restore'])->name('vendors.restore');

        // Vendor contacts
        Route::post('vendors/{vendor}/contacts', [WorkspaceVendorCatalogController::class, 'storeContact'])->name('vendors.contacts.store');
        Route::put('vendors/{vendor}/contacts/{contact}', [WorkspaceVendorCatalogController::class, 'updateContact'])->name('vendors.contacts.update');
        Route::delete('vendors/{vendor}/contacts/{contact}', [WorkspaceVendorCatalogController::class, 'destroyContact'])->name('vendors.contacts.destroy');

        // Vendor categories
        Route::get('vendor-categories', [WorkspaceVendorCatalogController::class, 'categories'])->name('vendor-categories.index');
        Route::post('vendor-categories', [WorkspaceVendorCatalogController::class, 'storeCategory'])->name('vendor-categories.store');
        Route::put('vendor-categories/{category}', [WorkspaceVendorCatalogController::class, 'updateCategory'])->name('vendor-categories.update');
        Route::delete('vendor-categories/{category}', [WorkspaceVendorCatalogController::class, 'destroyCategory'])->name('vendor-categories.destroy');

        // Vendor services and packages
        Route::post('vendors/{vendor}/services', [WorkspaceVendorCatalogController::class, 'storeService'])->name('vendors.services.store');
        Route::put('vendors/{vendor}/services/{service}', [WorkspaceVendorCatalogController::class, 'updateService'])->name('vendors.services.update');
        Route::delete('vendors/{vendor}/services/{service}', [WorkspaceVendorCatalogController::class, 'destroyService'])->name('vendors.services.destroy');
        Route::post('vendors/{vendor}/services/{service}/packages', [WorkspaceVendorCatalogController::class, 'storePackage'])->name('vendors.services.packages.store');
        Route::put('vendors/{vendor}/services/{service}/packages/{package}', [WorkspaceVendorCatalogController::class, 'updatePackage'])->name('vendors.services.packages.update');
        Route::delete('vendors/{vendor}/services/{service}/packages/{package}', [WorkspaceVendorCatalogController::class, 'destroyPackage'])->name('vendors.services.packages.destroy');

        // ------------------------------------------------------------------
        // Event vendor hub, sourcing and selection
        // ------------------------------------------------------------------
        Route::get('events/{event}/vendors', [WorkspaceEventVendorController::class, 'index'])->name('events.vendors.index');
        Route::get('events/{event}/vendors/reports', [WorkspaceEventVendorReportController::class, 'index'])->name('events.vendors.reports.index');
        Route::get('events/{event}/vendors/reports/export', [WorkspaceEventVendorReportController::class, 'export'])->name('events.vendors.reports.export');

        Route::post('events/{event}/vendors/requirements', [WorkspaceEventVendorSourcingController::class, 'storeRequirement'])->name('events.vendors.requirements.store');
        Route::put('events/{event}/vendors/requirements/{requirement}', [WorkspaceEventVendorSourcingController::class, 'updateRequirement'])->name('events.vendors.requirements.update');
        Route::delete('events/{event}/vendors/requirements/{requirement}', [WorkspaceEventVendorSourcingController::class, 'destroyRequirement'])->name('events.vendors.requirements.destroy');
        Route::post('events/{event}/vendors/requirements/{requirement}/candidates', [WorkspaceEventVendorSourcingController::class, 'storeCandidate'])->name('events.vendors.requirements.candidates.store');
        Route::delete('events/{event}/vendors/requirements/{requirement}/candidates/{candidate}', [WorkspaceEventVendorSourcingController::class, 'destroyCandidate'])->name('events.vendors.requirements.candidates.destroy');
        Route::post('events/{event}/vendors/requirements/{requirement}/candidates/{candidate}/preferred', [WorkspaceEventVendorSourcingController::class, 'preferCandidate'])->name('events.vendors.requirements.candidates.preferred');

        Route::post('events/{event}/vendors/proposals', [WorkspaceEventVendorSourcingController::class, 'storeProposal'])->name('events.vendors.proposals.store');
        Route::put('events/{event}/vendors/proposals/{proposal}', [WorkspaceEventVendorSourcingController::class, 'updateProposal'])->name('events.vendors.proposals.update');
        Route::post('events/{event}/vendors/proposals/{proposal}/status', [WorkspaceEventVendorSourcingController::class, 'proposalStatus'])->name('events.vendors.proposals.status');
        Route::post('events/{event}/vendors/proposals/{proposal}/select', [WorkspaceEventVendorSourcingController::class, 'selectProposal'])->name('events.vendors.proposals.select');

        // ------------------------------------------------------------------
        // Event vendor engagements
        // ------------------------------------------------------------------
        Route::get('events/{event}/vendors/engagements', [WorkspaceEventVendorEngagementController::class, 'index'])->name('events.vendors.engagements.index');
        Route::post('events/{event}/vendors/engagements', [WorkspaceEventVendorEngagementController::class, 'store'])->name('events.vendors.engagements.store');
        Route::get('events/{event}/vendors/engagements/{engagement}', [WorkspaceEventVendorEngagementController::class, 'show'])->name('events.vendors.engagements.show');
        Route::put('events/{event}/vendors/engagements/{engagement}', [WorkspaceEventVendorEngagementController::class, 'update'])->name('events.vendors.engagements.update');
        Route::post('events/{event}/vendors/engagements/{engagement}/status', [WorkspaceEventVendorEngagementController::class, 'status'])->name('events.vendors.engagements.status');
        Route::post('events/{event}/vendors/engagements/{engagement}/cancel', [WorkspaceEventVendorEngagementController::class, 'cancel'])->name('events.vendors.engagements.cancel');
        Route::post('events/{event}/vendors/engagements/{engagement}/close', [WorkspaceEventVendorEngagementController::class, 'close'])->name('events.vendors.engagements.close');
        Route::post('events/{event}/vendors/engagements/{engagement}/assignments', [WorkspaceEventVendorEngagementController::class, 'assign'])->name('events.vendors.engagements.assignments');

        // Documents
        Route::post('events/{event}/vendors/engagements/{engagement}/documents', [WorkspaceEventVendorDocumentController::class, 'store'])->name('events.vendors.documents.store');
        Route::post('events/{event}/vendors/documents/{document}/review', [WorkspaceEventVendorDocumentController::class, 'review'])->name('events.vendors.documents.review');
        Route::get('events/{event}/vendors/documents/{document}/download', [WorkspaceEventVendorDocumentController::class, 'download'])->name('events.vendors.documents.download');

        // Deliverables
        Route::post('events/{event}/vendors/engagements/{engagement}/deliverables', [WorkspaceEventVendorDeliverableController::class, 'store'])->name('events.vendors.deliverables.store');
        Route::put('events/{event}/vendors/deliverables/{deliverable}', [WorkspaceEventVendorDeliverableController::class, 'update'])->name('events.vendors.deliverables.update');
        Route::post('events/{event}/vendors/deliverables/{deliverable}/status', [WorkspaceEventVendorDeliverableController::class, 'status'])->name('events.vendors.deliverables.status');
        Route::post('events/{event}/vendors/deliverables/{deliverable}/submissions', [WorkspaceEventVendorDeliverableController::class, 'submit'])->name('events.vendors.deliverables.submissions.store');
        Route::post('events/{event}/vendors/deliverables/{deliverable}/submissions/{submission}/review', [WorkspaceEventVendorDeliverableController::class, 'review'])->name('events.vendors.deliverables.submissions.review');
        Route::post('events/{event}/vendors/deliverables/{deliverable}/task', [WorkspaceEventVendorDeliverableController::class, 'linkTask'])->name('events.vendors.deliverables.task');

        // Schedule references, arrival state and logistics
        Route::post('events/{event}/vendors/engagements/{engagement}/schedule', [WorkspaceEventVendorLogisticsController::class, 'storeSchedule'])->name('events.vendors.schedule.store');
        Route::put('events/{event}/vendors/schedule/{reference}', [WorkspaceEventVendorLogisticsController::class, 'updateSchedule'])->name('events.vendors.schedule.update');
        Route::post('events/{event}/vendors/schedule/{reference}/arrival', [WorkspaceEventVendorLogisticsController::class, 'arrival'])->name('events.vendors.schedule.arrival');
        Route::post('events/{event}/vendors/engagements/{engagement}/logistics', [WorkspaceEventVendorLogisticsController::class, 'storeLogistics'])->name('events.vendors.logistics.store');
        Route::put('events/{event}/vendors/logistics/{requirement}', [WorkspaceEventVendorLogisticsController::class, 'updateLogistics'])->name('events.vendors.logistics.update');
        Route::post('events/{event}/vendors/logistics/{requirement}/status', [WorkspaceEventVendorLogisticsController::class, 'logisticsStatus'])->name('events.vendors.logistics.status');

        // Communications, issues, change requests, performance
        Route::post('events/{event}/vendors/engagements/{engagement}/communications', [WorkspaceEventVendorCoordinationController::class, 'storeCommunication'])->name('events.vendors.communications.store');
        Route::post('events/{event}/vendors/engagements/{engagement}/issues', [WorkspaceEventVendorCoordinationController::class, 'storeIssue'])->name('events.vendors.issues.store');
        Route::put('events/{event}/vendors/issues/{issue}', [WorkspaceEventVendorCoordinationController::class, 'updateIssue'])->name('events.vendors.issues.update');
        Route::post('events/{event}/vendors/issues/{issue}/status', [WorkspaceEventVendorCoordinationController::class, 'issueStatus'])->name('events.vendors.issues.status');
        Route::post('events/{event}/vendors/issues/{issue}/incident', [WorkspaceEventVendorCoordinationController::class, 'linkIncident'])->name('events.vendors.issues.incident');
        Route::post('events/{event}/vendors/engagements/{engagement}/change-requests', [WorkspaceEventVendorCoordinationController::class, 'storeChangeRequest'])->name('events.vendors.change-requests.store');
        Route::post('events/{event}/vendors/change-requests/{changeRequest}/submit', [WorkspaceEventVendorCoordinationController::class, 'submitChangeRequest'])->name('events.vendors.change-requests.submit');
        Route::post('events/{event}/vendors/change-requests/{changeRequest}/decision', [WorkspaceEventVendorCoordinationController::class, 'decideChangeRequest'])->name('events.vendors.change-requests.decision');
        Route::post('events/{event}/vendors/change-requests/{changeRequest}/withdraw', [WorkspaceEventVendorCoordinationController::class, 'withdrawChangeRequest'])->name('events.vendors.change-requests.withdraw');
        Route::post('events/{event}/vendors/engagements/{engagement}/performance', [WorkspaceEventVendorCoordinationController::class, 'storePerformance'])->name('events.vendors.performance.store');

        // Vendor portal management (internal side)
        Route::get('events/{event}/vendors/engagements/{engagement}/portal', [WorkspaceEventVendorPortalController::class, 'index'])->name('events.vendors.portal.index');
        Route::post('events/{event}/vendors/engagements/{engagement}/portal/invite', [WorkspaceEventVendorPortalController::class, 'invite'])->name('events.vendors.portal.invite');
        Route::post('events/{event}/vendors/portal/memberships/{membership}/grant', [WorkspaceEventVendorPortalController::class, 'grant'])->name('events.vendors.portal.grant');
        Route::post('events/{event}/vendors/portal/memberships/{membership}/revoke', [WorkspaceEventVendorPortalController::class, 'revoke'])->name('events.vendors.portal.revoke');
    });
