<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\AssertsEventInOrganization;
use App\Models\Event;
use App\Models\FloorPlan;
use App\Models\FloorPlanVersion;
use App\Models\Organization;
use App\Models\SeatingAssignment;
use App\Models\SpatialReference;
use App\Services\FloorPlanValidationService;
use App\Services\FloorPlanVersionService;
use App\Services\SeatingAssignmentService;
use App\Services\SpatialReferenceService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceFloorPlanVersionController extends Controller
{
    use AssertsEventInOrganization, AuthorizesRequests;

    public function __construct(
        private readonly FloorPlanVersionService $versions,
        private readonly FloorPlanValidationService $validator,
        private readonly SeatingAssignmentService $seating,
        private readonly SpatialReferenceService $spatial,
    ) {}

    public function store(Request $request, Organization $organization, Event $event, FloorPlan $floorPlan): RedirectResponse
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageFloorPlanVersions', $event);

        $version = $this->versions->createNextDraft($event, $floorPlan, $request->user());

        return redirect()
            ->route('workspace.events.floor-plans.editor', [$organization, $event, $floorPlan])
            ->with('status', "Draf versi {$version->version_number} dibuat.");
    }

    public function show(
        Organization $organization,
        Event $event,
        FloorPlan $floorPlan,
        FloorPlanVersion $version,
    ): JsonResponse {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('viewFloorPlans', $event);
        $this->assertVersion($event, $floorPlan, $version);

        return response()->json([
            'version' => $version->summary(),
            'document' => $version->document,
            'lock_version' => $version->lock_version,
            'editable' => $version->isDraft(),
        ]);
    }

    public function saveDocument(
        Request $request,
        Organization $organization,
        Event $event,
        FloorPlan $floorPlan,
        FloorPlanVersion $version,
    ): JsonResponse {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageFloorPlans', $event);
        $this->assertVersion($event, $floorPlan, $version);

        $data = $request->validate([
            'document' => ['required', 'array'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ]);

        $saved = $this->versions->saveDocument(
            $event,
            $version,
            $data['document'],
            (int) $data['lock_version'],
            $request->user(),
        );

        $this->seating->syncReviewStatus($event, $saved);
        $this->spatial->syncReviewStatus($saved);

        return response()->json([
            'saved' => true,
            'lock_version' => $saved->lock_version,
            'stats' => [
                'object_count' => $saved->object_count,
                'table_count' => $saved->table_count,
                'chair_count' => $saved->chair_count,
                'zone_count' => $saved->zone_count,
                'seat_capacity' => $saved->seat_capacity,
            ],
            'saved_at' => $saved->updated_at?->toIso8601String(),
        ]);
    }

    public function publish(
        Request $request,
        Organization $organization,
        Event $event,
        FloorPlan $floorPlan,
        FloorPlanVersion $version,
    ): JsonResponse|RedirectResponse {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('publishFloorPlans', $event);
        $this->assertVersion($event, $floorPlan, $version);

        $published = $this->versions->publish($event, $version, $request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'published' => true,
                'version' => $published->summary(),
            ]);
        }

        return redirect()
            ->route('workspace.events.floor-plans.show', [$organization, $event, $floorPlan])
            ->with('status', "Versi {$published->version_number} diterbitkan.");
    }

    public function archive(
        Request $request,
        Organization $organization,
        Event $event,
        FloorPlan $floorPlan,
        FloorPlanVersion $version,
    ): RedirectResponse {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageFloorPlanVersions', $event);
        $this->assertVersion($event, $floorPlan, $version);

        $this->versions->archive($event, $version, $request->user());

        return back()->with('status', 'Versi floor plan diarsipkan.');
    }

    public function validate(
        Organization $organization,
        Event $event,
        FloorPlan $floorPlan,
        FloorPlanVersion $version,
    ): JsonResponse {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('viewFloorPlans', $event);
        $this->assertVersion($event, $floorPlan, $version);

        $issues = $this->validator->issues($event, $version);

        return response()->json([
            'issues' => $issues,
            'counts' => [
                'error' => count(array_filter($issues, fn (array $issue): bool => $issue['severity'] === 'error')),
                'warning' => count(array_filter($issues, fn (array $issue): bool => $issue['severity'] === 'warning')),
                'info' => count(array_filter($issues, fn (array $issue): bool => $issue['severity'] === 'info')),
            ],
        ]);
    }

    public function seatingIndex(
        Organization $organization,
        Event $event,
        FloorPlan $floorPlan,
        FloorPlanVersion $version,
    ): JsonResponse {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('viewSeating', $event);
        $this->assertVersion($event, $floorPlan, $version);

        return response()->json([
            'tables' => $this->seating->tableSummary($version),
            'assignments' => $this->seating->forVersion($event, $version)->map(fn (SeatingAssignment $assignment): array => [
                'id' => $assignment->id,
                'table_object_id' => $assignment->table_object_id,
                'seat_object_id' => $assignment->seat_object_id,
                'guest_id' => $assignment->guest_id,
                'guest_name' => $assignment->guest->name,
                'member_id' => $assignment->guest_party_membership_id,
                'member_name' => $assignment->member?->name,
                'headcount' => $assignment->headcount,
            ])->values(),
        ]);
    }

    public function seatingStore(
        Request $request,
        Organization $organization,
        Event $event,
        FloorPlan $floorPlan,
        FloorPlanVersion $version,
    ): JsonResponse {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('assignSeating', $event);
        $this->assertVersion($event, $floorPlan, $version);

        $data = $request->validate([
            'table_object_id' => ['required', 'string', 'max:64'],
            'seat_object_id' => ['nullable', 'string', 'max:64'],
            'guest_id' => ['required', 'integer'],
            'guest_party_membership_id' => ['nullable', 'integer'],
            'headcount' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $assignment = $this->seating->assign($event, $version, $data, $request->user());

        return response()->json([
            'assignment' => [
                'id' => $assignment->id,
                'table_object_id' => $assignment->table_object_id,
                'seat_object_id' => $assignment->seat_object_id,
                'guest_id' => $assignment->guest_id,
                'guest_party_membership_id' => $assignment->guest_party_membership_id,
                'headcount' => $assignment->headcount,
            ],
            'tables' => $this->seating->tableSummary($version),
        ], 201);
    }

    public function seatingDestroy(
        Request $request,
        Organization $organization,
        Event $event,
        FloorPlan $floorPlan,
        FloorPlanVersion $version,
        SeatingAssignment $assignment,
    ): JsonResponse {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('assignSeating', $event);
        $this->assertVersion($event, $floorPlan, $version);
        abort_unless((int) $assignment->floor_plan_version_id === (int) $version->id, 404);

        $reason = $request->input('reason');

        $this->seating->release($event, $assignment, is_string($reason) ? $reason : null, $request->user());

        return response()->json([
            'released' => true,
            'tables' => $this->seating->tableSummary($version),
        ]);
    }

    public function spatialReferenceStore(
        Request $request,
        Organization $organization,
        Event $event,
        FloorPlan $floorPlan,
        FloorPlanVersion $version,
    ): JsonResponse {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageFloorPlans', $event);
        $this->assertVersion($event, $floorPlan, $version);

        $data = $request->validate([
            'object_id' => ['required', 'string', 'max:64'],
            'object_type' => ['nullable', 'string', 'max:30'],
            'referenceable_type' => ['required', 'string', Rule::in(array_keys(SpatialReferenceService::TARGETS))],
            'referenceable_id' => ['required', 'integer'],
            'label' => ['nullable', 'string', 'max:160'],
        ]);

        $reference = $this->spatial->attach($event, $version, $data, $request->user());

        return response()->json([
            'reference' => [
                'id' => $reference->id,
                'object_id' => $reference->object_id,
                'object_type' => $reference->object_type,
                'referenceable_type' => $reference->referenceable_type,
                'referenceable_id' => $reference->referenceable_id,
                'status' => $reference->status->value,
            ],
        ], 201);
    }

    public function spatialReferenceDestroy(
        Request $request,
        Organization $organization,
        Event $event,
        FloorPlan $floorPlan,
        FloorPlanVersion $version,
        SpatialReference $spatialReference,
    ): JsonResponse {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageFloorPlans', $event);
        $this->assertVersion($event, $floorPlan, $version);
        abort_unless((int) $spatialReference->floor_plan_version_id === (int) $version->id, 404);

        $this->spatial->detach($event, $spatialReference, $request->user());

        return response()->json(['removed' => true]);
    }

    protected function assertVersion(Event $event, FloorPlan $floorPlan, FloorPlanVersion $version): void
    {
        abort_unless((int) $version->event_id === (int) $event->id, 404);
        abort_unless((int) $version->floor_plan_id === (int) $floorPlan->id, 404);
    }
}
