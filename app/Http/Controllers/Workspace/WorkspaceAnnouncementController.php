<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementPriority;
use App\Enums\AnnouncementStatus;
use App\Enums\EventStaffRole;
use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\AssertsEventInOrganization;
use App\Models\Event;
use App\Models\EventAnnouncement;
use App\Models\EventStation;
use App\Models\Organization;
use App\Services\AnnouncementService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceAnnouncementController extends Controller
{
    use AssertsEventInOrganization, AuthorizesRequests;

    public function __construct(private readonly AnnouncementService $announcements) {}

    public function index(Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('viewAnnouncements', $event);

        $user = request()->user();

        $this->announcements->expireDue($event);

        $announcements = EventAnnouncement::query()
            ->where('event_id', $event->id)
            ->with(['creator', 'publisher'])
            ->latest()
            ->get();

        return view('workspace.events.operations.announcements', [
            'organization' => $organization,
            'event' => $event,
            'announcements' => $announcements,
            'progress' => $announcements->mapWithKeys(
                fn (EventAnnouncement $announcement) => [$announcement->id => $this->announcements->progress($announcement)],
            ),
            'myRecipients' => $announcements
                ->flatMap(fn (EventAnnouncement $announcement) => $announcement->recipients()->where('user_id', $user->id)->get())
                ->keyBy('event_announcement_id'),
            'stations' => EventStation::query()->where('event_id', $event->id)->orderBy('name')->get(),
            'staff' => $event->staffMemberships()
                ->with('user')
                ->where('status', MembershipStatus::Active->value)
                ->get(),
            'roles' => EventStaffRole::operationalProfiles(),
            'audiences' => AnnouncementAudience::cases(),
            'priorities' => AnnouncementPriority::cases(),
            'statuses' => AnnouncementStatus::cases(),
            'canManage' => $user->can('manageAnnouncements', $event),
            'canPublish' => $user->can('publishAnnouncements', $event),
        ]);
    }

    public function store(Request $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageAnnouncements', $event);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:4000'],
            'priority' => ['nullable', Rule::enum(AnnouncementPriority::class)],
            'audience' => ['nullable', Rule::enum(AnnouncementAudience::class)],
            'audience_ref' => ['nullable', 'array'],
            'audience_ref.station_ids' => ['nullable', 'array'],
            'audience_ref.station_ids.*' => ['integer'],
            'audience_ref.membership_ids' => ['nullable', 'array'],
            'audience_ref.membership_ids.*' => ['integer'],
            'audience_ref.roles' => ['nullable', 'array'],
            'audience_ref.roles.*' => ['string', Rule::enum(EventStaffRole::class)],
            'requires_acknowledgement' => ['sometimes', 'boolean'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $this->announcements->create($event, $data, $request->user());

        return back()->with('status', 'Pengumuman dibuat.');
    }

    public function update(Request $request, Organization $organization, Event $event, EventAnnouncement $announcement)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $announcement->event_id === (int) $event->id, 404);
        $this->authorize('manageAnnouncements', $event);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'body' => ['sometimes', 'required', 'string', 'max:4000'],
            'priority' => ['nullable', Rule::enum(AnnouncementPriority::class)],
            'audience' => ['nullable', Rule::enum(AnnouncementAudience::class)],
            'audience_ref' => ['nullable', 'array'],
            'audience_ref.station_ids' => ['nullable', 'array'],
            'audience_ref.station_ids.*' => ['integer'],
            'audience_ref.membership_ids' => ['nullable', 'array'],
            'audience_ref.membership_ids.*' => ['integer'],
            'audience_ref.roles' => ['nullable', 'array'],
            'audience_ref.roles.*' => ['string', Rule::enum(EventStaffRole::class)],
            'requires_acknowledgement' => ['sometimes', 'boolean'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $this->announcements->update($event, $announcement, $data, $request->user());

        return back()->with('status', 'Pengumuman diperbarui.');
    }

    public function publish(Organization $organization, Event $event, EventAnnouncement $announcement)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $announcement->event_id === (int) $event->id, 404);
        $this->authorize('publishAnnouncements', $event);

        $this->announcements->publish($event, $announcement, request()->user());

        return back()->with('status', 'Pengumuman diterbitkan.');
    }

    public function withdraw(Organization $organization, Event $event, EventAnnouncement $announcement)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $announcement->event_id === (int) $event->id, 404);
        $this->authorize('publishAnnouncements', $event);

        $this->announcements->withdraw($event, $announcement, request()->user());

        return back()->with('status', 'Pengumuman ditarik.');
    }

    public function read(Organization $organization, Event $event, EventAnnouncement $announcement)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $announcement->event_id === (int) $event->id, 404);
        $this->authorize('viewAnnouncements', $event);

        $this->announcements->markRead($announcement, request()->user());

        return back()->with('status', 'Pengumuman ditandai sudah dibaca.');
    }

    public function acknowledge(Organization $organization, Event $event, EventAnnouncement $announcement)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $announcement->event_id === (int) $event->id, 404);
        $this->authorize('viewAnnouncements', $event);

        $this->announcements->acknowledge($announcement, request()->user());

        return back()->with('status', 'Pengumuman dikonfirmasi.');
    }
}
