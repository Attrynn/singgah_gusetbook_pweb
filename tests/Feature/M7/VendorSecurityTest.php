<?php

namespace Tests\Feature\M7;

use App\Enums\EventStaffRole;
use App\Enums\OrganizationRole;
use App\Enums\VendorChangeRequestStatus;
use App\Enums\VendorDocumentVisibility;
use App\Enums\VendorStatus;
use App\Enums\VendorType;
use App\Models\EventVendorChangeRequest;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\M7\Concerns\BuildsVendorFixtures;
use Tests\TestCase;

class VendorSecurityTest extends TestCase
{
    use BuildsVendorFixtures, RefreshDatabase;

    private function ownerOf(Organization $organization): User
    {
        return $this->memberOf($organization, OrganizationRole::Owner);
    }

    public function test_owner_cannot_open_a_vendor_belonging_to_another_organization(): void
    {
        $organization = $this->organization('alpha');
        $otherOrganization = $this->organization('beta');
        $event = $this->eventIn($organization);
        $vendor = $this->vendorIn($otherOrganization, ['name' => 'Other Tenant Vendor']);
        $owner = $this->ownerOf($organization);

        $this->actingAs($owner)
            ->get(route('workspace.vendors.show', [$organization, $vendor]))
            ->assertStatus(404);

        $this->actingAs($owner)
            ->get(route('workspace.vendors.edit', [$organization, $vendor]))
            ->assertStatus(404);

        $this->actingAs($owner)
            ->put(route('workspace.vendors.update', [$organization, $vendor]), ['name' => 'Hijacked'])
            ->assertStatus(404);

        $this->assertSame('Other Tenant Vendor', $vendor->fresh()->name);
        $this->assertSame($otherOrganization->id, $vendor->fresh()->organization_id);
    }

    public function test_owner_cannot_list_vendors_from_another_organization(): void
    {
        $organization = $this->organization('alpha');
        $otherOrganization = $this->organization('beta');
        $ownVendor = $this->vendorIn($organization, ['name' => 'Alpha Local Vendor']);
        $foreignVendor = $this->vendorIn($otherOrganization, ['name' => 'Beta Hidden Vendor']);
        $owner = $this->ownerOf($organization);

        $this->actingAs($owner)
            ->get(route('workspace.vendors.index', $organization))
            ->assertOk()
            ->assertSee($ownVendor->name)
            ->assertDontSee($foreignVendor->name);
    }

    public function test_owner_cannot_download_a_document_from_another_organization(): void
    {
        $organization = $this->organization('alpha');
        $otherOrganization = $this->organization('beta');
        $event = $this->eventIn($organization);
        $otherEvent = $this->eventIn($otherOrganization);
        $otherVendor = $this->vendorIn($otherOrganization);
        $otherEngagement = $this->engagementFor($otherEvent, $otherVendor);
        $document = $this->documentFor($otherEngagement);
        $owner = $this->ownerOf($organization);

        $this->actingAs($owner)
            ->get(route('workspace.events.vendors.documents.download', [$organization, $event, $document]))
            ->assertStatus(404);
    }

    public function test_engagement_from_another_event_is_not_found(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $otherEvent = $this->eventIn($organization);
        $vendor = $this->vendorIn($organization);
        $foreignEngagement = $this->engagementFor($otherEvent, $vendor);
        $owner = $this->ownerOf($organization);

        $this->actingAs($owner)
            ->get(route('workspace.events.vendors.engagements.show', [$organization, $event, $foreignEngagement]))
            ->assertStatus(404);
    }

    public function test_deliverable_from_another_engagement_is_not_found(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $otherEvent = $this->eventIn($organization);
        $vendor = $this->vendorIn($organization);
        $foreignEngagement = $this->engagementFor($otherEvent, $vendor);
        $foreignDeliverable = $this->deliverableFor($foreignEngagement);
        $owner = $this->ownerOf($organization);

        $this->actingAs($owner)
            ->put(
                route('workspace.events.vendors.deliverables.update', [$organization, $event, $foreignDeliverable]),
                ['title' => 'Hijacked deliverable'],
            )
            ->assertStatus(404);

        $this->assertNotSame('Hijacked deliverable', $foreignDeliverable->fresh()->title);
    }

    public function test_document_from_another_event_is_not_found(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $otherEvent = $this->eventIn($organization);
        $vendor = $this->vendorIn($organization);
        $foreignEngagement = $this->engagementFor($otherEvent, $vendor);
        $foreignDocument = $this->documentFor($foreignEngagement);
        $owner = $this->ownerOf($organization);

        $this->actingAs($owner)
            ->get(route('workspace.events.vendors.documents.download', [$organization, $event, $foreignDocument]))
            ->assertStatus(404);
    }

