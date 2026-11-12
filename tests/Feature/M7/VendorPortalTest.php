<?php

namespace Tests\Feature\M7;

use App\Enums\OrganizationRole;
use App\Enums\VendorArrivalStatus;
use App\Enums\VendorChangeRequestStatus;
use App\Enums\VendorCommunicationDirection;
use App\Enums\VendorCommunicationType;
use App\Enums\VendorCommunicationVisibility;
use App\Enums\VendorDeliverableStatus;
use App\Enums\VendorDocumentVisibility;
use App\Enums\VendorLogisticsResponsibility;
use App\Enums\VendorLogisticsStatus;
use App\Enums\VendorLogisticsType;
use App\Enums\VendorPortalStatus;
use App\Models\Event;
use App\Models\EventVendorCommunication;
use App\Models\EventVendorEngagement;
use App\Models\EventVendorLogisticsRequirement;
use App\Models\EventVendorScheduleReference;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPortalMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Feature\M7\Concerns\BuildsVendorFixtures;
use Tests\TestCase;

class VendorPortalTest extends TestCase
{
    use BuildsVendorFixtures, RefreshDatabase;

    private function ownerOf(Organization $organization): User
    {
        return $this->memberOf($organization, OrganizationRole::Owner);
    }

    private function tokenFromSession(TestResponse $response): string
    {
        $response->assertSessionHas('portal_invitation_url');

        $url = (string) session('portal_invitation_url');
        $this->assertNotSame('', $url, 'Invitation URL was not flashed to the session.');

        $path = (string) parse_url($url, PHP_URL_PATH);
        $token = basename($path);

        $this->assertNotSame('', $token, 'Invitation token could not be read from the session URL.');

        return $token;
    }

    private function inviteMember(
        Organization $organization,
        User $owner,
        Vendor $vendor,
        Event $event,
        EventVendorEngagement $engagement,
        string $email,
    ): string {
        $response = $this->actingAs($owner)->post(
            route('workspace.events.vendors.portal.invite', [$organization, $event, $engagement]),
            ['email' => $email],
        );

        $response->assertRedirect(route('workspace.events.vendors.portal.index', [
            $organization,
            $event,
            $engagement,
        ]));

        $this->assertDatabaseHas('vendor_portal_memberships', [
            'vendor_id' => $vendor->id,
            'invited_email' => $email,
        ]);

        return $this->tokenFromSession($response);
    }

    public function test_owner_invites_a_vendor_contact_and_the_matching_user_accepts(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $vendor = $this->vendorIn($organization);
        $engagement = $this->engagementFor($event, $vendor);
        $owner = $this->ownerOf($organization);

        $token = $this->inviteMember($organization, $owner, $vendor, $event, $engagement, 'invitee@example.test');

        $membership = VendorPortalMembership::query()
            ->where('vendor_id', $vendor->id)
            ->firstOrFail();

        $this->assertSame(VendorPortalStatus::Invited, $membership->status);
        $this->assertNull($membership->user_id);
        $this->assertNotNull($membership->invitation_token_hash);
        $this->assertNotSame($token, $membership->invitation_token_hash);
        $this->assertSame(hash('sha256', $token), $membership->invitation_token_hash);
        $this->assertTrue($membership->invitation_expires_at->isFuture());

        $invitee = $this->portalUser('invitee@example.test');

        $this->actingAs($invitee)
            ->post(route('vendor-portal.invitations.accept', ['token' => $token]))
            ->assertRedirect(route('vendor-portal.index'));

        $membership->refresh();

        $this->assertSame(VendorPortalStatus::Active, $membership->status);
        $this->assertSame($invitee->id, $membership->user_id);
        $this->assertNull($membership->invitation_token_hash);
        $this->assertNotNull($membership->accepted_at);
    }

    public function test_invitation_is_rejected_for_a_different_email(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $vendor = $this->vendorIn($organization);
        $engagement = $this->engagementFor($event, $vendor);
        $owner = $this->ownerOf($organization);

        $token = $this->inviteMember($organization, $owner, $vendor, $event, $engagement, 'invitee@example.test');

        $outsider = $this->portalUser('someone-else@example.test');

        $this->actingAs($outsider)
            ->post(route('vendor-portal.invitations.accept', ['token' => $token]))
            ->assertStatus(403);

        $membership = VendorPortalMembership::query()
            ->where('vendor_id', $vendor->id)
            ->firstOrFail();

        $this->assertSame(VendorPortalStatus::Invited, $membership->status);
        $this->assertNull($membership->user_id);
    }

