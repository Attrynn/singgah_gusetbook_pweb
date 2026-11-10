<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\ManualCheckinRequest;
use App\Http\Requests\Workspace\ScanCheckinRequest;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Organization;
use App\Services\AttendanceService;
use App\Services\CheckinService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class WorkspaceCheckinController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CheckinService $checkins,
        private readonly AttendanceService $attendance,
    ) {}

    /**
     * Event-scoped check-in history page.
     */
    public function index(Request $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('viewCheckins', $event);

        $query = Checkin::with(['guest', 'officer'])
            ->where('event_id', $event->id);

        if ($search = trim((string) ($request->input('search') ?? ''))) {
            $query->whereHas('guest', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        $checkins = $query->latest('checked_in_at')->paginate(20)->withQueryString();

        return view('workspace.events.checkins.index', [
            'organization' => $organization,
            'event' => $event,
            'checkins' => $checkins,
            'stats' => $this->attendance->dashboardStats($event),
            'distribution' => $this->attendance->checkinDistribution($event),
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Event-scoped check-in scanner page.
     */
    public function scanner(Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('scanCheckins', $event);

        return view('workspace.events.checkins.scanner', [
            'organization' => $organization,
            'event' => $event,
            'stats' => $this->attendance->dashboardStats($event),
        ]);
    }

    /**
     * Scan an individual's guest pass or a legacy party QR token.
     */
    public function scan(ScanCheckinRequest $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('createCheckins', $event);

        $result = $this->checkins->scan(
            $request->validated('token'),
            $request->user(),
            $event,
        );

        return response()->json([
            'status' => $result['status'],
            'message' => $result['message'],
            'warning' => $result['warning'],
            'guest' => $result['guest']
                ? [
                    'id' => $result['guest']->id,
                    'name' => $result['guest']->name,
                    'rsvp' => $result['guest']->rsvp_status?->label(),
                    'checked_in_at' => $result['guest']->checked_in_at?->toIso8601String(),
                ]
                : null,
        ]);
    }

    /**
     * Manual check-in by guest name / party.
     */
    public function manual(ManualCheckinRequest $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('createCheckins', $event);

        $guest = Guest::where('event_id', $event->id)
            ->findOrFail($request->validated('guest_id'));

        $result = $this->checkins->manual($guest, $request->user(), $request->validated('notes'));

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $result['status'],
                'message' => $result['message'],
                'warning' => $result['warning'],
                'guest' => $result['guest']
                    ? [
                        'id' => $result['guest']->id,
                        'name' => $result['guest']->name,
                        'checked_in_at' => $result['guest']->checked_in_at?->toIso8601String(),
                    ]
                    : null,
            ]);
        }

        return back()->with('status', $result['message']);
    }

    protected function assertEventInOrg(Organization $organization, Event $event): void
    {
        abort_unless((int) $event->organization_id === (int) $organization->id, 404);
    }
}
