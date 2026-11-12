<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\VendorArrivalStatus;
use App\Enums\VendorLogisticsResponsibility;
use App\Enums\VendorLogisticsStatus;
use App\Enums\VendorLogisticsType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\AssertsEventInOrganization;
use App\Models\Event;
use App\Models\EventVendorEngagement;
use App\Models\EventVendorLogisticsRequirement;
use App\Models\EventVendorScheduleReference;
use App\Models\Organization;
use App\Services\VendorLogisticsService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceEventVendorLogisticsController extends Controller
{
    use AssertsEventInOrganization, AuthorizesRequests;

    public function __construct(private readonly VendorLogisticsService $logistics) {}

    public function storeSchedule(Request $request, Organization $organization, Event $event, EventVendorEngagement $engagement)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $engagement->event_id === (int) $event->id, 404);
        $this->authorize('manageVendorLogistics', $event);

        $data = $request->validate([
            'event_run_of_show_item_id' => [
                'nullable', 'integer',
                Rule::exists('event_run_of_show_items', 'id')->where('event_id', $event->id),
            ],
            'event_station_id' => [
                'nullable', 'integer',
                Rule::exists('event_stations', 'id')->where('event_id', $event->id),
            ],
            'vendor_contact_id' => ['nullable', 'integer', 'exists:vendor_contacts,id'],
            'expected_arrival_at' => ['required', 'date'],
            'setup_start_at' => ['nullable', 'date'],
            'setup_end_at' => ['nullable', 'date'],
            'delivery_start_at' => ['nullable', 'date'],
            'delivery_end_at' => ['nullable', 'date'],
            'service_start_at' => ['nullable', 'date'],
            'service_end_at' => ['nullable', 'date'],
            'teardown_start_at' => ['nullable', 'date'],
            'teardown_end_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->logistics->createSchedule($engagement, $data, $request->user());

        return back()->with('status', 'Referensi jadwal vendor disimpan.');
    }

    public function updateSchedule(Request $request, Organization $organization, Event $event, EventVendorScheduleReference $reference)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $reference->event_id === (int) $event->id, 404);
        $this->authorize('manageVendorLogistics', $event);

        $data = $request->validate([
            'event_run_of_show_item_id' => [
                'nullable', 'integer',
                Rule::exists('event_run_of_show_items', 'id')->where('event_id', $event->id),
            ],
            'event_station_id' => [
                'nullable', 'integer',
                Rule::exists('event_stations', 'id')->where('event_id', $event->id),
            ],
            'vendor_contact_id' => ['nullable', 'integer', 'exists:vendor_contacts,id'],
            'expected_arrival_at' => ['sometimes', 'date'],
            'setup_start_at' => ['nullable', 'date'],
            'setup_end_at' => ['nullable', 'date'],
            'delivery_start_at' => ['nullable', 'date'],
            'delivery_end_at' => ['nullable', 'date'],
            'service_start_at' => ['nullable', 'date'],
            'service_end_at' => ['nullable', 'date'],
            'teardown_start_at' => ['nullable', 'date'],
            'teardown_end_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->logistics->updateSchedule($reference, $data, $request->user());

        return back()->with('status', 'Jadwal vendor diperbarui.');
    }

    public function arrival(Request $request, Organization $organization, Event $event, EventVendorScheduleReference $reference)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $reference->event_id === (int) $event->id, 404);
        $this->authorize('manageVendorLogistics', $event);

        $data = $request->validate([
            'arrival_status' => ['required', Rule::enum(VendorArrivalStatus::class)],
        ]);

        $this->logistics->transitionArrival(
            $reference,
            VendorArrivalStatus::from($data['arrival_status']),
            $request->user(),
        );

        return back()->with('status', 'Status kedatangan vendor diperbarui.');
    }

    public function storeLogistics(Request $request, Organization $organization, Event $event, EventVendorEngagement $engagement)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $engagement->event_id === (int) $event->id, 404);
        $this->authorize('manageVendorLogistics', $event);

        $data = $request->validate([
            'type' => ['required', Rule::enum(VendorLogisticsType::class)],
            'description' => ['required', 'string', 'max:2000'],
            'responsibility' => ['required', Rule::enum(VendorLogisticsResponsibility::class)],
            'event_station_id' => [
                'nullable', 'integer',
                Rule::exists('event_stations', 'id')->where('event_id', $event->id),
            ],
            'due_at' => ['nullable', 'date'],
        ]);

        $this->logistics->createLogistics($engagement, $data, $request->user());

        return back()->with('status', 'Kebutuhan logistik vendor dicatat.');
    }

    public function updateLogistics(Request $request, Organization $organization, Event $event, EventVendorLogisticsRequirement $requirement)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $requirement->event_id === (int) $event->id, 404);
        $this->authorize('manageVendorLogistics', $event);

        $data = $request->validate([
            'type' => ['sometimes', Rule::enum(VendorLogisticsType::class)],
            'description' => ['sometimes', 'string', 'max:2000'],
            'responsibility' => ['sometimes', Rule::enum(VendorLogisticsResponsibility::class)],
            'event_station_id' => [
                'nullable', 'integer',
                Rule::exists('event_stations', 'id')->where('event_id', $event->id),
            ],
            'due_at' => ['nullable', 'date'],
        ]);

        $this->logistics->updateLogistics($requirement, $data, $request->user());

        return back()->with('status', 'Kebutuhan logistik diperbarui.');
    }

    public function logisticsStatus(Request $request, Organization $organization, Event $event, EventVendorLogisticsRequirement $requirement)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $requirement->event_id === (int) $event->id, 404);
        $this->authorize('manageVendorLogistics', $event);

        $data = $request->validate([
            'status' => ['required', Rule::enum(VendorLogisticsStatus::class)],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->logistics->transitionLogistics(
            $requirement,
            VendorLogisticsStatus::from($data['status']),
            $request->user(),
            $data['resolution_note'] ?? null,
        );

        return back()->with('status', 'Status logistik vendor diperbarui.');
    }
}