    public function test_expired_invitation_cannot_be_accepted_and_is_marked_expired(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $vendor = $this->vendorIn($organization);
        $engagement = $this->engagementFor($event, $vendor);
        $owner = $this->ownerOf($organization);

        $token = $this->inviteMember($organization, $owner, $vendor, $event, $engagement, 'invitee@example.test');

        $membership = VendorPortalMembership::query()
            ->where('vendor_id', $vendor->id)
            ->firstOrFail();

        $membership->forceFill(['invitation_expires_at' => now()->subHour()])->save();

        $invitee = $this->portalUser('invitee@example.test');

        $this->actingAs($invitee)
            ->post(route('vendor-portal.invitations.accept', ['token' => $token]))
            ->assertStatus(410);

        $membership->refresh();

        $this->assertSame(VendorPortalStatus::Expired, $membership->status);
        $this->assertNull($membership->invitation_token_hash);
    }

    public function test_revoked_membership_loses_portal_access_and_engagement_listing(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $vendor = $this->vendorIn($organization);
        $engagement = $this->engagementFor($event, $vendor);
        $owner = $this->ownerOf($organization);
        $portalUser = $this->portalUser();

        $membership = $this->portalMemberFor($vendor, $portalUser);
        $this->grantPortalAccess($membership, $engagement);

        $this->actingAs($portalUser)
            ->get(route('vendor-portal.engagements.show', $engagement))
            ->assertOk();

        $this->actingAs($owner)
            ->post(route('workspace.events.vendors.portal.revoke', [$organization, $event, $membership]))
            ->assertRedirect();

        $membership->refresh();
        $this->assertSame(VendorPortalStatus::Revoked, $membership->status);
        $this->assertNotNull($membership->revoked_at);

        $this->actingAs($portalUser)
            ->get(route('vendor-portal.index'))
            ->assertOk()
            ->assertDontSee($engagement->title);

        $this->actingAs($portalUser)
            ->get(route('vendor-portal.engagements.show', $engagement))
            ->assertStatus(404);
    }

    public function test_portal_access_is_scoped_to_granted_engagements(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $secondEvent = $this->eventIn($organization);
        $vendorA = $this->vendorIn($organization);
        $vendorB = $this->vendorIn($organization);

        $granted = $this->engagementFor($event, $vendorA);
        $otherEventEngagement = $this->engagementFor($secondEvent, $vendorA);
        $withoutGrant = $this->engagementFor($event, $vendorA);
        $otherVendorEngagement = $this->engagementFor($event, $vendorB);

        $portalUser = $this->portalUser();
        $membership = $this->portalMemberFor($vendorA, $portalUser);
        $this->grantPortalAccess($membership, $granted);

        $this->actingAs($portalUser)
            ->get(route('vendor-portal.engagements.show', $granted))
            ->assertOk();

        $this->actingAs($portalUser)
            ->get(route('vendor-portal.engagements.show', $otherVendorEngagement))
            ->assertStatus(404);

        $this->actingAs($portalUser)
            ->get(route('vendor-portal.engagements.show', $otherEventEngagement))
            ->assertStatus(404);

        $this->actingAs($portalUser)
            ->get(route('vendor-portal.engagements.show', $withoutGrant))
            ->assertStatus(404);
    }

    public function test_revoking_access_removes_authorization_immediately(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $vendor = $this->vendorIn($organization);
        $engagement = $this->engagementFor($event, $vendor);
        $owner = $this->ownerOf($organization);
        $portalUser = $this->portalUser();

        $membership = $this->portalMemberFor($vendor, $portalUser);
        $this->grantPortalAccess($membership, $engagement);

        $this->actingAs($portalUser)
            ->get(route('vendor-portal.engagements.show', $engagement))
            ->assertOk();

        $this->actingAs($owner)
            ->post(route('workspace.events.vendors.portal.revoke', [$organization, $event, $membership]))
            ->assertRedirect();

        $this->actingAs($portalUser)
            ->get(route('vendor-portal.engagements.show', $engagement))
            ->assertStatus(404);
    }

