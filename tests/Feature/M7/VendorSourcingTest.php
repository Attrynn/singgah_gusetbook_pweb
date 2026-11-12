<?php

namespace Tests\Feature\M7;

use App\Enums\EventStaffRole;
use App\Enums\OrganizationRole;
use App\Enums\VendorEngagementStatus;
use App\Enums\VendorProposalStatus;
use App\Enums\VendorRequirementStatus;
use App\Models\EventVendorEngagement;
use App\Models\EventVendorProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\M7\Concerns\BuildsVendorFixtures;
use Tests\TestCase;

class VendorSourcingTest extends TestCase
{
    use BuildsVendorFixtures, RefreshDatabase;

    public function test_owner_can_create_a_vendor_requirement(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->memberOf($org, OrganizationRole::Owner);

        $this->actingAs($owner)
            ->post(route('workspace.events.vendors.requirements.store', [$org, $event]), [
                'title' => 'Dekorasi aula utama',
                'quantity' => 2,
                'needed_by' => now()->addMonth()->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('event_vendor_requirements', [
            'organization_id' => $org->id,
            'event_id' => $event->id,
            'title' => 'Dekorasi aula utama',
            'status' => VendorRequirementStatus::Open->value,
        ]);
    }

    public function test_staff_without_requirement_permission_cannot_create_a_requirement(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $staff = $this->staffOf($org, $event, EventStaffRole::GuestRelations);

        $this->actingAs($staff->user)
            ->post(route('workspace.events.vendors.requirements.store', [$org, $event]), [
                'title' => 'Dekorasi aula utama',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('event_vendor_requirements', ['event_id' => $event->id]);
    }

    public function test_shortlist_candidate_is_added_and_duplicates_are_rejected(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->memberOf($org, OrganizationRole::Owner);
        $requirement = $this->requirementIn($event);
        $vendor = $this->vendorIn($org);
        $url = route('workspace.events.vendors.requirements.candidates.store', [$org, $event, $requirement]);

        $this->actingAs($owner)
            ->post($url, ['vendor_id' => $vendor->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('event_vendor_requirement_candidates', [
            'event_vendor_requirement_id' => $requirement->id,
            'vendor_id' => $vendor->id,
        ]);
        $this->assertSame(VendorRequirementStatus::Sourcing, $requirement->fresh()->status);

        $this->actingAs($owner)
            ->post($url, ['vendor_id' => $vendor->id])
            ->assertSessionHasErrors('vendor_id');

        $this->assertSame(1, $requirement->candidates()->count());
    }

    public function test_preferring_a_candidate_clears_the_previous_preferred_candidate(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->memberOf($org, OrganizationRole::Owner);
        $requirement = $this->requirementIn($event);
        $firstVendor = $this->vendorIn($org);
        $secondVendor = $this->vendorIn($org);

        $this->actingAs($owner)->post(
            route('workspace.events.vendors.requirements.candidates.store', [$org, $event, $requirement]),
            ['vendor_id' => $firstVendor->id],
        )->assertSessionHasNoErrors();

        $this->actingAs($owner)->post(
            route('workspace.events.vendors.requirements.candidates.store', [$org, $event, $requirement]),
            ['vendor_id' => $secondVendor->id],
        )->assertSessionHasNoErrors();

        $first = $requirement->candidates()->where('vendor_id', $firstVendor->id)->firstOrFail();
        $second = $requirement->candidates()->where('vendor_id', $secondVendor->id)->firstOrFail();

        $this->actingAs($owner)
            ->post(route('workspace.events.vendors.requirements.candidates.preferred', [$org, $event, $requirement, $first]))
            ->assertRedirect();

        $this->assertTrue($first->fresh()->is_preferred);

        $this->actingAs($owner)
            ->post(route('workspace.events.vendors.requirements.candidates.preferred', [$org, $event, $requirement, $second]))
            ->assertRedirect();

        $this->assertFalse($first->fresh()->is_preferred);
        $this->assertTrue($second->fresh()->is_preferred);
    }

    public function test_creating_a_proposal_moves_the_requirement_to_proposals_received(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->memberOf($org, OrganizationRole::Owner);
        $requirement = $this->requirementIn($event);
        $vendor = $this->vendorIn($org);

        $this->actingAs($owner)
            ->post(route('workspace.events.vendors.proposals.store', [$org, $event]), [
                'event_vendor_requirement_id' => $requirement->id,
                'vendor_id' => $vendor->id,
                'title' => 'Proposal dekorasi',
                'quoted_amount' => 15000000,
                'status' => VendorProposalStatus::Submitted->value,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $proposal = EventVendorProposal::query()
            ->where('event_vendor_requirement_id', $requirement->id)
            ->firstOrFail();

        $this->assertSame(VendorProposalStatus::Submitted, $proposal->status);
        $this->assertSame(VendorRequirementStatus::ProposalsReceived, $requirement->fresh()->status);
    }

    public function test_an_expired_proposal_cannot_be_selected(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->memberOf($org, OrganizationRole::Owner);
        $requirement = $this->requirementIn($event);
        $vendor = $this->vendorIn($org);
        $proposal = $this->proposalFor($requirement, $vendor, [
            'status' => VendorProposalStatus::Submitted->value,
            'valid_until' => now()->subDay()->toDateString(),
        ]);
        $statusBefore = $requirement->fresh()->status;

        $this->actingAs($owner)
            ->post(route('workspace.events.vendors.proposals.select', [$org, $event, $proposal]), [
                'selection_reason' => 'Harga terbaik',
            ])
            ->assertSessionHasErrors('proposal');

        $this->assertSame($statusBefore, $requirement->fresh()->status);
        $this->assertSame(0, $event->vendorEngagements()->count());
    }

    public function test_selecting_a_proposal_rejects_the_other_submitted_proposals(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->memberOf($org, OrganizationRole::Owner);
        $requirement = $this->requirementIn($event);
        $chosen = $this->proposalFor($requirement, $this->vendorIn($org), [
            'title' => 'Proposal A',
            'status' => VendorProposalStatus::Submitted->value,
        ]);
        $other = $this->proposalFor($requirement, $this->vendorIn($org), [
            'title' => 'Proposal B',
            'status' => VendorProposalStatus::Submitted->value,
        ]);

        $this->actingAs($owner)
            ->post(route('workspace.events.vendors.proposals.select', [$org, $event, $chosen]), [
                'selection_reason' => 'Sesuai anggaran dan kualitas',
            ])
            ->assertRedirect();

        $this->assertSame(VendorProposalStatus::Selected, $chosen->fresh()->status);
        $this->assertSame(VendorProposalStatus::Rejected, $other->fresh()->status);
        $this->assertSame(2, $requirement->proposals()->count());
        $this->assertModelExists($other);
        $this->assertSame(VendorRequirementStatus::VendorSelected, $requirement->fresh()->status);
    }

    public function test_selection_creates_a_draft_engagement_with_amount_and_snapshots(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->memberOf($org, OrganizationRole::Owner);
        $requirement = $this->requirementIn($event);
        $vendor = $this->vendorIn($org);
        $service = $this->serviceFor($vendor, ['name' => 'Dekorasi Premium']);
        $package = $this->packageFor($service, ['name' => 'Paket Gold', 'price_amount' => 20000000]);
        $proposal = $this->proposalFor($requirement, $vendor, [
            'title' => 'Dekorasi aula',
            'vendor_service_package_id' => $package->id,
            'service_description' => 'Dekorasi panggung utama',
            'quoted_amount' => 18000000,
            'inclusions' => ['Bunga segar'],
            'exclusions' => ['Transport'],
            'status' => VendorProposalStatus::Submitted->value,
        ]);

        $this->actingAs($owner)
            ->post(route('workspace.events.vendors.proposals.select', [$org, $event, $proposal]), [
                'selection_reason' => 'Sesuai anggaran',
            ])
            ->assertRedirect();

        $engagement = EventVendorEngagement::query()
            ->where('event_vendor_proposal_id', $proposal->id)
            ->firstOrFail();

        $this->assertSame(VendorEngagementStatus::Draft, $engagement->status);
        $this->assertSame($proposal->fresh()->quoted_amount, $engagement->agreed_amount);
        $this->assertSame($package->id, $engagement->package_snapshot['id']);
        $this->assertSame('Paket Gold', $engagement->package_snapshot['name']);
        $this->assertSame('Dekorasi panggung utama', $engagement->scope_snapshot['description']);
        $this->assertSame(['Bunga segar'], $engagement->scope_snapshot['inclusions']);
    }

    public function test_a_vendor_from_another_organization_is_rejected(): void
    {
        $org = $this->organization('one');
        $other = $this->organization('two');
        $event = $this->eventIn($org);
        $owner = $this->memberOf($org, OrganizationRole::Owner);
        $requirement = $this->requirementIn($event);
        $foreignVendor = $this->vendorIn($other);

        $this->actingAs($owner)
            ->post(route('workspace.events.vendors.requirements.candidates.store', [$org, $event, $requirement]), [
                'vendor_id' => $foreignVendor->id,
            ])
            ->assertSessionHasErrors('vendor_id');

        $this->assertSame(0, $requirement->candidates()->count());
    }

    public function test_a_proposal_from_another_event_is_not_found(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $otherEvent = $this->eventIn($org);
        $owner = $this->memberOf($org, OrganizationRole::Owner);
        $vendor = $this->vendorIn($org);
        $foreignProposal = $this->proposalFor($this->requirementIn($otherEvent), $vendor);

        $this->actingAs($owner)
            ->put(route('workspace.events.vendors.proposals.update', [$org, $event, $foreignProposal]), [
                'title' => 'Percobaan ubah',
            ])
            ->assertNotFound();
    }

    public function test_unselected_proposal_history_remains_visible_to_an_authorized_user(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->memberOf($org, OrganizationRole::Owner);
        $requirement = $this->requirementIn($event);
        $chosen = $this->proposalFor($requirement, $this->vendorIn($org), [
            'status' => VendorProposalStatus::Submitted->value,
        ]);
        $unselected = $this->proposalFor($requirement, $this->vendorIn($org), [
            'status' => VendorProposalStatus::Submitted->value,
        ]);

        $this->actingAs($owner)
            ->post(route('workspace.events.vendors.proposals.select', [$org, $event, $chosen]), [
                'selection_reason' => 'Penawaran terbaik',
            ])
            ->assertRedirect();

        $this->assertTrue($owner->can('viewVendorProposals', $event));

        $history = $event->vendorProposals()->whereKey($unselected->id)->first();

        $this->assertNotNull($history);
        $this->assertSame(VendorProposalStatus::Rejected, $history->status);
        $this->assertModelExists($unselected);
    }
}
