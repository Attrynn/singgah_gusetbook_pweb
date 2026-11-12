<?php

namespace Tests\Feature\M7;

use App\Enums\EventStaffRole;
use App\Enums\OrganizationRole;
use App\Enums\VendorChangeRequestStatus;
use App\Enums\VendorDeliverableStatus;
use App\Enums\VendorEngagementStatus;
use App\Enums\VendorIssueSeverity;
use App\Enums\VendorIssueStatus;
use App\Models\EventVendorChangeRequest;
use App\Models\EventVendorIssue;
use App\Models\Organization;
use App\Models\User;
use App\Services\VendorEngagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\M7\Concerns\BuildsVendorFixtures;
use Tests\TestCase;

class VendorEngagementLifecycleTest extends TestCase
{
    use BuildsVendorFixtures, RefreshDatabase;

    private function owner(Organization $organization): User
    {
        return $this->memberOf($organization, OrganizationRole::Owner);
    }

    private function readyEngagement(Organization $organization, ?User $owner = null)
    {
        $event = $this->eventIn($organization);
        $vendor = $this->vendorIn($organization);
        $coordinator = $this->staffOf($organization, $event, EventStaffRole::OperationsCoordinator);

        $engagement = $this->engagementFor($event, $vendor, [
            'status' => VendorEngagementStatus::PendingApproval->value,
            'scope_snapshot' => ['description' => 'Dekorasi akad dan resepsi'],
            'coordinator_membership_id' => $coordinator->id,
        ]);

        return [$event, $vendor, $engagement, $owner ?? $this->owner($organization)];
    }

    public function test_confirmation_is_blocked_without_scope_and_coordinator(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $vendor = $this->vendorIn($org);
        $owner = $this->owner($org);

        $engagement = $this->engagementFor($event, $vendor, [
            'status' => VendorEngagementStatus::PendingApproval->value,
            'agreed_amount' => 9000000,
            'scope_snapshot' => null,
            'coordinator_membership_id' => null,
        ]);

        $this->expectException(ValidationException::class);

        app(VendorEngagementService::class)->transition(
            $event,
            $engagement,
            VendorEngagementStatus::Confirmed,
            $owner,
        );
    }

    public function test_confirmation_succeeds_and_records_first_revision(): void
    {
        $org = $this->organization();
        [$event, , $engagement, $owner] = $this->readyEngagement($org);

        $response = $this->actingAs($owner)->post(
            route('workspace.events.vendors.engagements.status', [$org, $event, $engagement]),
            ['status' => VendorEngagementStatus::Confirmed->value],
        );

        $response->assertRedirect();

        $engagement->refresh();

        $this->assertSame(VendorEngagementStatus::Confirmed, $engagement->status);
        $this->assertNotNull($engagement->confirmed_at);
        $this->assertSame($owner->id, $engagement->confirmed_by);
        $this->assertSame(1, $engagement->revisions()->count());
        $this->assertSame(1, (int) $engagement->revisions()->first()->revision_number);
    }