    public function test_portal_show_hides_internal_only_communications(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $vendor = $this->vendorIn($organization);
        $engagement = $this->engagementFor($event, $vendor);
        $portalUser = $this->portalUser();

        $membership = $this->portalMemberFor($vendor, $portalUser);
        $this->grantPortalAccess($membership, $engagement);

        EventVendorCommunication::create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_vendor_engagement_id' => $engagement->id,
            'direction' => VendorCommunicationDirection::Outbound->value,
            'visibility' => VendorCommunicationVisibility::VendorVisible->value,
            'type' => VendorCommunicationType::Clarification->value,
            'body' => 'VENDOR-VISIBLE-BODY-ALPHA',
        ]);

        EventVendorCommunication::create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_vendor_engagement_id' => $engagement->id,
            'direction' => VendorCommunicationDirection::Internal->value,
            'visibility' => VendorCommunicationVisibility::InternalOnly->value,
            'type' => VendorCommunicationType::Note->value,
            'body' => 'INTERNAL-ONLY-BODY-SECRET',
        ]);

        $this->actingAs($portalUser)
            ->get(route('vendor-portal.engagements.show', $engagement))
            ->assertOk()
            ->assertSee('VENDOR-VISIBLE-BODY-ALPHA')
            ->assertDontSee('INTERNAL-ONLY-BODY-SECRET');
    }

    public function test_portal_document_download_enforces_visibility_and_scope(): void
    {
        Storage::fake('local');

        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $vendor = $this->vendorIn($organization);
        $engagement = $this->engagementFor($event, $vendor);
        $otherEngagement = $this->engagementFor($event, $vendor);
        $portalUser = $this->portalUser();

        $membership = $this->portalMemberFor($vendor, $portalUser);
        $this->grantPortalAccess($membership, $engagement);

        $visible = $this->documentFor($engagement, [
            'visibility' => VendorDocumentVisibility::VendorVisible->value,
        ]);
        $internal = $this->documentFor($engagement, [
            'visibility' => VendorDocumentVisibility::InternalOnly->value,
        ]);
        $foreign = $this->documentFor($otherEngagement, [
            'visibility' => VendorDocumentVisibility::VendorVisible->value,
        ]);

        foreach ([$visible, $internal, $foreign] as $document) {
            Storage::disk('local')->put($document->path, 'PDF-CONTENT');
        }

        $response = $this->actingAs($portalUser)
            ->get(route('vendor-portal.documents.download', $visible));

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->actingAs($portalUser)
            ->get(route('vendor-portal.documents.download', $internal))
            ->assertStatus(404);

        $this->actingAs($portalUser)
            ->get(route('vendor-portal.documents.download', $foreign))
            ->assertStatus(404);
    }

    public function test_portal_deliverable_submission_creates_record_and_updates_status(): void
    {
        Storage::fake('local');

        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $vendor = $this->vendorIn($organization);
        $engagement = $this->engagementFor($event, $vendor);
        $portalUser = $this->portalUser();

        $membership = $this->portalMemberFor($vendor, $portalUser);
        $this->grantPortalAccess($membership, $engagement);

        $deliverable = $this->deliverableFor($engagement, [
            'status' => VendorDeliverableStatus::NotStarted->value,
        ]);

        $this->actingAs($portalUser)
            ->post(
                route('vendor-portal.deliverables.submit', [$engagement, $deliverable]),
                [
                    'notes' => 'Bukti penyerahan dari vendor.',
                    'file' => UploadedFile::fake()->create('deliverable.pdf', 100, 'application/pdf'),
                ],
            )
            ->assertRedirect();

        $this->assertDatabaseHas('event_vendor_deliverable_submissions', [
            'event_vendor_deliverable_id' => $deliverable->id,
            'submitted_by_user_id' => $portalUser->id,
            'status' => 'submitted',
        ]);

        $submission = $deliverable->submissions()->firstOrFail();
        $this->assertNotNull($submission->event_vendor_document_id);

        $this->assertSame(VendorDeliverableStatus::Submitted, $deliverable->fresh()->status);
    }

    public function test_portal_rejects_submission_for_a_deliverable_from_another_engagement(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $vendor = $this->vendorIn($organization);
        $engagement = $this->engagementFor($event, $vendor);
        $otherEngagement = $this->engagementFor($event, $vendor);
        $portalUser = $this->portalUser();

        $membership = $this->portalMemberFor($vendor, $portalUser);
        $this->grantPortalAccess($membership, $engagement);

        $foreignDeliverable = $this->deliverableFor($otherEngagement, [
            'status' => VendorDeliverableStatus::NotStarted->value,
        ]);

        $this->actingAs($portalUser)
            ->post(
                route('vendor-portal.deliverables.submit', [$engagement, $foreignDeliverable]),
                ['notes' => 'Mencoba deliverable engagement lain.'],
            )
            ->assertStatus(404);

        $this->assertDatabaseCount('event_vendor_deliverable_submissions', 0);
        $this->assertSame(VendorDeliverableStatus::NotStarted, $foreignDeliverable->fresh()->status);
    }

    public function test_portal_change_request_is_server_controlled(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $vendor = $this->vendorIn($organization);
        $engagement = $this->engagementFor($event, $vendor);
        $portalUser = $this->portalUser();

        $membership = $this->portalMemberFor($vendor, $portalUser);
        $this->grantPortalAccess($membership, $engagement);

        $this->actingAs($portalUser)
            ->post(
                route('vendor-portal.change-requests.store', $engagement),
                [
                    'title' => 'Tambah area dekorasi',
                    'description' => 'Vendor mengajukan penambahan area dekorasi.',
                    'impact' => 'Waktu pemasangan bertambah satu jam.',
                    'proposed_changes' => [
                        'amount' => 2500000,
                        'scope' => 'Area tambahan',
                    ],
                    // Internal-only fields must never be accepted from the portal.
                    'status' => 'approved',
                    'decision_notes' => 'INTERNAL-EVALUATION-NOTE',
                    'decided_by' => $portalUser->id,
                ],
            )
            ->assertRedirect();

        $changeRequest = $engagement->changeRequests()->firstOrFail();

        $this->assertSame(VendorChangeRequestStatus::Submitted, $changeRequest->status);
        $this->assertSame($portalUser->id, $changeRequest->requested_by_user_id);
        $this->assertNull($changeRequest->decision_notes);
        $this->assertNull($changeRequest->decided_by);
        $this->assertNotNull($changeRequest->submitted_at);
    }

    public function test_vendor_can_confirm_an_expected_schedule_reference(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $vendor = $this->vendorIn($organization);
        $engagement = $this->engagementFor($event, $vendor);
        $portalUser = $this->portalUser();

        $membership = $this->portalMemberFor($vendor, $portalUser);
        $this->grantPortalAccess($membership, $engagement);

        $reference = EventVendorScheduleReference::create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_vendor_engagement_id' => $engagement->id,
            'arrival_status' => VendorArrivalStatus::Expected->value,
            'expected_arrival_at' => now()->addDay(),
        ]);

        $this->actingAs($portalUser)
            ->post(route('vendor-portal.schedule.confirm', $reference))
            ->assertRedirect();

        $this->assertSame(VendorArrivalStatus::Confirmed, $reference->fresh()->arrival_status);
    }

    public function test_vendor_cannot_confirm_a_schedule_reference_twice(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $vendor = $this->vendorIn($organization);
        $engagement = $this->engagementFor($event, $vendor);
        $portalUser = $this->portalUser();

        $membership = $this->portalMemberFor($vendor, $portalUser);
        $this->grantPortalAccess($membership, $engagement);

        $reference = EventVendorScheduleReference::create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_vendor_engagement_id' => $engagement->id,
            'arrival_status' => VendorArrivalStatus::Confirmed->value,
            'expected_arrival_at' => now()->addDay(),
        ]);

        $this->actingAs($portalUser)
            ->post(route('vendor-portal.schedule.confirm', $reference))
            ->assertSessionHasErrors('schedule');

        $this->assertSame(VendorArrivalStatus::Confirmed, $reference->fresh()->arrival_status);
    }

    public function test_vendor_can_acknowledge_a_requested_logistics_requirement(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $vendor = $this->vendorIn($organization);
        $engagement = $this->engagementFor($event, $vendor);
        $portalUser = $this->portalUser();

        $membership = $this->portalMemberFor($vendor, $portalUser);
        $this->grantPortalAccess($membership, $engagement);

        $requirement = EventVendorLogisticsRequirement::create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_vendor_engagement_id' => $engagement->id,
            'type' => VendorLogisticsType::Electricity->value,
            'description' => 'Daya listrik 5000 watt.',
            'responsibility' => VendorLogisticsResponsibility::Vendor->value,
            'status' => VendorLogisticsStatus::Requested->value,
        ]);

        $this->actingAs($portalUser)
            ->post(route('vendor-portal.logistics.confirm', $requirement))
            ->assertRedirect();

        $this->assertSame(VendorLogisticsStatus::Acknowledged, $requirement->fresh()->status);
    }
}
