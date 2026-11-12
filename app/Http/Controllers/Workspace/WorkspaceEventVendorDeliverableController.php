<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\VendorDeliverableStatus;
use App\Enums\VendorSubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\AssertsEventInOrganization;
use App\Models\Event;
use App\Models\EventTask;
use App\Models\EventVendorDeliverable;
use App\Models\EventVendorDeliverableSubmission;
use App\Models\EventVendorEngagement;
use App\Models\Organization;
use App\Services\VendorDeliverableService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceEventVendorDeliverableController extends Controller
{
    use AssertsEventInOrganization, AuthorizesRequests;

    public function __construct(private readonly VendorDeliverableService $deliverables) {}

    public function store(Request $request, Organization $organization, Event $event, EventVendorEngagement $engagement)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $engagement->event_id === (int) $event->id, 404);
        $this->authorize('reviewVendorDeliverables', $event);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'vendor_contact_id' => ['nullable', 'integer', 'exists:vendor_contacts,id'],
            'reviewer_membership_id' => [
                'nullable', 'integer',
                Rule::exists('event_staff_memberships', 'id')->where('event_id', $event->id),
            ],
            'due_at' => ['nullable', 'date'],
            'sequence' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->deliverables->create($engagement, $data, $request->user());

        return back()->with('status', 'Deliverable vendor dibuat.');
    }

    public function update(Request $request, Organization $organization, Event $event, EventVendorDeliverable $deliverable)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $deliverable->event_id === (int) $event->id, 404);
        $this->authorize('reviewVendorDeliverables', $event);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'vendor_contact_id' => ['nullable', 'integer', 'exists:vendor_contacts,id'],
            'reviewer_membership_id' => [
                'nullable', 'integer',
                Rule::exists('event_staff_memberships', 'id')->where('event_id', $event->id),
            ],
            'due_at' => ['nullable', 'date'],
            'sequence' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->deliverables->update($deliverable, $data, $request->user());

        return back()->with('status', 'Deliverable vendor diperbarui.');
    }

    public function status(Request $request, Organization $organization, Event $event, EventVendorDeliverable $deliverable)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $deliverable->event_id === (int) $event->id, 404);
        $this->authorize('reviewVendorDeliverables', $event);

        $data = $request->validate([
            'status' => ['required', Rule::enum(VendorDeliverableStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->deliverables->transition(
            $deliverable,
            VendorDeliverableStatus::from($data['status']),
            $request->user(),
            $data['notes'] ?? null,
        );

        return back()->with('status', 'Status deliverable diperbarui.');
    }

    public function submit(Request $request, Organization $organization, Event $event, EventVendorDeliverable $deliverable)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $deliverable->event_id === (int) $event->id, 404);
        $this->authorize('reviewVendorDeliverables', $event);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:4000'],
            'event_vendor_document_id' => ['nullable', 'integer', 'exists:event_vendor_documents,id'],
        ]);

        $this->deliverables->submit($deliverable, $data, $request->user());

        return back()->with('status', 'Deliverable diserahkan untuk ditinjau.');
    }

    public function review(
        Request $request,
        Organization $organization,
        Event $event,
        EventVendorDeliverable $deliverable,
        EventVendorDeliverableSubmission $submission,
    ) {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $deliverable->event_id === (int) $event->id, 404);
        abort_unless((int) $submission->event_vendor_deliverable_id === (int) $deliverable->id, 404);
        $this->authorize('reviewVendorDeliverables', $event);

        $data = $request->validate([
            'status' => ['required', Rule::enum(VendorSubmissionStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->deliverables->review(
            $submission,
            VendorSubmissionStatus::from($data['status']),
            $request->user(),
            $data['notes'] ?? null,
        );

        return back()->with('status', 'Penyerahan deliverable ditinjau.');
    }

    public function linkTask(Request $request, Organization $organization, Event $event, EventVendorDeliverable $deliverable)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $deliverable->event_id === (int) $event->id, 404);
        $this->authorize('reviewVendorDeliverables', $event);

        $data = $request->validate([
            'event_task_id' => [
                'required', 'integer',
                Rule::exists('event_tasks', 'id')->where('event_id', $event->id),
            ],
        ]);

        $task = EventTask::query()->where('event_id', $event->id)->findOrFail($data['event_task_id']);

        $this->deliverables->linkTask($deliverable, $task, $request->user());

        return back()->with('status', 'Deliverable ditautkan ke tugas internal.');
    }
}
