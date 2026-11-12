<?php

namespace Tests\Feature\M7;

use App\Enums\EventStaffRole;
use App\Enums\OrganizationRole;
use App\Enums\VendorChangeRequestStatus;
use App\Enums\VendorCommunicationDirection;
use App\Enums\VendorCommunicationType;
use App\Enums\VendorCommunicationVisibility;
use App\Enums\VendorIssueSeverity;
use App\Models\EventIncident;
use App\Models\Organization;
use App\Models\User;
use App\Services\VendorCoordinationService;
use App\Services\VendorEngagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\M7\Concerns\BuildsVendorFixtures;
use Tests\TestCase;

class VendorCoordinationTest extends TestCase
{
    use BuildsVendorFixtures, RefreshDatabase;

    private VendorCoordinationService $coordination;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coordination = app(VendorCoordinationService::class);
    }

    private function owner(Organization $organization): User
    {
        return $this->memberOf($organization, OrganizationRole::Owner);
    }

    public function test_internal_only_communication_is_stored_as_internal(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $vendor = $this->vendorIn($org);
        $engagement = $this->engagementFor($event, $vendor);

        $communication = $this->coordination->addCommunication($engagement, [
            'visibility' => VendorCommunicationVisibility::InternalOnly->value,
            'type' => VendorCommunicationType::Note->value,
            'body' => 'Catatan internal untuk tim.',
        ], $owner);

        $this->assertSame(VendorCommunicationVisibility::InternalOnly, $communication->visibility);
        $this->assertSame(VendorCommunicationDirection::Internal, $communication->direction);
        $this->assertSame($owner->id, $communication->author_user_id);
        $this->assertSame($engagement->id, $communication->event_vendor_engagement_id);
    }

    public function test_issue_can_link_an_existing_incident_without_creating_a_new_one(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $vendor = $this->vendorIn($org);
        $engagement = $this->engagementFor($event, $vendor);

        $issue = $this->coordination->storeIssue($engagement, [
            'title' => 'Vendor terlambat',
            'severity' => VendorIssueSeverity::High->value,
        ], $owner);

        $incident = EventIncident::create([
            'event_id' => $event->id,
            'title' => 'Insiden keterlambatan vendor',
            'reported_at' => now(),
        ]);

        $incidentCount = EventIncident::query()->count();

        $linked = $this->coordination->linkIncident($issue, $incident, $owner);

        $this->assertSame($incident->id, $linked->event_incident_id);
        $this->assertSame($incidentCount, EventIncident::query()->count());
    }

    public function test_approved_change_request_updates_amount_and_preserves_prior_revision(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $vendor = $this->vendorIn($org);
        $engagement = $this->confirmedEngagementFor($event, $vendor, [
            'agreed_amount' => 10000000,
            'currency' => 'IDR',
            'scope_snapshot' => ['description' => 'Lingkup awal'],
        ]);

        app(VendorEngagementService::class)->snapshotRevision($engagement, null, $owner, 'Konfirmasi awal');

        $changeRequest = $this->coordination->createChangeRequest($engagement, [
            'title' => 'Penambahan dekorasi',
            'description' => 'Menambah dekorasi panggung.',
            'proposed_changes' => ['amount' => 12500000],
        ], $owner);

        $this->coordination->submitChangeRequest($changeRequest, $owner);

        $decided = $this->coordination->decideChangeRequest(
            $changeRequest->refresh(),
            VendorChangeRequestStatus::Approved,
            $owner,
            'Disetujui.',
        );

        $this->assertSame(VendorChangeRequestStatus::Approved, $decided->status);

        $engagement->refresh();
        $this->assertSame('12500000.00', (string) $engagement->agreed_amount);

        $revisions = $engagement->revisions()->orderBy('revision_number')->get();

        $this->assertCount(2, $revisions);
        $this->assertSame('10000000.00', (string) $revisions[0]->snapshot['agreed_amount']);
        $this->assertSame('12500000.00', (string) $revisions[1]->snapshot['agreed_amount']);
        $this->assertSame($changeRequest->id, $revisions[1]->event_vendor_change_request_id);
    }

    public function test_rejecting_a_change_request_requires_notes(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $vendor = $this->vendorIn($org);
        $engagement = $this->engagementFor($event, $vendor);

        $changeRequest = $this->coordination->createChangeRequest($engagement, [
            'title' => 'Perubahan jadwal',
            'description' => 'Menggeser waktu pemasangan.',
            'proposed_changes' => ['schedule' => 'Pukul 06.00'],
            'status' => VendorChangeRequestStatus::Submitted->value,
        ], $owner);

        $this->expectException(ValidationException::class);

        $this->coordination->decideChangeRequest(
            $changeRequest,
            VendorChangeRequestStatus::Rejected,
            $owner,
            null,
        );
    }

    public function test_submitted_change_request_can_be_withdrawn(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $vendor = $this->vendorIn($org);
        $engagement = $this->engagementFor($event, $vendor);

        $changeRequest = $this->coordination->createChangeRequest($engagement, [
            'title' => 'Perubahan lingkup',
            'description' => 'Menambah area layanan.',
            'status' => VendorChangeRequestStatus::Submitted->value,
        ], $owner);

        $withdrawn = $this->coordination->withdrawChangeRequest($changeRequest, $owner);

        $this->assertSame(VendorChangeRequestStatus::Withdrawn, $withdrawn->status);
        $this->assertNotNull($withdrawn->withdrawn_at);
    }

    public function test_commercial_change_approval_requires_commercial_approval_permission(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $vendor = $this->vendorIn($org);
        $engagement = $this->engagementFor($event, $vendor);
        $coordinator = $this->staffOf($org, $event, EventStaffRole::OperationsCoordinator);

        $changeRequest = $this->coordination->createChangeRequest($engagement, [
            'title' => 'Kenaikan nilai',
            'description' => 'Penyesuaian nilai kesepakatan.',
            'proposed_changes' => ['amount' => 11000000],
            'status' => VendorChangeRequestStatus::Submitted->value,
        ], $owner);

        $this->actingAs($coordinator->user)
            ->post(route('workspace.events.vendors.change-requests.decision', [$org, $event, $changeRequest]), [
                'status' => VendorChangeRequestStatus::Approved->value,
                'decision_notes' => 'Disetujui.',
            ])
            ->assertForbidden();
    }

    public function test_performance_review_requires_permission(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $vendor = $this->vendorIn($org);
        $engagement = $this->confirmedEngagementFor($event, $vendor);
        $general = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);

        $this->actingAs($general->user)
            ->post(route('workspace.events.vendors.performance.store', [$org, $event, $engagement]), [
                'overall_rating' => 4,
            ])
            ->assertForbidden();
    }

    public function test_performance_review_rating_is_bounded(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $vendor = $this->vendorIn($org);
        $engagement = $this->confirmedEngagementFor($event, $vendor);

        $this->actingAs($owner)
            ->post(route('workspace.events.vendors.performance.store', [$org, $event, $engagement]), [
                'overall_rating' => 6,
            ])
            ->assertSessionHasErrors('overall_rating');
    }
}
