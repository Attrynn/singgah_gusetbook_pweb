<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\VendorContactPurpose;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Vendor;
use App\Models\VendorCategory;
use App\Models\VendorContact;
use App\Models\VendorService;
use App\Models\VendorServicePackage;
use App\Services\AuditService;
use App\Services\VendorDirectoryService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceVendorCatalogController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly VendorDirectoryService $vendors,
        private readonly AuditService $audit,
    ) {}

    public function storeContact(Request $request, Organization $organization, Vendor $vendor)
    {
        $this->authorize('manageVendorContacts', $organization);

        $vendor = $this->vendorIn($organization, $vendor);

        $data = $request->validate($this->contactRules(partial: false));

        $contact = $this->vendors->createContact($vendor, $data, $request->user());

        $this->audit->record(
            'vendor.contact.created',
            "Kontak {$contact->name} ditambahkan untuk vendor {$vendor->name}.",
            null,
            $request->user(),
            $contact,
        );

        return back()->with('status', 'Kontak vendor ditambahkan.');
    }

    public function updateContact(Request $request, Organization $organization, Vendor $vendor, VendorContact $contact)
    {
        $this->authorize('manageVendorContacts', $organization);

        $vendor = $this->vendorIn($organization, $vendor);
        $this->assertContactInVendor($vendor, $contact);

        $data = $request->validate($this->contactRules(partial: true));

        $contact = $this->vendors->updateContact($vendor, $contact, $data, $request->user());

        $this->audit->record(
            'vendor.contact.updated',
            "Kontak {$contact->name} diperbarui.",
            null,
            $request->user(),
            $contact,
        );

        return back()->with('status', 'Kontak vendor diperbarui.');
    }

    public function destroyContact(Request $request, Organization $organization, Vendor $vendor, VendorContact $contact)
    {
        $this->authorize('manageVendorContacts', $organization);

        $vendor = $this->vendorIn($organization, $vendor);
        $this->assertContactInVendor($vendor, $contact);

        $this->vendors->destroyContact($vendor, $contact, $request->user());

        $this->audit->record(
            'vendor.contact.deleted',
            "Kontak {$contact->name} dihapus atau dinonaktifkan.",
            null,
            $request->user(),
            $contact,
        );

        return back()->with('status', 'Kontak vendor dihapus.');
    }

    public function categories(Request $request, Organization $organization)
    {
        $this->authorize('viewVendors', $organization);

        return view('workspace.vendor-categories.index', [
            'organization' => $organization,
            'categories' => $this->vendors->listCategories($organization),
        ]);
    }

    public function storeCategory(Request $request, Organization $organization)
    {
        $this->authorize('manageVendorCatalog', $organization);

        $data = $request->validate($this->categoryRules(partial: false));

        $category = $this->vendors->createCategory($organization, $data, $request->user());

        $this->audit->record(
            'vendor.category.created',
            "Kategori vendor {$category->name} dibuat.",
            null,
            $request->user(),
            $category,
        );

        return back()->with('status', 'Kategori vendor ditambahkan.');
    }

    public function updateCategory(Request $request, Organization $organization, VendorCategory $category)
    {
        $this->authorize('manageVendorCatalog', $organization);

        $category = $organization->vendorCategories()->findOrFail($category->id);

        $data = $request->validate($this->categoryRules(partial: true));

        $category = $this->vendors->updateCategory($organization, $category, $data, $request->user());

        $this->audit->record(
            'vendor.category.updated',
            "Kategori vendor {$category->name} diperbarui.",
            null,
            $request->user(),
            $category,
        );

        return back()->with('status', 'Kategori vendor diperbarui.');
    }

    public function destroyCategory(Request $request, Organization $organization, VendorCategory $category)
    {
        $this->authorize('manageVendorCatalog', $organization);

        $category = $organization->vendorCategories()->findOrFail($category->id);

        $this->vendors->destroyCategory($organization, $category, $request->user());

        $this->audit->record(
            'vendor.category.deleted',
            "Kategori vendor {$category->name} dihapus.",
            null,
            $request->user(),
            $category,
        );

        return back()->with('status', 'Kategori vendor dihapus.');
    }

    public function storeService(Request $request, Organization $organization, Vendor $vendor)
    {
        $this->authorize('manageVendorCatalog', $organization);

        $vendor = $this->vendorIn($organization, $vendor);

        $data = $request->validate($this->serviceRules($organization, partial: false));

        $service = $this->vendors->createService($vendor, $data, $request->user());

        $this->audit->record(
            'vendor.service.created',
            "Layanan {$service->name} ditambahkan untuk vendor {$vendor->name}.",
            null,
            $request->user(),
            $service,
        );

        return back()->with('status', 'Layanan vendor ditambahkan.');
    }

    public function updateService(Request $request, Organization $organization, Vendor $vendor, VendorService $service)
    {
        $this->authorize('manageVendorCatalog', $organization);

        $vendor = $this->vendorIn($organization, $vendor);
        $this->assertServiceInVendor($vendor, $service);

        $data = $request->validate($this->serviceRules($organization, partial: true));

        $service = $this->vendors->updateService($vendor, $service, $data, $request->user());

        $this->audit->record(
            'vendor.service.updated',
            "Layanan {$service->name} diperbarui.",
            null,
            $request->user(),
            $service,
        );

        return back()->with('status', 'Layanan vendor diperbarui.');
    }

    public function destroyService(Request $request, Organization $organization, Vendor $vendor, VendorService $service)
    {
        $this->authorize('manageVendorCatalog', $organization);

        $vendor = $this->vendorIn($organization, $vendor);
        $this->assertServiceInVendor($vendor, $service);

        $this->vendors->destroyService($vendor, $service, $request->user());

        $this->audit->record(
            'vendor.service.deleted',
            "Layanan {$service->name} dihapus atau dinonaktifkan.",
            null,
            $request->user(),
            $service,
        );

        return back()->with('status', 'Layanan vendor dihapus.');
    }

    public function storePackage(Request $request, Organization $organization, Vendor $vendor, VendorService $service)
    {
        $this->authorize('manageVendorCatalog', $organization);

        $vendor = $this->vendorIn($organization, $vendor);
        $this->assertServiceInVendor($vendor, $service);

        $data = $request->validate($this->packageRules(partial: false));

        $package = $this->vendors->createPackage($vendor, $service, $data, $request->user());

        $this->audit->record(
            'vendor.package.created',
            "Paket {$package->name} ditambahkan untuk layanan {$service->name}.",
            null,
            $request->user(),
            $package,
        );

        return back()->with('status', 'Paket layanan ditambahkan.');
    }

    public function updatePackage(Request $request, Organization $organization, Vendor $vendor, VendorService $service, VendorServicePackage $package)
    {
        $this->authorize('manageVendorCatalog', $organization);

        $vendor = $this->vendorIn($organization, $vendor);
        $this->assertServiceInVendor($vendor, $service);
        $this->assertPackageInService($service, $package);

        $data = $request->validate($this->packageRules(partial: true));

        $package = $this->vendors->updatePackage($vendor, $service, $package, $data, $request->user());

        $this->audit->record(
            'vendor.package.updated',
            "Paket {$package->name} diperbarui (versi {$package->version}).",
            null,
            $request->user(),
            $package,
        );

        return back()->with('status', 'Paket layanan diperbarui.');
    }

    public function destroyPackage(Request $request, Organization $organization, Vendor $vendor, VendorService $service, VendorServicePackage $package)
    {
        $this->authorize('manageVendorCatalog', $organization);

        $vendor = $this->vendorIn($organization, $vendor);
        $this->assertServiceInVendor($vendor, $service);
        $this->assertPackageInService($service, $package);

        $this->vendors->destroyPackage($vendor, $service, $package, $request->user());

        $this->audit->record(
            'vendor.package.deleted',
            "Paket {$package->name} dihapus atau dinonaktifkan.",
            null,
            $request->user(),
            $package,
        );

        return back()->with('status', 'Paket layanan dihapus.');
    }

    private function vendorIn(Organization $organization, Vendor $vendor): Vendor
    {
        return $organization->vendors()->findOrFail($vendor->id);
    }

    private function assertContactInVendor(Vendor $vendor, VendorContact $contact): void
    {
        abort_unless((int) $contact->vendor_id === (int) $vendor->id, 404);
    }

    private function assertServiceInVendor(Vendor $vendor, VendorService $service): void
    {
        abort_unless((int) $service->vendor_id === (int) $vendor->id, 404);
    }

    private function assertPackageInService(VendorService $service, VendorServicePackage $package): void
    {
        abort_unless((int) $package->vendor_service_id === (int) $service->id, 404);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function contactRules(bool $partial): array
    {
        return [
            'name' => $partial
                ? ['sometimes', 'required', 'string', 'max:160']
                : ['required', 'string', 'max:160'],
            'position' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'purpose' => ['nullable', Rule::enum(VendorContactPurpose::class)],
            'is_primary' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function categoryRules(bool $partial): array
    {
        return [
            'name' => $partial
                ? ['sometimes', 'required', 'string', 'max:120']
                : ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function serviceRules(Organization $organization, bool $partial): array
    {
        return [
            'name' => $partial
                ? ['sometimes', 'required', 'string', 'max:160']
                : ['required', 'string', 'max:160'],
            'vendor_category_id' => [
                'nullable',
                'integer',
                Rule::exists('vendor_categories', 'id')->where('organization_id', $organization->id),
            ],
            'description' => ['nullable', 'string', 'max:4000'],
            'scope' => ['nullable', 'string', 'max:4000'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'conditions' => ['nullable', 'string', 'max:4000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function packageRules(bool $partial): array
    {
        return [
            'name' => $partial
                ? ['sometimes', 'required', 'string', 'max:160']
                : ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:4000'],
            'price_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'inclusions' => ['nullable', 'array'],
            'inclusions.*' => ['nullable', 'string', 'max:255'],
            'exclusions' => ['nullable', 'array'],
            'exclusions.*' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'conditions' => ['nullable', 'string', 'max:4000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
