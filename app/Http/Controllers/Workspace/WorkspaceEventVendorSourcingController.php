<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\VendorProposalStatus;
use App\Enums\VendorRequirementStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\AssertsEventInOrganization;
use App\Models\Event;
use App\Models\EventVendorProposal;
use App\Models\EventVendorRequirement;
use App\Models\EventVendorRequirementCandidate;
use App\Models\Organization;
use App\Services\VendorSourcingService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceEventVendorSourcingController extends Controller
{
    use AssertsEventInOrganization, AuthorizesRequests;

    public function __construct(private readonly VendorSourcingService $sourcing) {}

    public function storeRequirement(Request $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageVendorRequirements', $event);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'vendor_category_id' => [
                'nullable',
                'integer',
                Rule::exists('vendor_categories', 'id')->where('organization_id', $event->organization_id),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'scope' => ['nullable', 'string', 'max:5000'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'needed_by' => ['nullable', 'date'],
            'desired_availability' => ['nullable', 'string', 'max:500'],
            'owner_membership_id' => [
                'nullable',
                'integer',
                Rule::exists('event_staff_memberships', 'id')->where('event_id', $event->id),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::enum(VendorRequirementStatus::class)],
        ], [
            'title.required' => 'Judul kebutuhan wajib diisi.',
            'title.max' => 'Judul kebutuhan maksimal 180 karakter.',
            'vendor_category_id.integer' => 'Kategori vendor tidak valid.',
            'vendor_category_id.exists' => 'Kategori vendor tidak valid untuk organisasi ini.',
            'description.max' => 'Deskripsi maksimal 5000 karakter.',
            'scope.max' => 'Lingkup maksimal 5000 karakter.',
            'quantity.integer' => 'Jumlah harus berupa angka.',
            'quantity.min' => 'Jumlah minimal 1.',
            'needed_by.date' => 'Tanggal kebutuhan tidak valid.',
            'desired_availability.max' => 'Ketersediaan yang diinginkan maksimal 500 karakter.',
            'owner_membership_id.exists' => 'Penanggung jawab tidak valid untuk event ini.',
            'notes.max' => 'Catatan maksimal 2000 karakter.',
            'status.enum' => 'Status kebutuhan tidak valid.',
        ]);

        $this->sourcing->createRequirement($event, $data, $request->user());

        return back()->with('status', 'Kebutuhan vendor ditambahkan.');
    }

    public function updateRequirement(Request $request, Organization $organization, Event $event, EventVendorRequirement $requirement)
    {
        $this->assertEventInOrg($organization, $event);
        $requirement = $event->vendorRequirements()->findOrFail($requirement->getKey());
        $this->authorize('manageVendorRequirements', $event);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'vendor_category_id' => [
                'nullable',
                'integer',
                Rule::exists('vendor_categories', 'id')->where('organization_id', $event->organization_id),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'scope' => ['nullable', 'string', 'max:5000'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'needed_by' => ['nullable', 'date'],
            'desired_availability' => ['nullable', 'string', 'max:500'],
            'owner_membership_id' => [
                'nullable',
                'integer',
                Rule::exists('event_staff_memberships', 'id')->where('event_id', $event->id),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::enum(VendorRequirementStatus::class)],
        ], [
            'title.required' => 'Judul kebutuhan wajib diisi.',
            'title.max' => 'Judul kebutuhan maksimal 180 karakter.',
            'vendor_category_id.integer' => 'Kategori vendor tidak valid.',
            'vendor_category_id.exists' => 'Kategori vendor tidak valid untuk organisasi ini.',
            'description.max' => 'Deskripsi maksimal 5000 karakter.',
            'scope.max' => 'Lingkup maksimal 5000 karakter.',
            'quantity.integer' => 'Jumlah harus berupa angka.',
            'quantity.min' => 'Jumlah minimal 1.',
            'needed_by.date' => 'Tanggal kebutuhan tidak valid.',
            'desired_availability.max' => 'Ketersediaan yang diinginkan maksimal 500 karakter.',
            'owner_membership_id.exists' => 'Penanggung jawab tidak valid untuk event ini.',
            'notes.max' => 'Catatan maksimal 2000 karakter.',
            'status.enum' => 'Status kebutuhan tidak valid.',
        ]);

        $this->sourcing->updateRequirement($event, $requirement, $data, $request->user());

        return back()->with('status', 'Kebutuhan vendor diperbarui.');
    }

    public function destroyRequirement(Organization $organization, Event $event, EventVendorRequirement $requirement)
    {
        $this->assertEventInOrg($organization, $event);
        $requirement = $event->vendorRequirements()->findOrFail($requirement->getKey());
        $this->authorize('manageVendorRequirements', $event);

        $this->sourcing->deleteRequirement($event, $requirement, request()->user());

        return back()->with('status', 'Kebutuhan vendor dihapus.');
    }

    public function storeCandidate(Request $request, Organization $organization, Event $event, EventVendorRequirement $requirement)
    {
        $this->assertEventInOrg($organization, $event);
        $requirement = $event->vendorRequirements()->findOrFail($requirement->getKey());
        $this->authorize('manageVendorRequirements', $event);

        $data = $request->validate([
            'vendor_id' => [
                'required',
                'integer',
                Rule::exists('vendors', 'id')->where('organization_id', $event->organization_id),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'vendor_id.required' => 'Vendor wajib dipilih.',
            'vendor_id.integer' => 'Vendor tidak valid.',
            'vendor_id.exists' => 'Vendor tidak valid untuk organisasi ini.',
            'notes.max' => 'Catatan maksimal 2000 karakter.',
        ]);

        $this->sourcing->addCandidate(
            $requirement,
            (int) $data['vendor_id'],
            $request->user(),
            $data['notes'] ?? null,
        );

        return back()->with('status', 'Kandidat vendor ditambahkan ke daftar pendek.');
    }

    public function destroyCandidate(
        Organization $organization,
        Event $event,
        EventVendorRequirement $requirement,
        EventVendorRequirementCandidate $candidate,
    ) {
        $this->assertEventInOrg($organization, $event);
        $requirement = $event->vendorRequirements()->findOrFail($requirement->getKey());
        abort_unless((int) $candidate->event_vendor_requirement_id === (int) $requirement->id, 404);
        $this->authorize('manageVendorRequirements', $event);

        $this->sourcing->removeCandidate($requirement, $candidate, request()->user());

        return back()->with('status', 'Kandidat vendor dihapus dari daftar pendek.');
    }

    public function preferCandidate(
        Organization $organization,
        Event $event,
        EventVendorRequirement $requirement,
        EventVendorRequirementCandidate $candidate,
    ) {
        $this->assertEventInOrg($organization, $event);
        $requirement = $event->vendorRequirements()->findOrFail($requirement->getKey());
        abort_unless((int) $candidate->event_vendor_requirement_id === (int) $requirement->id, 404);
        $this->authorize('manageVendorRequirements', $event);

        $this->sourcing->preferCandidate($requirement, $candidate, request()->user());

        return back()->with('status', 'Kandidat vendor ditandai sebagai pilihan utama.');
    }

    public function storeProposal(Request $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageVendorProposals', $event);

        $data = $request->validate([
            'event_vendor_requirement_id' => [
                'required',
                'integer',
                Rule::exists('event_vendor_requirements', 'id')->where('event_id', $event->id),
            ],
            'vendor_id' => [
                'required',
                'integer',
                Rule::exists('vendors', 'id')->where('organization_id', $event->organization_id),
            ],
            'title' => ['required', 'string', 'max:180'],
            'service_description' => ['nullable', 'string', 'max:5000'],
            'vendor_service_package_id' => ['nullable', 'integer'],
            'quoted_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3', 'uppercase'],
            'inclusions' => ['nullable', 'array'],
            'inclusions.*' => ['string', 'max:500'],
            'exclusions' => ['nullable', 'array'],
            'exclusions.*' => ['string', 'max:500'],
            'valid_until' => ['nullable', 'date'],
            'proposed_schedule' => ['nullable', 'string', 'max:2000'],
            'status' => [
                'nullable',
                Rule::enum(VendorProposalStatus::class),
                Rule::in([VendorProposalStatus::Draft->value, VendorProposalStatus::Submitted->value]),
            ],
            'evaluation_notes' => ['nullable', 'string', 'max:2000'],
            'evaluation_rating' => ['nullable', 'integer', 'between:1,5'],
        ], [
            'event_vendor_requirement_id.required' => 'Kebutuhan vendor wajib dipilih.',
            'event_vendor_requirement_id.exists' => 'Kebutuhan vendor tidak valid untuk event ini.',
            'vendor_id.required' => 'Vendor wajib dipilih.',
            'vendor_id.integer' => 'Vendor tidak valid.',
            'vendor_id.exists' => 'Vendor tidak valid untuk organisasi ini.',
            'title.required' => 'Judul proposal wajib diisi.',
            'title.max' => 'Judul proposal maksimal 180 karakter.',
            'service_description.max' => 'Deskripsi layanan maksimal 5000 karakter.',
            'vendor_service_package_id.integer' => 'Paket layanan tidak valid.',
            'quoted_amount.numeric' => 'Nilai penawaran harus berupa angka.',
            'quoted_amount.min' => 'Nilai penawaran tidak boleh negatif.',
            'currency.size' => 'Mata uang harus 3 karakter.',
            'currency.uppercase' => 'Mata uang harus berupa huruf kapital.',
            'inclusions.array' => 'Rincian termasuk harus berupa daftar.',
            'inclusions.*.max' => 'Setiap rincian termasuk maksimal 500 karakter.',
            'exclusions.array' => 'Rincian tidak termasuk harus berupa daftar.',
            'exclusions.*.max' => 'Setiap rincian tidak termasuk maksimal 500 karakter.',
            'valid_until.date' => 'Tanggal berlaku tidak valid.',
            'proposed_schedule.max' => 'Jadwal usulan maksimal 2000 karakter.',
            'status.enum' => 'Status proposal tidak valid.',
            'status.in' => 'Status proposal awal hanya boleh draf atau diajukan.',
            'evaluation_notes.max' => 'Catatan evaluasi maksimal 2000 karakter.',
            'evaluation_rating.integer' => 'Penilaian harus berupa angka.',
            'evaluation_rating.between' => 'Penilaian harus antara 1 sampai 5.',
        ]);

        $requirement = $event->vendorRequirements()->findOrFail($data['event_vendor_requirement_id']);

        $this->sourcing->storeProposal($requirement, $data, $request->user());

        return back()->with('status', 'Proposal vendor dicatat.');
    }

    public function updateProposal(Request $request, Organization $organization, Event $event, EventVendorProposal $proposal)
    {
        $this->assertEventInOrg($organization, $event);
        $proposal = $event->vendorProposals()->findOrFail($proposal->getKey());
        $this->authorize('manageVendorProposals', $event);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'service_description' => ['nullable', 'string', 'max:5000'],
            'quoted_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3', 'uppercase'],
            'inclusions' => ['nullable', 'array'],
            'inclusions.*' => ['string', 'max:500'],
            'exclusions' => ['nullable', 'array'],
            'exclusions.*' => ['string', 'max:500'],
            'valid_until' => ['nullable', 'date'],
            'proposed_schedule' => ['nullable', 'string', 'max:2000'],
            'evaluation_notes' => ['nullable', 'string', 'max:2000'],
            'evaluation_rating' => ['nullable', 'integer', 'between:1,5'],
        ], [
            'title.required' => 'Judul proposal wajib diisi.',
            'title.max' => 'Judul proposal maksimal 180 karakter.',
            'service_description.max' => 'Deskripsi layanan maksimal 5000 karakter.',
            'quoted_amount.numeric' => 'Nilai penawaran harus berupa angka.',
            'quoted_amount.min' => 'Nilai penawaran tidak boleh negatif.',
            'currency.size' => 'Mata uang harus 3 karakter.',
            'currency.uppercase' => 'Mata uang harus berupa huruf kapital.',
            'inclusions.array' => 'Rincian termasuk harus berupa daftar.',
            'inclusions.*.max' => 'Setiap rincian termasuk maksimal 500 karakter.',
            'exclusions.array' => 'Rincian tidak termasuk harus berupa daftar.',
            'exclusions.*.max' => 'Setiap rincian tidak termasuk maksimal 500 karakter.',
            'valid_until.date' => 'Tanggal berlaku tidak valid.',
            'proposed_schedule.max' => 'Jadwal usulan maksimal 2000 karakter.',
            'evaluation_notes.max' => 'Catatan evaluasi maksimal 2000 karakter.',
            'evaluation_rating.integer' => 'Penilaian harus berupa angka.',
            'evaluation_rating.between' => 'Penilaian harus antara 1 sampai 5.',
        ]);

        $this->sourcing->updateProposal($event, $proposal, $data, $request->user());

        return back()->with('status', 'Proposal vendor diperbarui.');
    }

    public function proposalStatus(Request $request, Organization $organization, Event $event, EventVendorProposal $proposal)
    {
        $this->assertEventInOrg($organization, $event);
        $proposal = $event->vendorProposals()->findOrFail($proposal->getKey());
        $this->authorize('manageVendorProposals', $event);

        $data = $request->validate([
            'status' => ['required', Rule::enum(VendorProposalStatus::class)],
        ], [
            'status.required' => 'Status proposal wajib diisi.',
            'status.enum' => 'Status proposal tidak valid.',
        ]);

        $this->sourcing->transitionProposal(
            $event,
            $proposal,
            VendorProposalStatus::from($data['status']),
            $request->user(),
        );

        return back()->with('status', 'Status proposal diperbarui.');
    }

    public function selectProposal(Request $request, Organization $organization, Event $event, EventVendorProposal $proposal)
    {
        $this->assertEventInOrg($organization, $event);
        $proposal = $event->vendorProposals()->findOrFail($proposal->getKey());
        $this->authorize('selectVendors', $event);

        $data = $request->validate([
            'selection_reason' => ['required', 'string', 'max:2000'],
        ], [
            'selection_reason.required' => 'Alasan pemilihan vendor wajib diisi.',
            'selection_reason.max' => 'Alasan pemilihan vendor maksimal 2000 karakter.',
        ]);

        $engagement = $this->sourcing->selectProposal(
            $event,
            $proposal,
            $data['selection_reason'],
            $request->user(),
        );

        return redirect()
            ->route('workspace.events.vendors.engagements.show', [$organization, $event, $engagement])
            ->with('status', 'Vendor berhasil dipilih dan engagement dibuat.');
    }
}
