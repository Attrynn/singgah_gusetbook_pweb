<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\VendorDocumentReviewStatus;
use App\Enums\VendorDocumentType;
use App\Enums\VendorDocumentVisibility;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\AssertsEventInOrganization;
use App\Models\Event;
use App\Models\EventVendorDocument;
use App\Models\EventVendorEngagement;
use App\Models\Organization;
use App\Services\AuditService;
use App\Services\VendorDocumentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceEventVendorDocumentController extends Controller
{
    use AssertsEventInOrganization, AuthorizesRequests;

    private const COMMERCIAL_TYPES = [
        'quotation', 'agreement', 'contract', 'invoice_reference', 'amendment',
    ];

    public function __construct(
        private readonly VendorDocumentService $documents,
        private readonly AuditService $audit,
    ) {}

    public function store(Request $request, Organization $organization, Event $event, EventVendorEngagement $engagement)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $engagement->event_id === (int) $event->id, 404);

        $this->authorize('manageVendorAgreements', $event);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'type' => ['required', Rule::enum(VendorDocumentType::class)],
            'title' => ['required', 'string', 'max:180'],
            'visibility' => ['required', Rule::enum(VendorDocumentVisibility::class)],
            'review_status' => ['nullable', Rule::enum(VendorDocumentReviewStatus::class)],
            'effective_date' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'supersedes_id' => ['nullable', 'integer'],
            'event_vendor_proposal_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($data['visibility'] === VendorDocumentVisibility::RestrictedCommercial->value) {
            $this->authorize('viewVendorCommercial', $event);
        }

        $document = $this->documents->store($event, $engagement, $request->file('file'), $data, $request->user());

        $this->audit->record(
            'vendor.document.uploaded',
            "Dokumen vendor diunggah: {$document->title} (v{$document->version})",
            $event,
            $request->user(),
            $document,
            ['visibility' => $document->visibility->value, 'type' => $document->type->value],
        );

        return back()->with('status', 'Dokumen vendor tersimpan.');
    }

    public function review(Request $request, Organization $organization, Event $event, EventVendorDocument $document)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $document->event_id === (int) $event->id, 404);

        $this->authorize('manageVendorAgreements', $event);

        $data = $request->validate([
            'review_status' => ['required', Rule::enum(VendorDocumentReviewStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $status = VendorDocumentReviewStatus::from($data['review_status']);

        if (in_array($status, [VendorDocumentReviewStatus::Approved, VendorDocumentReviewStatus::Rejected], true)) {
            $this->authorize('approveVendorCommercial', $event);
        }

        $document = $this->documents->review($document, $status, $request->user(), $data['notes'] ?? null);

        $this->audit->recordCritical(
            'vendor.document.reviewed',
            "Status dokumen vendor: {$document->title} → {$document->review_status->value}",
            $event,
            $request->user(),
            $document,
        );

        return back()->with('status', 'Status dokumen diperbarui.');
    }

    public function download(Request $request, Organization $organization, Event $event, EventVendorDocument $document)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $document->event_id === (int) $event->id, 404);

        $isCommercial = in_array($document->type->value, self::COMMERCIAL_TYPES, true)
            || $document->visibility === VendorDocumentVisibility::RestrictedCommercial;

        if ($isCommercial) {
            $this->authorize('viewVendorCommercial', $event);
        } else {
            $this->authorize('viewVendorEngagements', $event);
        }

        $this->audit->record(
            'vendor.document.downloaded',
            "Dokumen vendor diunduh: {$document->title}",
            $event,
            $request->user(),
            $document,
        );

        return $this->documents->stream($document);
    }
}