    public function test_commercial_terms_are_locked_after_confirmation_but_notes_stay_editable(): void
    {
        $org = $this->organization();
        [$event, , $engagement, $owner] = $this->readyEngagement($org);

        app(VendorEngagementService::class)->transition(
            $event,
            $engagement,
            VendorEngagementStatus::Confirmed,
            $owner,
        );

        $this->actingAs($owner)
            ->put(route('workspace.events.vendors.engagements.update', [$org, $event, $engagement]), [
                'agreed_amount' => 12000000,
            ])
            ->assertSessionHasErrors('agreed_amount');

        $this->assertSame('10000000.00', $engagement->refresh()->agreed_amount);

        $this->actingAs($owner)
            ->put(route('workspace.events.vendors.engagements.update', [$org, $event, $engagement]), [
                'notes' => 'Catatan koordinasi internal.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Catatan koordinasi internal.', $engagement->refresh()->notes);
    }

    public function test_cancelling_requires_a_reason_and_keeps_deliverables(): void
    {
        $org = $this->organization();
        [$event, , $engagement, $owner] = $this->readyEngagement($org);

        $deliverable = $this->deliverableFor($engagement, ['title' => 'Bunga meja']);

        $this->actingAs($owner)
            ->post(route('workspace.events.vendors.engagements.cancel', [$org, $event, $engagement]), [])
            ->assertSessionHasErrors('reason');

        $this->actingAs($owner)
            ->post(route('workspace.events.vendors.engagements.cancel', [$org, $event, $engagement]), [
                'reason' => 'Vendor membatalkan sepihak.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $engagement->refresh();

        $this->assertSame(VendorEngagementStatus::Cancelled, $engagement->status);
        $this->assertSame('Vendor membatalkan sepihak.', $engagement->cancel_reason);
        $this->assertDatabaseHas('event_vendor_deliverables', ['id' => $deliverable->id]);
    }

    public function test_closure_is_blocked_by_open_issues_pending_changes_and_unreviewed_deliverables(): void
    {
        $org = $this->organization();
        [$event, $vendor, $engagement, $owner] = $this->readyEngagement($org);

        $engagement->forceFill([
            'status' => VendorEngagementStatus::Confirmed->value,
            'confirmed_at' => now(),
        ])->save();

        EventVendorIssue::create([
            'organization_id' => $org->id,
            'event_id' => $event->id,
            'event_vendor_engagement_id' => $engagement->id,
            'title' => 'Katering terlambat',
            'severity' => VendorIssueSeverity::High->value,
            'status' => VendorIssueStatus::Open->value,
            'reported_by' => $owner->id,
        ]);

        $changeRequest = EventVendorChangeRequest::create([
            'organization_id' => $org->id,
            'event_id' => $event->id,
            'event_vendor_engagement_id' => $engagement->id,
            'title' => 'Tambah porsi',
            'description' => 'Menambah 20 porsi.',
            'status' => VendorChangeRequestStatus::Submitted->value,
            'submitted_at' => now(),
        ]);

        $deliverable = $this->deliverableFor($engagement, [
            'status' => VendorDeliverableStatus::Submitted->value,
        ]);

        $this->actingAs($owner)
            ->post(route('workspace.events.vendors.engagements.close', [$org, $event, $engagement]), [
                'closure_notes' => 'Siap ditutup.',
            ])
            ->assertSessionHasErrors('closure');

        $this->assertNull($engagement->refresh()->closed_at);

        EventVendorIssue::query()->where('event_vendor_engagement_id', $engagement->id)
            ->update(['status' => VendorIssueStatus::Resolved->value, 'resolved_at' => now()]);
        $changeRequest->forceFill([
            'status' => VendorChangeRequestStatus::Rejected->value,
            'decided_at' => now(),
            'decision_notes' => 'Anggaran tidak tersedia.',
        ])->save();
        $deliverable->forceFill([
            'status' => VendorDeliverableStatus::Accepted->value,
            'accepted_by' => $owner->id,
            'accepted_at' => now(),
        ])->save();

        $this->actingAs($owner)
            ->post(route('workspace.events.vendors.engagements.close', [$org, $event, $engagement]), [
                'closure_notes' => 'Semua kewajiban selesai.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $engagement->refresh();

        $this->assertNotNull($engagement->closed_at);
        $this->assertSame($owner->id, $engagement->closed_by);
        $this->assertSame('Semua kewajiban selesai.', $engagement->closure_notes);
        $this->assertIsArray($engagement->closure_snapshot);
        $this->assertSame(VendorEngagementStatus::Completed->value, $engagement->closure_snapshot['final_status']);
        $this->assertSame($vendor->id, (int) $engagement->vendor_id);
    }

    public function test_cross_event_engagement_returns_not_found(): void
    {
        $org = $this->organization();
        $eventA = $this->eventIn($org);
        $eventB = $this->eventIn($org);
        $vendor = $this->vendorIn($org);
        $owner = $this->owner($org);

        $engagement = $this->engagementFor($eventB, $vendor);

        $this->actingAs($owner)
            ->get(route('workspace.events.vendors.engagements.show', [$org, $eventA, $engagement]))
            ->assertNotFound();
    }

    public function test_staff_without_engagement_management_permission_is_forbidden(): void
    {
        $org = $this->organization();
        [$event, $vendor] = $this->readyEngagement($org);
        $generalStaff = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);

        $this->actingAs($generalStaff->user)
            ->post(route('workspace.events.vendors.engagements.store', [$org, $event]), [
                'vendor_id' => $vendor->id,
                'title' => 'Engagement tidak sah',
            ])
            ->assertForbidden();
    }
}
