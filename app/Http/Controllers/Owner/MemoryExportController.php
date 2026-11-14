<?php

namespace App\Http\Controllers\Owner;

use App\Enums\MemoryExportStatus;
use App\Http\Controllers\Owner\Concerns\ResolvesOwnerEvent;
use App\Http\Requests\M9\MemoryExportRequest;
use App\Models\Event;
use App\Models\MemoryCollection;
use App\Models\MemoryExportJob;
use App\Services\AuditService;
use App\Services\MemoryExportService;
use App\Services\MemoryFeedService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemoryExportController extends Controller
{
    use ResolvesOwnerEvent;

    public function __construct(
        protected MemoryExportService $exports,
        protected MemoryFeedService $feed,
        protected AuditService $audit,
    ) {}

    public function index(Request $request): View
    {
        $event = $this->ownerEvent($request);

        return view('owner.memories.exports', $this->payload($event));
    }

    public function store(MemoryExportRequest $request): RedirectResponse
    {
        $event = $this->ownerEvent($request);

        $job = $this->exports->request($event, $request->user(), $request->validated());

        return redirect()
            ->route('owner.memories.export.show', $job)
            ->with('status', 'Ekspor diminta. Arsip sedang disiapkan; muat ulang halaman ini untuk melihat progresnya.');
    }

    public function show(Request $request, int $job): View
    {
        $event = $this->ownerEvent($request);
        $export = $this->memoryExportJob($event, $job);

        return view('owner.memories.exports', $this->payload($event, $export));
    }

    public function download(Request $request, int $job): StreamedResponse
    {
        $event = $this->ownerEvent($request);
        $export = $this->memoryExportJob($event, $job);

        abort_unless(
            $export->status === MemoryExportStatus::Completed
                && $export->fileExists()
                && ! $export->isExpired(),
            404,
        );

        $this->audit->record(
            'memory.export.downloaded',
            'Arsip ekspor memori diunduh oleh pemilik acara',
            $event,
            $request->user(),
            $export,
        );

        return Storage::disk($export->file_disk)->download(
            $export->file_path,
            'vowspace-kenangan-'.$event->slug.'.zip',
        );
    }

    /**
     * Shared view payload for the export index and the single-job detail page.
     *
     * @return array<string,mixed>
     */
    protected function payload(Event $event, ?MemoryExportJob $export = null): array
    {
        $jobs = MemoryExportJob::query()
            ->where('event_id', $event->id)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $collections = MemoryCollection::query()
            ->where('event_id', $event->id)
            ->whereNull('archived_at')
            ->orderBy('sort_order')
            ->get();

        return [
            'event' => $event,
            'jobs' => $jobs,
            'export' => $export,
            'collections' => $collections,
            'stats' => $this->feed->stats($event),
            'itemCap' => (int) config('memory.export_max_items', 2000),
        ];
    }

    protected function memoryExportJob(Event $event, int $id): MemoryExportJob
    {
        return MemoryExportJob::query()->where('event_id', $event->id)->findOrFail($id);
    }
}
