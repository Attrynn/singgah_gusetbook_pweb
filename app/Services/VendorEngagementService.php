<?php

namespace App\Services;

use App\Enums\VendorChangeRequestStatus;
use App\Enums\VendorDeliverableStatus;
use App\Enums\VendorEngagementStatus;
use App\Enums\VendorIssueStatus;
use App\Models\Event;
use App\Models\EventVendorChangeRequest;
use App\Models\EventVendorEngagement;
use App\Models\EventVendorEngagementRevision;
use App\Models\EventVendorProposal;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorService;
use App\Models\VendorServicePackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Vendor engagement lifecycle.
 *
 * Engagements carry immutable snapshots of the selected service, package and
 * agreed scope. Once an engagement leaves the negotiation states, commercial
 * terms can only change through an approved change request that produces a new
 * revision — never by silently editing the agreement.
 */
class VendorEngagementService
{
    public function __construct(private readonly AuditService $audit) {}

    public function create(Event $event, array $data, User $actor): EventVendorEngagement
    {
        $vendor = Vendor::query()
            ->where('organization_id', $event->organization_id)
            ->findOrFail($data['vendor_id']);

        $service = null;
        $package = null;

        if (! empty($data['vendor_service_id'])) {
            $service = VendorService::query()
                ->where('vendor_id', $vendor->id)
                ->findOrFail($data['vendor_service_id']);
        }

        if (! empty($data['vendor_service_package_id'])) {
            $package = VendorServicePackage::query()
                ->when($service !== null, fn ($query) => $query->where('vendor_service_id', $service->id))
                ->findOrFail($data['vendor_service_package_id']);
        }

        $proposal = null;

        if (! empty($data['event_vendor_proposal_id'])) {
            $proposal = EventVendorProposal::query()
                ->where('event_id', $event->id)
                ->where('vendor_id', $vendor->id)
                ->findOrFail($data['event_vendor_proposal_id']);
        }

        $agreedAmount = $data['agreed_amount'] ?? $package?->price_amount ?? $proposal?->quoted_amount;

        $engagement = DB::transaction(function () use ($event, $data, $actor, $vendor, $service, $package, $proposal, $agreedAmount): EventVendorEngagement {
            return EventVendorEngagement::create([
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
                'vendor_id' => $vendor->id,
                'event_vendor_requirement_id' => $data['event_vendor_requirement_id'] ?? null,
                'event_vendor_proposal_id' => $proposal?->id,
                'vendor_service_id' => $service?->id,
                'vendor_service_package_id' => $package?->id,
                'coordinator_membership_id' => $data['coordinator_membership_id'] ?? null,
                'vendor_contact_id' => $data['vendor_contact_id'] ?? null,
                'title' => $data['title'],
                'status' => VendorEngagementStatus::Draft->value,
                'service_snapshot' => $service !== null ? [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'scope' => $service->scope,
                    'capacity' => $service->capacity,
                    'version' => $service->version,
                ] : null,
                'package_snapshot' => $package !== null ? [
                    'id' => $package->id,
                    'name' => $package->name,
                    'price_amount' => $package->price_amount,
                    'currency' => $package->currency,
                    'inclusions' => $package->inclusions,
                    'exclusions' => $package->exclusions,
                    'capacity' => $package->capacity,
                    'conditions' => $package->conditions,
                    'version' => $package->version,
                ] : null,
                'scope_snapshot' => $data['scope_snapshot'] ?? $this->buildScopeSnapshot($service, $package, $data),
                'agreed_amount' => $agreedAmount,
                'currency' => $data['currency'] ?? $package?->currency ?? 'IDR',
                'selection_reason' => $data['selection_reason'] ?? null,
                'selected_by' => $proposal !== null ? $actor->id : null,
                'selected_at' => $proposal !== null ? now() : null,
                'created_by' => $actor->id,
                'notes' => $data['notes'] ?? null,
            ]);
        });

        $this->audit->record(
            'vendor.engagement.created',
            "Engagement vendor dibuat: {$engagement->title}",
            $event,
            $actor,
            $engagement,
            ['vendor_id' => $vendor->id],
        );

        return $engagement;
    }

    public function update(Event $event, EventVendorEngagement $engagement, array $data, User $actor): EventVendorEngagement
    {
        $this->assertBelongs($event, $engagement);

        $commercialLocked = ! in_array($engagement->status, [
            VendorEngagementStatus::Draft,
            VendorEngagementStatus::Sourcing,
            VendorEngagementStatus::ProposalReceived,
            VendorEngagementStatus::Negotiation,
            VendorEngagementStatus::PendingApproval,
        ], true);

        if ($commercialLocked) {
            $changedCommercial = array_key_exists('agreed_amount', $data)
                && (string) $data['agreed_amount'] !== (string) $engagement->agreed_amount;

            $changedScope = array_key_exists('scope_snapshot', $data)
                && $data['scope_snapshot'] !== $engagement->scope_snapshot;

            if ($changedCommercial || $changedScope) {
                throw ValidationException::withMessages([
                    'agreed_amount' => 'Nilai atau lingkup yang telah disepakati hanya dapat diubah melalui permintaan perubahan yang disetujui.',
                ]);
            }
        }

        $fillable = [
            'title', 'coordinator_membership_id', 'vendor_contact_id',
            'notes', 'agreed_amount', 'currency', 'scope_snapshot',
        ];

        $updates = array_intersect_key($data, array_flip($fillable));

        if ($updates !== []) {
            $engagement->forceFill($updates)->save();
        }

        $this->audit->record(
            'vendor.engagement.updated',
            "Engagement vendor diperbarui: {$engagement->title}",
            $event,
            $actor,
            $engagement,
        );

        return $engagement->refresh();
    }

