<?php

namespace Tests\Feature\M7;

use App\Enums\OrganizationRole;
use App\Enums\VendorContactPurpose;
use App\Enums\VendorStatus;
use App\Models\Organization;
use App\Models\User;
use App\Models\VendorCategory;
use App\Models\VendorContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\M7\Concerns\BuildsVendorFixtures;
use Tests\TestCase;

class VendorCatalogTest extends TestCase
{
    use BuildsVendorFixtures, RefreshDatabase;

    private function owner(Organization $organization): User
    {
        return $this->memberOf($organization, OrganizationRole::Owner);
    }

    public function test_contacts_can_be_created_updated_and_deleted(): void
    {
        $org = $this->organization();
        $owner = $this->owner($org);
        $vendor = $this->vendorIn($org);

        $this->actingAs($owner)
            ->post(route('workspace.vendors.contacts.store', [$org, $vendor]), [
                'name' => 'Budi',
                'purpose' => VendorContactPurpose::Sales->value,
                'email' => 'budi@example.test',
                'is_primary' => true,
            ])
            ->assertRedirect();

        $contact = VendorContact::query()
            ->where('vendor_id', $vendor->id)
            ->where('name', 'Budi')
            ->first();

        $this->assertNotNull($contact);
        $this->assertSame(VendorContactPurpose::Sales, $contact->purpose);

        $this->actingAs($owner)
            ->put(route('workspace.vendors.contacts.update', [$org, $vendor, $contact]), [
                'name' => 'Budi Santoso',
            ])
            ->assertRedirect();

        $this->assertSame('Budi Santoso', $contact->fresh()->name);

        $this->actingAs($owner)
            ->delete(route('workspace.vendors.contacts.destroy', [$org, $vendor, $contact]))
            ->assertRedirect();

        $this->assertDatabaseMissing('vendor_contacts', ['id' => $contact->id]);
    }

    public function test_contact_referenced_by_an_engagement_is_deactivated_instead_of_deleted(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $vendor = $this->vendorIn($org);
        $contact = $this->contactFor($vendor);

        $this->engagementFor($event, $vendor, ['vendor_contact_id' => $contact->id]);

        $this->actingAs($owner)
            ->delete(route('workspace.vendors.contacts.destroy', [$org, $vendor, $contact]))
            ->assertRedirect();

        $contact->refresh();

        $this->assertFalse($contact->is_active);
        $this->assertDatabaseHas('vendor_contacts', ['id' => $contact->id]);
    }

    public function test_category_slugs_are_unique_within_an_organization(): void
    {
        $org = $this->organization();
        $owner = $this->owner($org);

        $this->actingAs($owner)
            ->post(route('workspace.vendor-categories.store', $org), ['name' => 'Katering'])
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('workspace.vendor-categories.store', $org), ['name' => 'Katering'])
            ->assertRedirect();

        $slugs = VendorCategory::query()
            ->where('organization_id', $org->id)
            ->orderBy('id')
            ->pluck('slug')
            ->all();

        $this->assertSame(['katering', 'katering-2'], $slugs);
    }

    public function test_category_with_vendors_cannot_be_deleted(): void
    {
        $org = $this->organization();
        $owner = $this->owner($org);
        $category = VendorCategory::create([
            'organization_id' => $org->id,
            'name' => 'Katering',
            'slug' => 'katering',
        ]);
        $this->vendorIn($org, ['vendor_category_id' => $category->id]);

        $this->actingAs($owner)
            ->delete(route('workspace.vendor-categories.destroy', [$org, $category]))
            ->assertRedirect()
            ->assertSessionHasErrors('category');

        $this->assertDatabaseHas('vendor_categories', ['id' => $category->id]);
    }

    public function test_service_referenced_by_an_engagement_is_deactivated_instead_of_deleted(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $vendor = $this->vendorIn($org);
        $service = $this->serviceFor($vendor);

        $this->engagementFor($event, $vendor, ['vendor_service_id' => $service->id]);

        $this->actingAs($owner)
            ->delete(route('workspace.vendors.services.destroy', [$org, $vendor, $service]))
            ->assertRedirect();

        $service->refresh();

        $this->assertFalse($service->is_active);
        $this->assertDatabaseHas('vendor_services', ['id' => $service->id]);
    }

    public function test_updating_a_package_bumps_its_version_without_touching_engagement_snapshots(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $vendor = $this->vendorIn($org);
        $service = $this->serviceFor($vendor);
        $package = $this->packageFor($service);

        $snapshot = ['name' => 'Paket Lama', 'price' => '15000000.00'];

        $engagement = $this->engagementFor($event, $vendor, [
            'vendor_service_id' => $service->id,
            'vendor_service_package_id' => $package->id,
            'package_snapshot' => $snapshot,
        ]);

        $this->actingAs($owner)
            ->put(route('workspace.vendors.services.packages.update', [$org, $vendor, $service, $package]), [
                'name' => 'Paket Baru',
                'price_amount' => '17500000.00',
            ])
            ->assertRedirect();

        $package->refresh();

        $this->assertSame(2, $package->version);
        $this->assertSame('Paket Baru', $package->name);
        $this->assertSame('17500000.00', $package->price_amount);
        $this->assertSame($snapshot, $engagement->fresh()->package_snapshot);
    }

    public function test_invalid_vendor_status_enum_is_rejected(): void
    {
        $org = $this->organization();
        $owner = $this->owner($org);
        $vendor = $this->vendorIn($org, ['status' => VendorStatus::Active->value]);

        $this->actingAs($owner)
            ->postJson(route('workspace.vendors.status', [$org, $vendor]), ['status' => 'bogus'])
            ->assertStatus(422);
    }
}
