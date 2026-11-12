<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\AssertsEventInOrganization;
use App\Models\Event;
use App\Models\EventVendorEngagement;
use App\Models\Organization;
use App\Models\VendorPortalMembership;
use App\Services\AuditService;
use App\Services\VendorPortalAccessService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceEventVendorPortalController extends Controller
{
    use AssertsEventInOrganization, AuthorizesRequests;

    public function __construct(
        private readonly VendorPortalAccessService $access,
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request, Organization $organization, Event $event, EventVendorEngagement $engagement)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $engagement->event_id === (int) $event->id, 404);
        $this->authorize('manageVendorPortal', $event);

        $memberships = VendorPortalMembership::query()
            ->where('vendor_id', $engagement->vendor_id)
            ->with(['user', 'vendorContact', 'engagementAccess' => fn ($query) => $query->where('event_vendor_engagement_id', $engagement->id)])
            ->latest()
            ->get();

        return view('workspace.events.vendors.portal', [
            'organization' => $organization,
            'event' => $event,
            'engagement' => $engagement,
            'memberships' => $memberships,
            'invitationUrl' => session('portal_invitation_url'),
        ]);
    }

    public function invite(Request $request, Organization $organization, Event $event, EventVendorEngagement $engagement)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $engagement->event_id === (int) $event->id, 404);
        $this->authorize('manageVendorPortal', $event);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:160'],
            'vendor_contact_id' => [
                'nullable',
                'integer',
                Rule::exists('vendor_contacts', 'id')->where('vendor_id', $engagement->vendor_id),
            ],
        ]);

        [$membership, $token] = $this->access->invite(
            $engagement->vendor,
            isset($data['vendor_contact_id']) ? $engagement->vendor->contacts()->find($data['vendor_contact_id']) : null,
            $data['email'],
            $request->user(),
        );

        $this->audit->record(
            'vendor.portal.invited',
            "Undangan portal vendor dibuat untuk {$data['email']}.",
            $event,
            $request->user(),
            $membership,
        );

        // Delivery is not confirmed here: the invitation link is surfaced to the
        // manager, who shares it through a verified channel.
        return redirect()
            ->route('workspace.events.vendors.portal.index', [$organization, $event, $engagement])
            ->with('status', 'Undangan portal dibuat. Bagikan tautan berikut secara aman.')
            ->with('portal_invitation_url', route('vendor-portal.invitations.show', ['token' => $token]));
    }

    public function grant(Request $request, Organization $organization, Event $event, VendorPortalMembership $membership)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $membership->organization_id === (int) $organization->id, 404);
        $this->authorize('manageVendorPortal', $event);

        $data = $request->validate([
            'event_vendor_engagement_id' => ['required', 'integer'],
        ]);

        $engagement = EventVendorEngagement::query()
            ->where('event_id', $event->id)
            ->where('vendor_id', $membership->vendor_id)
            ->find($data['event_vendor_engagement_id']);

        abort_if($engagement === null, 404);

        $this->access->grant($membership, $engagement, $request->user());

        $this->audit->recordCritical(
            'vendor.portal.access.granted',
            'Akses portal diberikan untuk satu engagement vendor.',
            $event,
            $request->user(),
            $membership,
            ['engagement_id' => $engagement->id],
        );

        return back()->with('status', 'Akses engagement diberikan.');
    }

    public function revoke(Request $request, Organization $organization, Event $event, VendorPortalMembership $membership)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $membership->organization_id === (int) $organization->id, 404);
        $this->authorize('manageVendorPortal', $event);

        $this->access->revokeMembership($membership, $request->user());

        $this->audit->recordCritical(
            'vendor.portal.access.revoked',
            'Akses portal vendor dicabut.',
            $event,
            $request->user(),
            $membership,
        );

        return back()->with('status', 'Akses portal dicabut.');
    }
}
