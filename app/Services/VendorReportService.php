<?php

namespace App\Services;

use App\Enums\VendorChangeRequestStatus;
use App\Enums\VendorDeliverableStatus;
use App\Enums\VendorDocumentReviewStatus;
use App\Enums\VendorDocumentType;
use App\Enums\VendorIssueStatus;
use App\Enums\VendorLogisticsStatus;
use App\Enums\VendorProposalStatus;
use App\Enums\VendorStatus;
use App\Models\Event;
use App\Models\EventVendorChangeRequest;
use App\Models\EventVendorDeliverable;
use App\Models\EventVendorDocument;
use App\Models\EventVendorEngagement;
use App\Models\EventVendorIssue;
use App\Models\EventVendorLogisticsRequirement;
use App\Models\EventVendorPerformanceReview;
use App\Models\EventVendorProposal;
use App\Models\EventVendorScheduleReference;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;

/**
 * Event-scoped vendor hub metrics and report rows.
 *
 * Commercial values are only produced when the caller explicitly requests
 * them, so read-only views never leak agreed or quoted amounts.
 */
class VendorReportService
{
    public const TYPES = [
        'directory',
        'engagements',
        'services',
        'proposals',
        'agreements',
        'deliverables',
        'logistics',
        'issues',
        'performance',
    ];

