<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\VendorStatus;
use App\Enums\VendorType;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Vendor;
use App\Services\AuditService;
use App\Services\VendorDirectoryService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceVendorDirectoryController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly VendorDirectoryService $vendors,
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request, Organization $organization)
    {
        $this->authorize('viewVendors', $organization);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:160'],
            'category_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(VendorStatus::class)],
            'type' => ['nullable', Rule::enum(VendorType::class)],
            'sort' => ['nullable', Rule::in(['name', 'created_at', 'status'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $filters = array_merge([
            'q' => null,
            'category_id' => null,
            'status' => null,
            'type' => null,
            'sort' => 'name',
            'direction' => 'asc',
        ], $validated);

        return view('workspace.vendors.index', [
            'organization' => $organization,
            'vendors' => $this->vendors->paginate($organization, $filters),
            'categories' => $this->vendors->listCategories($organization),
            'filters' => $filters,
            'canManage' => $request->user()->can('createVendors', $organization),
        ]);
    }

    public function create(Request $request, Organization $organization)
    {
        $this->authorize('createVendors', $organization);

        return view('workspace.vendors.create', [
            'organization' => $organization,
            'categories' => $this->vendors->listCategories($organization),
        ]);
    }

    public function store(Request $request, Organization $organization)
    {
        $this->authorize('createVendors', $organization);

        $data = $request->validate($this->vendorRules($organization, partial: false));

        $vendor = $this->vendors->create($organization, $data, $request->user());

        $this->audit->record(
            'vendor.created',
            "Vendor {$vendor->name} ditambahkan ke direktori.",
            null,
            $request->user(),
            $vendor,
        );

        return back()->with('status', 'Vendor ditambahkan.');
    }

    public function show(Request $request, Organization $organization, Vendor $vendor)
    {
        $this->authorize('viewVendors', $organization);

        $vendor = $organization->vendors()->findOrFail($vendor->id);

        $vendor->load([
            'category',
            'contacts' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('name'),
            'services.packages',
            'engagements' => fn ($query) => $query->with('event')->latest()->limit(20),
        ]);

        return view('workspace.vendors.show', [
            'organization' => $organization,
            'vendor' => $vendor,
            'canManage' => $request->user()->can('updateVendors', $organization),
            'canManageContacts' => $request->user()->can('manageVendorContacts', $organization),
            'canManageCatalog' => $request->user()->can('manageVendorCatalog', $organization),
        ]);
    }

    public function edit(Request $request, Organization $organization, Vendor $vendor)
    {
        $this->authorize('updateVendors', $organization);

        $vendor = $organization->vendors()->findOrFail($vendor->id);

        return view('workspace.vendors.edit', [
            'organization' => $organization,
            'vendor' => $vendor,
            'categories' => $this->vendors->listCategories($organization),
        ]);
    }

    public function update(Request $request, Organization $organization, Vendor $vendor)
    {
        $this->authorize('updateVendors', $organization);

        $vendor = $organization->vendors()->findOrFail($vendor->id);

        $data = $request->validate($this->vendorRules($organization, partial: true));

        $vendor = $this->vendors->update($vendor, $data, $request->user());

        $this->audit->record(
            'vendor.updated',
            "Vendor {$vendor->name} diperbarui.",
            null,
            $request->user(),
            $vendor,
        );

        return back()->with('status', 'Vendor diperbarui.');
    }

    public function status(Request $request, Organization $organization, Vendor $vendor)
    {
        $this->authorize('updateVendors', $organization);

        $vendor = $organization->vendors()->findOrFail($vendor->id);

        $data = $request->validate([
            'status' => ['required', Rule::enum(VendorStatus::class)],
        ]);

        $vendor = $this->vendors->changeStatus(
            $vendor,
            VendorStatus::from($data['status']),
            $request->user(),
        );

        $this->audit->record(
            'vendor.status_changed',
            "Status vendor {$vendor->name} menjadi {$vendor->status->label()}.",
            null,
            $request->user(),
            $vendor,
        );

        return back()->with('status', 'Status vendor diperbarui.');
    }

    public function archive(Request $request, Organization $organization, Vendor $vendor)
    {
        $this->authorize('archiveVendors', $organization);

        $vendor = $organization->vendors()->findOrFail($vendor->id);

        $vendor = $this->vendors->archive($vendor, $request->user());

        $this->audit->recordCritical(
            'vendor.archived',
            "Vendor {$vendor->name} diarsipkan.",
            null,
            $request->user(),
            $vendor,
        );

        return back()->with('status', 'Vendor diarsipkan.');
    }

    public function restore(Request $request, Organization $organization, Vendor $vendor)
    {
        $this->authorize('archiveVendors', $organization);

        $vendor = $organization->vendors()->findOrFail($vendor->id);

        $vendor = $this->vendors->restore($vendor, $request->user());

        $this->audit->recordCritical(
            'vendor.restored',
            "Vendor {$vendor->name} dikembalikan ke direktori aktif.",
            null,
            $request->user(),
            $vendor,
        );

        return back()->with('status', 'Vendor dipulihkan.');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function vendorRules(Organization $organization, bool $partial): array
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
            'legal_name' => ['nullable', 'string', 'max:200'],
            'type' => $partial
                ? ['sometimes', 'required', Rule::enum(VendorType::class)]
                : ['required', Rule::enum(VendorType::class)],
            'description' => ['nullable', 'string', 'max:4000'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
