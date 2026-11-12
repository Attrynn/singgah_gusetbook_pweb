<?php

namespace Tests\Feature\M7;

use App\Enums\EventStaffRole;
use App\Enums\OrganizationRole;
use App\Enums\VendorStatus;
use App\Enums\VendorType;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorCategory;
use App\Services\VendorDirectoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\M7\Concerns\BuildsVendorFixtures;
use Tests\TestCase;

class VendorDirectoryTest extends TestCase
{
    use BuildsVendorFixtures, RefreshDatabase;

    private function owner(Organization $organization): User
    {
        return $this->memberOf($organization, OrganizationRole::Owner);
    }

    public function test_owner_can_create_a_vendor_and_the_action_is_audited(): void
    {
        $org = $this->organization();
        $owner = $this->owner($org);

        $this->actingAs($owner)
            ->post(route('workspace.vendors.store', $org), [
                'name' => 'Vendor Baru',
                'type' => VendorType::Company->value,
                'email' => 'vendor@example.test',
            ])
            ->assertRedirect();

        $vendor = Vendor::query()
            ->where('organization_id', $org->id)
            ->where('name', 'Vendor Baru')
            ->first();

        $this->assertNotNull($vendor);
        $this->assertSame(VendorStatus::PendingVerification, $vendor->status);
        $this->assertSame($owner->id, $vendor->created_by);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'vendor.created',
            'target_type' => Vendor::class,
            'target_id' => $vendor->id,
        ]);
    }

    public function test_vendor_from_another_organization_is_not_visible(): void
    {
        $orgA = $this->organization('a');
        $orgB = $this->organization('b');
        $owner = $this->owner($orgA);
        $foreignVendor = $this->vendorIn($orgB);

        $this->actingAs($owner)
            ->get(route('workspace.vendors.show', [$orgA, $foreignVendor]))
            ->assertNotFound();
    }

    public function test_directory_filters_by_query_category_and_status(): void
    {
        $org = $this->organization();
        $category = VendorCategory::create([
            'organization_id' => $org->id,
            'name' => 'Katering',
            'slug' => 'katering',
        ]);

        $matching = $this->vendorIn($org, [
            'name' => 'Catering Enak',
            'status' => VendorStatus::Active->value,
            'vendor_category_id' => $category->id,
        ]);
        $inactive = $this->vendorIn($org, [
            'name' => 'Dekorasi Indah',
            'status' => VendorStatus::Inactive->value,
        ]);

        $service = app(VendorDirectoryService::class);

        $byQuery = $service->paginate($org, ['q' => 'Catering']);
        $this->assertCount(1, $byQuery->items());
        $this->assertSame($matching->id, $byQuery->items()[0]->id);

        $byCategory = $service->paginate($org, ['category_id' => $category->id]);
        $this->assertCount(1, $byCategory->items());
        $this->assertSame($matching->id, $byCategory->items()[0]->id);

        $byStatus = $service->paginate($org, ['status' => VendorStatus::Inactive->value]);
        $this->assertCount(1, $byStatus->items());
        $this->assertSame($inactive->id, $byStatus->items()[0]->id);
    }

    public function test_archiving_keeps_engagements_and_sets_archived_at(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $vendor = $this->vendorIn($org);
        $engagement = $this->engagementFor($event, $vendor);

        $this->actingAs($owner)
            ->post(route('workspace.vendors.archive', [$org, $vendor]))
            ->assertRedirect();

        $vendor->refresh();

        $this->assertSame(VendorStatus::Archived, $vendor->status);
        $this->assertNotNull($vendor->archived_at);
        $this->assertDatabaseHas('event_vendor_engagements', ['id' => $engagement->id]);
    }

    public function test_archived_vendor_can_be_restored(): void
    {
        $org = $this->organization();
        $owner = $this->owner($org);
        $vendor = $this->vendorIn($org, [
            'status' => VendorStatus::Archived->value,
            'archived_at' => now(),
        ]);

        $this->actingAs($owner)
            ->post(route('workspace.vendors.restore', [$org, $vendor]))
            ->assertRedirect();

        $vendor->refresh();

        $this->assertSame(VendorStatus::Active, $vendor->status);
        $this->assertNull($vendor->archived_at);
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $org = $this->organization();
        $owner = $this->owner($org);
        $vendor = $this->vendorIn($org, ['status' => VendorStatus::Active->value]);

        $this->expectException(ValidationException::class);

        app(VendorDirectoryService::class)->changeStatus($vendor, VendorStatus::PendingVerification, $owner);
    }

    public function test_staff_without_vendor_create_permission_cannot_store(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $staff = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);

        $this->actingAs($staff->user)
            ->post(route('workspace.vendors.store', $org), [
                'name' => 'Vendor Terlarang',
                'type' => VendorType::Company->value,
            ])
            ->assertForbidden();
    }

    public function test_owner_of_another_organization_cannot_view_the_directory(): void
    {
        $orgA = $this->organization('a');
        $orgB = $this->organization('b');
        $foreignOwner = $this->owner($orgB);
        $vendor = $this->vendorIn($orgA);

        $response = $this->actingAs($foreignOwner)
            ->get(route('workspace.vendors.show', [$orgA, $vendor]));

        $this->assertContains($response->status(), [403, 404]);
    }
}