    public function test_staff_with_only_vendor_view_cannot_perform_privileged_vendor_actions(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $vendor = $this->vendorIn($organization);
        $engagement = $this->engagementFor($event, $vendor);
        $staff = $this->staffOf($organization, $event, EventStaffRole::GuestManager);
        $user = $staff->user;

        $changeRequest = EventVendorChangeRequest::create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_vendor_engagement_id' => $engagement->id,
            'title' => 'Perubahan lingkup',
            'description' => 'Permintaan perubahan untuk diuji.',
            'proposed_changes' => ['amount' => 1000000],
            'status' => VendorChangeRequestStatus::Submitted->value,
            'submitted_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('workspace.events.vendors.engagements.store', [$organization, $event]), [
                'vendor_id' => $vendor->id,
                'title' => 'Engagement tidak sah',
            ])
            ->assertStatus(403);

        $this->actingAs($user)
            ->post(route('workspace.events.vendors.change-requests.decision', [$organization, $event, $changeRequest]), [
                'status' => VendorChangeRequestStatus::Approved->value,
            ])
            ->assertStatus(403);

        $this->actingAs($user)
            ->post(route('workspace.events.vendors.portal.invite', [$organization, $event, $engagement]), [
                'email' => 'portal@example.test',
            ])
            ->assertStatus(403);

        $this->actingAs($user)
            ->get(route('workspace.events.vendors.reports.export', [$organization, $event]).'?type=engagements')
            ->assertStatus(403);

        $this->assertDatabaseCount('event_vendor_engagements', 1);
        $this->assertDatabaseCount('vendor_portal_memberships', 0);
        $this->assertSame(VendorChangeRequestStatus::Submitted, $changeRequest->fresh()->status);
    }

    public function test_vendor_create_ignores_client_supplied_scope_fields(): void
    {
        $organization = $this->organization('alpha');
        $otherOrganization = $this->organization('beta');
        $event = $this->eventIn($organization);
        $owner = $this->ownerOf($organization);

        $this->actingAs($owner)
            ->post(route('workspace.vendors.store', $organization), [
                'name' => 'Mass Assignment Vendor',
                'type' => VendorType::Company->value,
                'organization_id' => $otherOrganization->id,
                'status' => VendorStatus::Archived->value,
                'event_id' => $event->id,
            ])
            ->assertRedirect();

        $vendor = Vendor::query()->where('name', 'Mass Assignment Vendor')->firstOrFail();

        $this->assertSame($organization->id, $vendor->organization_id);
        $this->assertSame(VendorStatus::PendingVerification, $vendor->status);
        $this->assertDatabaseMissing('vendors', [
            'name' => 'Mass Assignment Vendor',
            'organization_id' => $otherOrganization->id,
        ]);
    }

    public function test_portal_endpoints_never_expose_internal_notes(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $vendor = $this->vendorIn($organization, [
            'internal_notes' => 'INTERNAL-VENDOR-NOTE-XYZ',
        ]);
        $engagement = $this->engagementFor($event, $vendor, [
            'notes' => 'INTERNAL-ENGAGEMENT-NOTE-XYZ',
        ]);
        $portalUser = $this->portalUser();

        $membership = $this->portalMemberFor($vendor, $portalUser);
        $this->grantPortalAccess($membership, $engagement);

        $this->documentFor($engagement, [
            'visibility' => VendorDocumentVisibility::VendorVisible->value,
            'notes' => 'INTERNAL-DOCUMENT-NOTE-XYZ',
        ]);

        $this->actingAs($portalUser)
            ->get(route('vendor-portal.index'))
            ->assertOk()
            ->assertDontSee('INTERNAL-VENDOR-NOTE-XYZ');

        $this->actingAs($portalUser)
            ->get(route('vendor-portal.engagements.show', $engagement))
            ->assertOk()
            ->assertDontSee('INTERNAL-VENDOR-NOTE-XYZ')
            ->assertDontSee('INTERNAL-ENGAGEMENT-NOTE-XYZ')
            ->assertDontSee('INTERNAL-DOCUMENT-NOTE-XYZ');
    }

    public function test_portal_invitation_creation_writes_an_audit_log(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $vendor = $this->vendorIn($organization);
        $engagement = $this->engagementFor($event, $vendor);
        $owner = $this->ownerOf($organization);

        $this->actingAs($owner)
            ->post(route('workspace.events.vendors.portal.invite', [$organization, $event, $engagement]), [
                'email' => 'audit-invite@example.test',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'vendor.portal.invited',
            'event_id' => $event->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_internal_document_download_writes_an_audit_log(): void
    {
        Storage::fake('local');

        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $vendor = $this->vendorIn($organization);
        $engagement = $this->engagementFor($event, $vendor);
        $document = $this->documentFor($engagement, [
            'visibility' => VendorDocumentVisibility::InternalOnly->value,
        ]);
        Storage::disk('local')->put($document->path, 'PDF-CONTENT');
        $owner = $this->ownerOf($organization);

        $this->actingAs($owner)
            ->get(route('workspace.events.vendors.documents.download', [$organization, $event, $document]))
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'vendor.document.downloaded',
            'event_id' => $event->id,
            'user_id' => $owner->id,
        ]);
    }
}