    /**
     * @return array<string,string>
     */
    public static function types(): array
    {
        return [
            'directory' => 'Direktori Vendor',
            'engagements' => 'Engagement Vendor',
            'services' => 'Layanan Vendor',
            'proposals' => 'Proposal Vendor',
            'agreements' => 'Perjanjian Vendor',
            'deliverables' => 'Deliverable Vendor',
            'logistics' => 'Logistik Vendor',
            'issues' => 'Masalah Vendor',
            'performance' => 'Kinerja Vendor',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function hub(Event $event, bool $includeCommercial = true): array
    {
        $organizationId = (int) $event->organization_id;

        $vendorsTotal = Vendor::query()
            ->where('organization_id', $organizationId)
            ->count();

        $vendorsActive = Vendor::query()
            ->where('organization_id', $organizationId)
            ->where('status', VendorStatus::Active->value)
            ->whereNull('archived_at')
            ->count();

        $engagementCounts = EventVendorEngagement::query()
            ->where('event_id', $event->id)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count): int => (int) $count)
            ->all();

        $proposalPending = EventVendorProposal::query()
            ->where('event_id', $event->id)
            ->whereIn('status', [
                VendorProposalStatus::Submitted->value,
                VendorProposalStatus::UnderEvaluation->value,
            ])->count();

        $proposalReceived = EventVendorProposal::query()
            ->where('event_id', $event->id)
            ->whereNotIn('status', [
                VendorProposalStatus::Draft->value,
                VendorProposalStatus::Withdrawn->value,
            ])->count();

        $proposalSelected = EventVendorProposal::query()
            ->where('event_id', $event->id)
            ->where('status', VendorProposalStatus::Selected->value)
            ->count();

        $proposalRejected = EventVendorProposal::query()
            ->where('event_id', $event->id)
            ->where('status', VendorProposalStatus::Rejected->value)
            ->count();

        $documentsPending = EventVendorDocument::query()
            ->where('event_id', $event->id)
            ->where('review_status', VendorDocumentReviewStatus::UnderReview->value)
            ->count();

        $documentsApproved = EventVendorDocument::query()
            ->where('event_id', $event->id)
            ->where('review_status', VendorDocumentReviewStatus::Approved->value)
            ->count();

        $documentsExpired = EventVendorDocument::query()
            ->where('event_id', $event->id)
            ->where(function ($query): void {
                $query->where('review_status', VendorDocumentReviewStatus::Expired->value)
                    ->orWhere(function ($nested): void {
                        $nested->whereNotNull('expires_at')
                            ->where('expires_at', '<', now())
                            ->where('review_status', '!=', VendorDocumentReviewStatus::Archived->value);
                    });
            })
            ->count();

        $deliverableCounts = EventVendorDeliverable::query()
            ->where('event_id', $event->id)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count): int => (int) $count)
            ->all();

        $overdueDeliverables = EventVendorDeliverable::query()
            ->where('event_id', $event->id)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereIn('status', [
                VendorDeliverableStatus::NotStarted->value,
                VendorDeliverableStatus::InProgress->value,
                VendorDeliverableStatus::Submitted->value,
                VendorDeliverableStatus::UnderReview->value,
                VendorDeliverableStatus::Rejected->value,
                VendorDeliverableStatus::Overdue->value,
            ])->count();

        $upcomingArrivals = EventVendorScheduleReference::query()
            ->where('event_id', $event->id)
            ->whereNotNull('expected_arrival_at')
            ->where('expected_arrival_at', '>=', now())
            ->with(['engagement.vendor'])
            ->orderBy('expected_arrival_at')
            ->limit(10)
            ->get();

        $openLogistics = EventVendorLogisticsRequirement::query()
            ->where('event_id', $event->id)
            ->whereIn('status', [
                VendorLogisticsStatus::Requested->value,
                VendorLogisticsStatus::Acknowledged->value,
                VendorLogisticsStatus::InProgress->value,
            ])->count();

        $openIssueStatuses = [
            VendorIssueStatus::Open->value,
            VendorIssueStatus::Acknowledged->value,
            VendorIssueStatus::InProgress->value,
        ];

        $openIssues = EventVendorIssue::query()
            ->where('event_id', $event->id)
            ->whereIn('status', $openIssueStatuses)
            ->count();

        $issuesBySeverity = EventVendorIssue::query()
            ->where('event_id', $event->id)
            ->whereIn('status', $openIssueStatuses)
            ->selectRaw('severity, count(*) as aggregate')
            ->groupBy('severity')
            ->pluck('aggregate', 'severity')
            ->map(fn ($count): int => (int) $count)
            ->all();

        $pendingChangeRequests = EventVendorChangeRequest::query()
            ->where('event_id', $event->id)
            ->whereIn('status', [
                VendorChangeRequestStatus::Submitted->value,
                VendorChangeRequestStatus::UnderReview->value,
            ])->count();

        $averageRating = EventVendorPerformanceReview::query()
            ->where('event_id', $event->id)
            ->avg('overall_rating');

        $data = [
            'vendors' => [
                'total' => $vendorsTotal,
                'active' => $vendorsActive,
            ],
            'engagements' => [
                'total' => array_sum($engagementCounts),
                'by_status' => $engagementCounts,
            ],
            'proposals' => [
                'pending' => $proposalPending,
                'received' => $proposalReceived,
                'selected' => $proposalSelected,
                'rejected' => $proposalRejected,
            ],
            'documents' => [
                'pending_review' => $documentsPending,
                'approved' => $documentsApproved,
                'expired' => $documentsExpired,
            ],
            'deliverables' => [
                'by_status' => $deliverableCounts,
                'overdue' => $overdueDeliverables,
            ],
            'upcoming_arrivals' => $upcomingArrivals,
            'logistics' => [
                'open' => $openLogistics,
            ],
            'issues' => [
                'open' => $openIssues,
                'by_severity' => $issuesBySeverity,
            ],
            'change_requests' => [
                'pending' => $pendingChangeRequests,
            ],
            'performance' => [
                'average_rating' => $averageRating !== null ? round((float) $averageRating, 2) : null,
            ],
        ];

        if ($includeCommercial) {
            $data['commercial'] = [
                'agreed_by_currency' => $this->sumByCurrency(
                    EventVendorEngagement::query()->where('event_id', $event->id),
                    'agreed_amount',
                ),
                'quoted_by_currency' => $this->sumByCurrency(
                    EventVendorProposal::query()->where('event_id', $event->id),
                    'quoted_amount',
                ),
            ];
        }

        return $data;
    }

    /**
     * @return array{title:string,columns:array<int,string>,rows:array<int,array<int,mixed>>}
     */
    public function report(Event $event, string $type, bool $includeCommercial = true): array
    {
        return match ($type) {
            'directory' => $this->directoryReport($event),
            'engagements' => $this->engagementReport($event, $includeCommercial),
            'services' => $this->serviceReport($event),
            'proposals' => $this->proposalReport($event, $includeCommercial),
            'agreements' => $this->agreementReport($event),
            'deliverables' => $this->deliverableReport($event),
            'logistics' => $this->logisticsReport($event),
            'issues' => $this->issueReport($event),
            'performance' => $this->performanceReport($event),
            default => throw new \InvalidArgumentException("Unknown vendor report type: {$type}"),
        };
    }

    /**
     * @return iterable<int,array<int,mixed>>
     */
    public function exportRows(Event $event, string $type, bool $includeCommercial = true): iterable
    {
        $report = $this->report($event, $type, $includeCommercial);

        yield $report['columns'];

        foreach ($report['rows'] as $row) {
            yield $row;
        }
    }

    /**
     * @return array{title:string,columns:array<int,string>,rows:array<int,array<int,mixed>>}
     */
    protected function directoryReport(Event $event): array
    {
        $vendors = Vendor::query()
            ->where('organization_id', $event->organization_id)
            ->with('category')
            ->orderBy('name')
            ->get();

        return [
            'title' => 'Direktori Vendor',
            'columns' => ['Nama', 'Jenis', 'Status', 'Kategori', 'Email', 'Telepon'],
            'rows' => $vendors->map(fn (Vendor $vendor): array => [
                $vendor->name,
                $vendor->type->label(),
                $vendor->status->label(),
                $vendor->category?->name ?? '—',
                $vendor->email ?? '—',
                $vendor->phone ?? '—',
            ])->all(),
        ];
    }

    /**
     * @return array{title:string,columns:array<int,string>,rows:array<int,array<int,mixed>>}
     */
    protected function engagementReport(Event $event, bool $includeCommercial): array
    {
        $engagements = EventVendorEngagement::query()
            ->where('event_id', $event->id)
            ->with(['vendor', 'coordinator.user'])
            ->orderBy('title')
            ->get();

        $columns = ['Vendor', 'Engagement', 'Status', 'Koordinator'];

        if ($includeCommercial) {
            $columns[] = 'Nilai Kesepakatan';
            $columns[] = 'Mata Uang';
        }

        $rows = $engagements->map(function (EventVendorEngagement $engagement) use ($includeCommercial): array {
            $row = [
                $engagement->vendor?->name ?? '—',
                $engagement->title,
                $engagement->status->label(),
                $engagement->coordinator?->user?->name ?? '—',
            ];

            if ($includeCommercial) {
                $row[] = $engagement->agreed_amount !== null ? (float) $engagement->agreed_amount : null;
                $row[] = $engagement->currency ?? '—';
            }

            return $row;
        })->all();

        return [
            'title' => 'Engagement Vendor',
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{title:string,columns:array<int,string>,rows:array<int,array<int,mixed>>}
     */
    protected function serviceReport(Event $event): array
    {
        $engagements = EventVendorEngagement::query()
            ->where('event_id', $event->id)
            ->whereNotNull('service_snapshot')
            ->with('vendor')
            ->orderBy('title')
            ->get();

        return [
            'title' => 'Layanan Vendor',
            'columns' => ['Vendor', 'Engagement', 'Layanan', 'Paket', 'Status'],
            'rows' => $engagements->map(fn (EventVendorEngagement $engagement): array => [
                $engagement->vendor?->name ?? '—',
                $engagement->title,
                $engagement->service_snapshot['name'] ?? '—',
                $engagement->package_snapshot['name'] ?? '—',
                $engagement->status->label(),
            ])->all(),
        ];
    }

    /**
     * @return array{title:string,columns:array<int,string>,rows:array<int,array<int,mixed>>}
     */
    protected function proposalReport(Event $event, bool $includeCommercial): array
    {
        $proposals = EventVendorProposal::query()
            ->where('event_id', $event->id)
            ->with(['vendor', 'requirement'])
            ->orderBy('title')
            ->get();

        $columns = ['Proposal', 'Vendor', 'Kebutuhan', 'Status', 'Diajukan'];

        if ($includeCommercial) {
            $columns[] = 'Nilai Penawaran';
            $columns[] = 'Mata Uang';
        }

        $rows = $proposals->map(function (EventVendorProposal $proposal) use ($includeCommercial): array {
            $row = [
                $proposal->title,
                $proposal->vendor?->name ?? '—',
                $proposal->requirement?->title ?? '—',
                $proposal->status->label(),
                $proposal->submitted_at?->toDateTimeString() ?? '—',
            ];

            if ($includeCommercial) {
                $row[] = $proposal->quoted_amount !== null ? (float) $proposal->quoted_amount : null;
                $row[] = $proposal->currency ?? '—';
            }

            return $row;
        })->all();

        return [
            'title' => 'Proposal Vendor',
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{title:string,columns:array<int,string>,rows:array<int,array<int,mixed>>}
     */
    protected function agreementReport(Event $event): array
    {
        $documents = EventVendorDocument::query()
            ->where('event_id', $event->id)
            ->whereIn('type', [
                VendorDocumentType::Agreement->value,
                VendorDocumentType::Contract->value,
                VendorDocumentType::Amendment->value,
            ])
            ->with('engagement.vendor')
            ->orderBy('title')
            ->get();

        return [
            'title' => 'Perjanjian Vendor',
            'columns' => ['Dokumen', 'Vendor', 'Jenis', 'Status Tinjauan', 'Versi', 'Berlaku', 'Kedaluwarsa'],
            'rows' => $documents->map(fn (EventVendorDocument $document): array => [
                $document->title,
                $document->engagement?->vendor?->name ?? '—',
                $document->type->label(),
                $document->review_status->label(),
                $document->version,
                $document->effective_date?->toDateString() ?? '—',
                $document->expires_at?->toDateTimeString() ?? '—',
            ])->all(),
        ];
    }

    /**
     * @return array{title:string,columns:array<int,string>,rows:array<int,array<int,mixed>>}
     */
    protected function deliverableReport(Event $event): array
    {
        $deliverables = EventVendorDeliverable::query()
            ->where('event_id', $event->id)
            ->with('engagement.vendor')
            ->orderBy('due_at')
            ->get();

        return [
            'title' => 'Deliverable Vendor',
            'columns' => ['Deliverable', 'Vendor', 'Engagement', 'Status', 'Tenggat', 'Terlambat'],
            'rows' => $deliverables->map(fn (EventVendorDeliverable $deliverable): array => [
                $deliverable->title,
                $deliverable->engagement?->vendor?->name ?? '—',
                $deliverable->engagement?->title ?? '—',
                $deliverable->status->label(),
                $deliverable->due_at?->toDateTimeString() ?? '—',
                $deliverable->isOverdue() ? 'Ya' : 'Tidak',
            ])->all(),
        ];
    }

    /**
     * @return array{title:string,columns:array<int,string>,rows:array<int,array<int,mixed>>}
     */
    protected function logisticsReport(Event $event): array
    {
        $requirements = EventVendorLogisticsRequirement::query()
            ->where('event_id', $event->id)
            ->with('engagement.vendor')
            ->orderBy('due_at')
            ->get();

        return [
            'title' => 'Logistik Vendor',
            'columns' => ['Jenis', 'Deskripsi', 'Vendor', 'Tanggung Jawab', 'Status', 'Tenggat'],
            'rows' => $requirements->map(fn (EventVendorLogisticsRequirement $requirement): array => [
                $requirement->type->label(),
                $requirement->description,
                $requirement->engagement?->vendor?->name ?? '—',
                $requirement->responsibility->label(),
                $requirement->status->label(),
                $requirement->due_at?->toDateTimeString() ?? '—',
            ])->all(),
        ];
    }

    /**
     * @return array{title:string,columns:array<int,string>,rows:array<int,array<int,mixed>>}
     */
    protected function issueReport(Event $event): array
    {
        $issues = EventVendorIssue::query()
            ->where('event_id', $event->id)
            ->with(['engagement.vendor', 'reporter', 'assignee'])
            ->latest()
            ->get();

        return [
            'title' => 'Masalah Vendor',
            'columns' => ['Masalah', 'Vendor', 'Keparahan', 'Status', 'Pelapor', 'Penanggung Jawab', 'Dibuat'],
            'rows' => $issues->map(fn (EventVendorIssue $issue): array => [
                $issue->title,
                $issue->engagement?->vendor?->name ?? '—',
                $issue->severity->label(),
                $issue->status->label(),
                $issue->reporter?->name ?? '—',
                $issue->assignee?->name ?? '—',
                $issue->created_at?->toDateTimeString() ?? '—',
            ])->all(),
        ];
    }

    /**
     * @return array{title:string,columns:array<int,string>,rows:array<int,array<int,mixed>>}
     */
    protected function performanceReport(Event $event): array
    {
        $reviews = EventVendorPerformanceReview::query()
            ->where('event_id', $event->id)
            ->with(['vendor', 'engagement', 'reviewer'])
            ->latest('reviewed_at')
            ->get();

        return [
            'title' => 'Kinerja Vendor',
            'columns' => ['Vendor', 'Engagement', 'Nilai', 'Penilai', 'Dinilai', 'Komentar'],
            'rows' => $reviews->map(fn (EventVendorPerformanceReview $review): array => [
                $review->vendor?->name ?? '—',
                $review->engagement?->title ?? '—',
                $review->overall_rating,
                $review->reviewer?->name ?? '—',
                $review->reviewed_at?->toDateTimeString() ?? '—',
                $review->comments ?? '—',
            ])->all(),
        ];
    }

    /**
     * @return array<string,float>
     */
    protected function sumByCurrency(Builder $query, string $column): array
    {
        return $query
            ->whereNotNull($column)
            ->selectRaw("COALESCE(currency, 'IDR') as currency, SUM({$column}) as total")
            ->groupBy('currency')
            ->pluck('total', 'currency')
            ->map(fn ($total): float => (float) $total)
            ->all();
    }
}
