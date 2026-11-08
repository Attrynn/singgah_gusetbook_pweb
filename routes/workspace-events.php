<?php

use App\Http\Controllers\Workspace\WorkspaceEventController;
use App\Http\Controllers\Workspace\WorkspaceEventDuplicateController;
use App\Http\Controllers\Workspace\WorkspaceEventScheduleController;
use App\Http\Controllers\Workspace\WorkspaceEventTeamController;
use App\Http\Controllers\Workspace\WorkspacePlaybookController;
use App\Http\Controllers\Workspace\WorkspaceRunOfShowController;
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
