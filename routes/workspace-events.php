<?php

use App\Http\Controllers\Workspace\WorkspaceAnnouncementController;
use App\Http\Controllers\Workspace\WorkspaceCheckinController;
use App\Http\Controllers\Workspace\WorkspaceEventController;
use App\Http\Controllers\Workspace\WorkspaceEventDuplicateController;
use App\Http\Controllers\Workspace\WorkspaceEventScheduleController;
use App\Http\Controllers\Workspace\WorkspaceEventTeamController;
use App\Http\Controllers\Workspace\WorkspaceGuestController;
use App\Http\Controllers\Workspace\WorkspaceGuestMemberController;
use App\Http\Controllers\Workspace\WorkspaceIncidentController;
use App\Http\Controllers\Workspace\WorkspaceOperationsController;
use App\Http\Controllers\Workspace\WorkspaceOperationsReportController;
use App\Http\Controllers\Workspace\WorkspaceOperationsStaffController;
use App\Http\Controllers\Workspace\WorkspacePlaybookController;
use App\Http\Controllers\Workspace\WorkspaceRunOfShowController;
use App\Http\Controllers\Workspace\WorkspaceStationController;
use App\Http\Controllers\Workspace\WorkspaceTaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('workspace/{organization}')->name('workspace.')
    ->middleware(['auth', 'auth.session', 'active', 'org.context', 'org.member'])
    ->group(function (): void {
        // Event portfolio + lifecycle. `events/create` must be declared before
        // `events/{event}` so the literal segment is not swallowed by binding.
        Route::get('events', [WorkspaceEventController::class, 'index'])->name('events.index');
        Route::get('events/create', [WorkspaceEventController::class, 'create'])->name('events.create');
        Route::post('events', [WorkspaceEventController::class, 'store'])->name('events.store');
        Route::get('events/{event}', [WorkspaceEventController::class, 'show'])->name('events.show');
        Route::get('events/{event}/edit', [WorkspaceEventController::class, 'edit'])->name('events.edit');
        Route::put('events/{event}', [WorkspaceEventController::class, 'update'])->name('events.update');
        Route::get('events/{event}/setup', [WorkspaceEventController::class, 'setup'])->name('events.setup');
        Route::put('events/{event}/setup', [WorkspaceEventController::class, 'saveSetup'])->name('events.setup.save');
        Route::post('events/{event}/setup/finalize', [WorkspaceEventController::class, 'finalizeSetup'])->name('events.setup.finalize');
        Route::post('events/{event}/status', [WorkspaceEventController::class, 'status'])->name('events.status');
        Route::post('events/{event}/archive', [WorkspaceEventController::class, 'archive'])->name('events.archive');
        Route::post('events/{event}/unarchive', [WorkspaceEventController::class, 'unarchive'])->name('events.unarchive');

        // Team assignment.
        Route::get('events/{event}/team', [WorkspaceEventTeamController::class, 'index'])->name('events.team.index');
        Route::post('events/{event}/team', [WorkspaceEventTeamController::class, 'store'])->name('events.team.store');
        Route::post('events/{event}/team/primary', [WorkspaceEventTeamController::class, 'setPrimary'])->name('events.team.primary');
        Route::put('events/{event}/team/{membership}', [WorkspaceEventTeamController::class, 'update'])->name('events.team.update');
        Route::delete('events/{event}/team/{membership}', [WorkspaceEventTeamController::class, 'destroy'])->name('events.team.destroy');

        // Public schedule (event_schedules).
        Route::get('events/{event}/schedule', [WorkspaceEventScheduleController::class, 'index'])->name('events.schedule.index');
        Route::post('events/{event}/schedule', [WorkspaceEventScheduleController::class, 'store'])->name('events.schedule.store');
        Route::put('events/{event}/schedule/{schedule}', [WorkspaceEventScheduleController::class, 'update'])->name('events.schedule.update');
        Route::delete('events/{event}/schedule/{schedule}', [WorkspaceEventScheduleController::class, 'destroy'])->name('events.schedule.destroy');

        // Internal run of show.
        Route::get('events/{event}/run-of-show', [WorkspaceRunOfShowController::class, 'index'])->name('events.run-of-show.index');
        Route::post('events/{event}/run-of-show', [WorkspaceRunOfShowController::class, 'store'])->name('events.run-of-show.store');
        Route::put('events/{event}/run-of-show/{item}', [WorkspaceRunOfShowController::class, 'update'])->name('events.run-of-show.update');
        Route::delete('events/{event}/run-of-show/{item}', [WorkspaceRunOfShowController::class, 'destroy'])->name('events.run-of-show.destroy');
        Route::post('events/{event}/run-of-show/{item}/status', [WorkspaceRunOfShowController::class, 'status'])->name('events.run-of-show.status');

        // ------------------------------------------------------------------
        // Milestone 6 — Event operations & staff coordination.
        // ------------------------------------------------------------------
        Route::get('events/{event}/operations', [WorkspaceOperationsController::class, 'index'])->name('events.operations.index');
        Route::get('events/{event}/operations/live', [WorkspaceOperationsController::class, 'live'])->name('events.operations.live');
        Route::get('events/{event}/operations/my-work', [WorkspaceOperationsController::class, 'myWork'])->name('events.operations.my-work');
        Route::post('events/{event}/operations/transition', [WorkspaceOperationsController::class, 'transition'])->name('events.operations.transition');
        Route::get('events/{event}/operations/closure', [WorkspaceOperationsController::class, 'closure'])->name('events.operations.closure');
        Route::post('events/{event}/operations/closure', [WorkspaceOperationsController::class, 'close'])->name('events.operations.close');
        Route::post('events/{event}/operations/reopen', [WorkspaceOperationsController::class, 'reopen'])->name('events.operations.reopen');

        Route::get('events/{event}/operations/staff', [WorkspaceOperationsStaffController::class, 'index'])->name('events.operations.staff.index');
        Route::post('events/{event}/operations/staff/invite', [WorkspaceOperationsStaffController::class, 'invite'])->name('events.operations.staff.invite');
        Route::post('events/{event}/operations/staff/{membership}/accept', [WorkspaceOperationsStaffController::class, 'accept'])->name('events.operations.staff.accept');
        Route::post('events/{event}/operations/staff/{membership}/decline', [WorkspaceOperationsStaffController::class, 'decline'])->name('events.operations.staff.decline');
        Route::post('events/{event}/operations/staff/{membership}/suspend', [WorkspaceOperationsStaffController::class, 'suspend'])->name('events.operations.staff.suspend');
        Route::post('events/{event}/operations/staff/{membership}/reinstate', [WorkspaceOperationsStaffController::class, 'reinstate'])->name('events.operations.staff.reinstate');
        Route::delete('events/{event}/operations/staff/{membership}', [WorkspaceOperationsStaffController::class, 'remove'])->name('events.operations.staff.remove');
        Route::put('events/{event}/operations/staff/{membership}/permissions', [WorkspaceOperationsStaffController::class, 'permissions'])->name('events.operations.staff.permissions');

        Route::get('events/{event}/operations/stations', [WorkspaceStationController::class, 'index'])->name('events.operations.stations.index');
        Route::post('events/{event}/operations/stations', [WorkspaceStationController::class, 'store'])->name('events.operations.stations.store');
        Route::put('events/{event}/operations/stations/{station}', [WorkspaceStationController::class, 'update'])->name('events.operations.stations.update');
        Route::delete('events/{event}/operations/stations/{station}', [WorkspaceStationController::class, 'destroy'])->name('events.operations.stations.destroy');
        Route::post('events/{event}/operations/stations/{station}/status', [WorkspaceStationController::class, 'status'])->name('events.operations.stations.status');
        Route::post('events/{event}/operations/stations/{station}/assign', [WorkspaceStationController::class, 'assign'])->name('events.operations.stations.assign');
        Route::delete('events/{event}/operations/stations/{station}/assign/{membership}', [WorkspaceStationController::class, 'unassign'])->name('events.operations.stations.unassign');
        Route::post('events/{event}/operations/stations/{station}/supervisor', [WorkspaceStationController::class, 'supervisor'])->name('events.operations.stations.supervisor');

        Route::get('events/{event}/operations/tasks', [WorkspaceTaskController::class, 'index'])->name('events.operations.tasks.index');
        Route::post('events/{event}/operations/tasks', [WorkspaceTaskController::class, 'store'])->name('events.operations.tasks.store');
        Route::put('events/{event}/operations/tasks/{task}', [WorkspaceTaskController::class, 'update'])->name('events.operations.tasks.update');
        Route::post('events/{event}/operations/tasks/{task}/status', [WorkspaceTaskController::class, 'status'])->name('events.operations.tasks.status');
        Route::post('events/{event}/operations/tasks/{task}/assign', [WorkspaceTaskController::class, 'assign'])->name('events.operations.tasks.assign');
        Route::post('events/{event}/operations/tasks/{task}/checklist', [WorkspaceTaskController::class, 'storeChecklist'])->name('events.operations.tasks.checklist.store');
        Route::put('events/{event}/operations/tasks/{task}/checklist/{item}', [WorkspaceTaskController::class, 'updateChecklist'])->name('events.operations.tasks.checklist.update');
        Route::delete('events/{event}/operations/tasks/{task}/checklist/{item}', [WorkspaceTaskController::class, 'destroyChecklist'])->name('events.operations.tasks.checklist.destroy');
        Route::post('events/{event}/operations/tasks/{task}/comments', [WorkspaceTaskController::class, 'storeComment'])->name('events.operations.tasks.comments.store');
        Route::post('events/{event}/operations/tasks/{task}/dependencies', [WorkspaceTaskController::class, 'storeDependency'])->name('events.operations.tasks.dependencies.store');
        Route::delete('events/{event}/operations/tasks/{task}/dependencies/{dependency}', [WorkspaceTaskController::class, 'destroyDependency'])->name('events.operations.tasks.dependencies.destroy');

        Route::get('events/{event}/operations/incidents', [WorkspaceIncidentController::class, 'index'])->name('events.operations.incidents.index');
        Route::post('events/{event}/operations/incidents', [WorkspaceIncidentController::class, 'store'])->name('events.operations.incidents.store');
        Route::put('events/{event}/operations/incidents/{incident}', [WorkspaceIncidentController::class, 'update'])->name('events.operations.incidents.update');
        Route::post('events/{event}/operations/incidents/{incident}/assign', [WorkspaceIncidentController::class, 'assign'])->name('events.operations.incidents.assign');
        Route::post('events/{event}/operations/incidents/{incident}/status', [WorkspaceIncidentController::class, 'status'])->name('events.operations.incidents.status');
        Route::post('events/{event}/operations/incidents/{incident}/escalate', [WorkspaceIncidentController::class, 'escalate'])->name('events.operations.incidents.escalate');
        Route::post('events/{event}/operations/incidents/{incident}/resolve', [WorkspaceIncidentController::class, 'resolve'])->name('events.operations.incidents.resolve');

        Route::get('events/{event}/operations/announcements', [WorkspaceAnnouncementController::class, 'index'])->name('events.operations.announcements.index');
        Route::post('events/{event}/operations/announcements', [WorkspaceAnnouncementController::class, 'store'])->name('events.operations.announcements.store');
        Route::put('events/{event}/operations/announcements/{announcement}', [WorkspaceAnnouncementController::class, 'update'])->name('events.operations.announcements.update');
        Route::post('events/{event}/operations/announcements/{announcement}/publish', [WorkspaceAnnouncementController::class, 'publish'])->name('events.operations.announcements.publish');
        Route::post('events/{event}/operations/announcements/{announcement}/withdraw', [WorkspaceAnnouncementController::class, 'withdraw'])->name('events.operations.announcements.withdraw');
        Route::post('events/{event}/operations/announcements/{announcement}/read', [WorkspaceAnnouncementController::class, 'read'])->name('events.operations.announcements.read');
        Route::post('events/{event}/operations/announcements/{announcement}/acknowledge', [WorkspaceAnnouncementController::class, 'acknowledge'])->name('events.operations.announcements.acknowledge');

        Route::get('events/{event}/operations/reports', [WorkspaceOperationsReportController::class, 'index'])->name('events.operations.reports.index');
        Route::get('events/{event}/operations/reports/export', [WorkspaceOperationsReportController::class, 'export'])->name('events.operations.reports.export');

        // Guests & invitations. Literal segments must be declared before the
        // `{guest}` binding so they are not swallowed by route-model binding.
        Route::get('events/{event}/guests', [WorkspaceGuestController::class, 'index'])->name('events.guests.index');
        Route::get('events/{event}/guests/create', [WorkspaceGuestController::class, 'create'])->name('events.guests.create');
        Route::post('events/{event}/guests', [WorkspaceGuestController::class, 'store'])->name('events.guests.store');
        Route::get('events/{event}/guests/individuals', [WorkspaceGuestController::class, 'individuals'])->name('events.guests.individuals');
        Route::get('events/{event}/guests/import', [WorkspaceGuestController::class, 'importForm'])->name('events.guests.import');
        Route::post('events/{event}/guests/import', [WorkspaceGuestController::class, 'import'])->name('events.guests.import.store');
        Route::get('events/{event}/guests/export', [WorkspaceGuestController::class, 'export'])->name('events.guests.export');
        Route::post('events/{event}/guests/bulk', [WorkspaceGuestController::class, 'bulk'])->name('events.guests.bulk');
        Route::post('events/{event}/guests/migrate-legacy', [WorkspaceGuestController::class, 'migrateLegacy'])->name('events.guests.migrate');
        Route::get('events/{event}/guests/{guest}', [WorkspaceGuestController::class, 'show'])->name('events.guests.show');
        Route::get('events/{event}/guests/{guest}/edit', [WorkspaceGuestController::class, 'edit'])->name('events.guests.edit');
        Route::put('events/{event}/guests/{guest}', [WorkspaceGuestController::class, 'update'])->name('events.guests.update');
        Route::delete('events/{event}/guests/{guest}', [WorkspaceGuestController::class, 'destroy'])->name('events.guests.destroy');

        // Identified individuals inside an invitation party.
        Route::post('events/{event}/guests/{guest}/members', [WorkspaceGuestMemberController::class, 'store'])->name('events.guests.members.store');
        Route::put('events/{event}/guests/{guest}/members/{member}', [WorkspaceGuestMemberController::class, 'update'])->name('events.guests.members.update');
        Route::delete('events/{event}/guests/{guest}/members/{member}', [WorkspaceGuestMemberController::class, 'destroy'])->name('events.guests.members.destroy');
        Route::post('events/{event}/guests/{guest}/members/{member}/move', [WorkspaceGuestMemberController::class, 'move'])->name('events.guests.members.move');
        Route::post('events/{event}/guests/{guest}/members/{member}/pass', [WorkspaceGuestMemberController::class, 'issuePass'])->name('events.guests.members.pass.issue');
        Route::delete('events/{event}/guests/{guest}/members/{member}/pass/{pass}', [WorkspaceGuestMemberController::class, 'revokePass'])->name('events.guests.members.pass.revoke');

        // Event-day check-in operations (permission-gated by CHECKIN_*).
        Route::get('events/{event}/checkins', [WorkspaceCheckinController::class, 'index'])->name('events.checkins.index');
        Route::get('events/{event}/checkins/scanner', [WorkspaceCheckinController::class, 'scanner'])->name('events.checkins.scanner');
        Route::post('events/{event}/checkins/scan', [WorkspaceCheckinController::class, 'scan'])->name('events.checkins.scan');
        Route::post('events/{event}/checkins/manual', [WorkspaceCheckinController::class, 'manual'])->name('events.checkins.manual');

        // Duplicate blueprint.
        Route::post('events/{event}/duplicate', [WorkspaceEventDuplicateController::class, 'store'])->name('events.duplicate');

        // Playbooks.
        Route::get('playbooks', [WorkspacePlaybookController::class, 'index'])->name('playbooks.index');
        Route::get('playbooks/{playbook}', [WorkspacePlaybookController::class, 'show'])->name('playbooks.show');
        Route::post('playbooks', [WorkspacePlaybookController::class, 'store'])->name('playbooks.store');
        Route::put('playbooks/{playbook}', [WorkspacePlaybookController::class, 'update'])->name('playbooks.update');
        Route::post('playbooks/{playbook}/duplicate', [WorkspacePlaybookController::class, 'duplicate'])->name('playbooks.duplicate');
        Route::post('playbooks/{playbook}/deactivate', [WorkspacePlaybookController::class, 'deactivate'])->name('playbooks.deactivate');
    });