    public function transition(
        Event $event,
        EventVendorEngagement $engagement,
        VendorEngagementStatus $target,
        User $actor,
        array $context = [],
    ): EventVendorEngagement {
        $this->assertBelongs($event, $engagement);

        if (! $engagement->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => 'Perubahan status engagement tidak valid.',
            ]);
        }

        if ($target === VendorEngagementStatus::Confirmed) {
            $this->assertReadyForConfirmation($engagement, $actor);
        }

        if ($target === VendorEngagementStatus::Completed) {
            $blocking = $engagement->deliverables()
                ->whereIn('status', [
                    VendorDeliverableStatus::Submitted->value,
                    VendorDeliverableStatus::UnderReview->value,
                ])
                ->count();

            if ($blocking > 0) {
                throw ValidationException::withMessages([
                    'status' => 'Masih ada deliverable yang menunggu keputusan peninjauan.',
                ]);
            }
        }

        DB::transaction(function () use ($engagement, $target, $actor, $context): void {
            $attributes = ['status' => $target->value];

            if ($target === VendorEngagementStatus::Confirmed) {
                $attributes['confirmed_by'] = $actor->id;
                $attributes['confirmed_at'] = now();
            }

            if ($target === VendorEngagementStatus::Completed) {
                $attributes['completed_at'] = now();
            }

            if ($target === VendorEngagementStatus::Cancelled) {
                $attributes['cancelled_by'] = $actor->id;
                $attributes['cancelled_at'] = now();
                $attributes['cancel_reason'] = $context['reason'] ?? null;
            }

            $engagement->forceFill($attributes)->save();

            if ($target === VendorEngagementStatus::Confirmed
                && $engagement->revisions()->count() === 0) {
                $this->snapshotRevision($engagement, null, $actor, 'Konfirmasi engagement');
            }
        });

        $this->audit->recordCritical(
            'vendor.engagement.transitioned',
            "Status engagement vendor: {$engagement->title} → {$target->value}",
            $event,
            $actor,
            $engagement,
        );

        return $engagement->refresh();
    }

    public function cancel(
        Event $event,
        EventVendorEngagement $engagement,
        string $reason,
        User $actor,
    ): EventVendorEngagement {
        $this->assertBelongs($event, $engagement);

        if ($engagement->status->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => 'Engagement yang sudah selesai atau dibatalkan tidak dapat dibatalkan lagi.',
            ]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan pembatalan wajib diisi.',
            ]);
        }

        $engagement->forceFill([
            'status' => VendorEngagementStatus::Cancelled->value,
            'cancelled_by' => $actor->id,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ])->save();

        $this->audit->recordCritical(
            'vendor.engagement.cancelled',
            "Engagement vendor dibatalkan: {$engagement->title}",
            $event,
            $actor,
            $engagement,
            ['reason' => $reason],
        );

        return $engagement->refresh();
    }

    /**
     * @return array{blocking: array<int,string>, warnings: array<int,string>}
     */
    public function closureReadiness(EventVendorEngagement $engagement): array
    {
        $blocking = [];
        $warnings = [];

        $pendingChanges = $engagement->changeRequests()
            ->whereIn('status', [
                VendorChangeRequestStatus::Submitted->value,
                VendorChangeRequestStatus::UnderReview->value,
            ])->count();

        if ($pendingChanges > 0) {
            $blocking[] = "{$pendingChanges} permintaan perubahan masih menunggu keputusan.";
        }

        $openIssues = $engagement->issues()
            ->whereIn('status', [
                VendorIssueStatus::Open->value,
                VendorIssueStatus::Acknowledged->value,
                VendorIssueStatus::InProgress->value,
            ])->count();

        if ($openIssues > 0) {
            $blocking[] = "{$openIssues} masalah vendor masih terbuka.";
        }

        $awaitingDecision = $engagement->deliverables()
            ->whereIn('status', [
                VendorDeliverableStatus::Submitted->value,
                VendorDeliverableStatus::UnderReview->value,
            ])->count();

        if ($awaitingDecision > 0) {
            $blocking[] = "{$awaitingDecision} deliverable masih menunggu keputusan peninjauan.";
        }

        $unfinished = $engagement->deliverables()
            ->whereIn('status', [
                VendorDeliverableStatus::NotStarted->value,
                VendorDeliverableStatus::InProgress->value,
                VendorDeliverableStatus::Rejected->value,
                VendorDeliverableStatus::Overdue->value,
            ])->count();

        if ($unfinished > 0) {
            $warnings[] = "{$unfinished} deliverable belum diterima.";
        }

        $unapprovedDocuments = $engagement->documents()
            ->whereNotIn('review_status', ['approved', 'archived'])
            ->count();

        if ($unapprovedDocuments > 0) {
            $warnings[] = "{$unapprovedDocuments} dokumen belum disetujui.";
        }

        return ['blocking' => $blocking, 'warnings' => $warnings];
    }

    public function close(
        Event $event,
        EventVendorEngagement $engagement,
        array $data,
        User $actor,
    ): EventVendorEngagement {
        $this->assertBelongs($event, $engagement);

        if ($engagement->closed_at !== null) {
            throw ValidationException::withMessages([
                'engagement' => 'Engagement ini sudah ditutup.',
            ]);
        }

        $readiness = $this->closureReadiness($engagement);

        if ($readiness['blocking'] !== []) {
            throw ValidationException::withMessages([
                'closure' => implode(' ', $readiness['blocking']),
            ]);
        }

        DB::transaction(function () use ($engagement, $data, $actor, $readiness): void {
            $engagement->forceFill([
                'status' => VendorEngagementStatus::Completed->value,
                'completed_at' => $engagement->completed_at ?? now(),
                'closed_by' => $actor->id,
                'closed_at' => now(),
                'closure_notes' => $data['closure_notes'] ?? null,
                'closure_snapshot' => [
                    'closed_at' => now()->toIso8601String(),
                    'closed_by' => $actor->id,
                    'final_status' => VendorEngagementStatus::Completed->value,
                    'agreed_amount' => $engagement->agreed_amount,
                    'currency' => $engagement->currency,
                    'service' => $engagement->service_snapshot,
                    'package' => $engagement->package_snapshot,
                    'scope' => $engagement->scope_snapshot,
                    'warnings' => $readiness['warnings'],
                    'deliverables' => $engagement->deliverables()
                        ->get(['id', 'title', 'status'])
                        ->toArray(),
                ],
            ])->save();

            $this->snapshotRevision($engagement, null, $actor, 'Penutupan engagement');
        });

        $this->audit->recordCritical(
            'vendor.engagement.closed',
            "Engagement vendor ditutup: {$engagement->title}",
            $event,
            $actor,
            $engagement,
            ['warnings' => $readiness['warnings']],
        );

        return $engagement->refresh();
    }

    public function snapshotRevision(
        EventVendorEngagement $engagement,
        ?EventVendorChangeRequest $changeRequest,
        User $actor,
        ?string $reason = null,
    ): EventVendorEngagementRevision {
        return DB::transaction(function () use ($engagement, $changeRequest, $actor, $reason): EventVendorEngagementRevision {
            $next = (int) $engagement->revisions()->max('revision_number') + 1;

            return EventVendorEngagementRevision::create([
                'event_vendor_engagement_id' => $engagement->id,
                'event_vendor_change_request_id' => $changeRequest?->id,
                'revision_number' => $next,
                'snapshot' => [
                    'status' => $engagement->status->value,
                    'agreed_amount' => $engagement->agreed_amount,
                    'currency' => $engagement->currency,
                    'service' => $engagement->service_snapshot,
                    'package' => $engagement->package_snapshot,
                    'scope' => $engagement->scope_snapshot,
                    'vendor_contact_id' => $engagement->vendor_contact_id,
                    'coordinator_membership_id' => $engagement->coordinator_membership_id,
                ],
                'reason' => $reason,
                'created_by' => $actor->id,
            ]);
        });
    }

    protected function assertBelongs(Event $event, EventVendorEngagement $engagement): void
    {
        if ((int) $engagement->event_id !== (int) $event->id) {
            abort(404);
        }
    }

    protected function assertReadyForConfirmation(EventVendorEngagement $engagement, User $actor): void
    {
        $errors = [];

        if ($engagement->agreed_amount === null) {
            $errors['agreed_amount'] = 'Nilai kesepakatan wajib diisi sebelum konfirmasi.';
        }

        if (empty($engagement->scope_snapshot)) {
            $errors['scope_snapshot'] = 'Lingkup pekerjaan wajib ditetapkan sebelum konfirmasi.';
        }

        if ($engagement->coordinator_membership_id === null) {
            $errors['coordinator_membership_id'] = 'Koordinator internal wajib ditunjuk sebelum konfirmasi.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @return array<string,mixed>
     */
    protected function buildScopeSnapshot(?VendorService $service, ?VendorServicePackage $package, array $data): array
    {
        return [
            'description' => $data['scope_description'] ?? $service?->scope ?? $service?->description,
            'inclusions' => $data['inclusions'] ?? $package?->inclusions ?? [],
            'exclusions' => $data['exclusions'] ?? $package?->exclusions ?? [],
            'conditions' => $package?->conditions,
            'quantity' => $data['quantity'] ?? null,
            'execution_date' => $data['execution_date'] ?? null,
        ];
    }
}
