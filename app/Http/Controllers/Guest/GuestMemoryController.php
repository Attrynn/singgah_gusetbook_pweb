<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Guest\Concerns\ResolvesGuest;
use App\Http\Requests\M9\GuestMemoryMessageRequest;
use App\Http\Requests\M9\GuestMemoryUploadRequest;
use App\Models\EventMemorySettings;
use App\Models\GuestbookMessage;
use App\Models\MediaAsset;
use App\Services\MemoryContributionService;
use App\Services\MemorySettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class GuestMemoryController extends Controller
{
    use ResolvesGuest;

    public function __construct(
        protected MemoryContributionService $contribution,
        protected MemorySettingsService $settings,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($this->can($request, 'memory_contribute'), 403, 'Kenangan belum dibuka untuk acara ini.');

        $event = $this->event($request);
        $guest = $this->guest($request);
        $token = (string) $request->route('token');

        $settings = EventMemorySettings::resolve($event);
        $quota = $this->contribution->remainingQuota($event, $guest);

        $recent = MediaAsset::query()
            ->where('event_id', $event->id)
            ->where('guest_id', $guest->id)
            ->latest()
            ->limit(12)
            ->get(['id', 'status', 'hidden_at', 'thumbnail_path', 'path', 'caption', 'created_at']);

        $recentMessages = GuestbookMessage::query()
            ->where('event_id', $event->id)
            ->where('guest_id', $guest->id)
            ->latest()
            ->limit(6)
            ->get(['id', 'status', 'message', 'created_at']);

        return view('guest.memories.index', compact('event', 'guest', 'settings', 'quota', 'recent', 'recentMessages', 'token'));
    }

    public function store(Request $request, GuestMemoryUploadRequest $upload): RedirectResponse
    {
        abort_unless($this->can($request, 'memory_contribute'), 403, 'Kenangan belum dibuka untuk acara ini.');

        $event = $this->event($request);
        $guest = $this->guest($request);

        // Ensure the settings row exists so the contribution gate reads the
        // persisted defaults instead of an unsaved instance.
        $this->settings->for($event);

        $this->contribution->submitPhotos($event, $guest, $request->file('files', []), $upload->validated());

        $status = $this->settings->for($event)->auto_approve
            ? 'Terima kasih! Foto Anda sudah ditambahkan ke koleksi.'
            : 'Terima kasih! Foto Anda sudah terkirim dan akan diperiksa oleh mempelai.';

        return back()->with('status', $status);
    }

    public function storeMessage(Request $request, GuestMemoryMessageRequest $message): RedirectResponse
    {
        abort_unless($this->can($request, 'memory_contribute'), 403, 'Kenangan belum dibuka untuk acara ini.');

        $event = $this->event($request);
        $guest = $this->guest($request);

        // Ensure the settings row exists so the contribution gate reads the
        // persisted defaults instead of an unsaved instance.
        $this->settings->for($event);

        $data = $message->validated();
        $data['display_name'] = $message->input('display_name') ?: ($data['sender_name'] ?? null);

        $this->contribution->submitMessage($event, $guest, $data);

        $status = $this->settings->for($event)->auto_approve
            ? 'Terima kasih! Ucapan Anda sudah ditambahkan ke koleksi.'
            : 'Terima kasih! Ucapan Anda sudah terkirim dan akan diperiksa oleh mempelai.';

        return back()->with('status', $status);
    }
}
