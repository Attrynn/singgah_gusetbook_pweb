<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Enums\MediaStatus;
use App\Enums\MessageStatus;
use App\Enums\MessageVisibility;
use App\Models\Event;
use App\Models\GuestbookMessage;
use App\Models\MediaAsset;
use App\Models\MemoryCollection;
use App\Services\MemorySettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Public memory share page.
 *
 * Resolves a revocable share token and renders only contributions that are
 * approved, not hidden, not trashed, and covered by a consent that allows both
 * event and public display. Nothing internal (statuses, reviewer identity,
 * moderation notes, guest contact data, the raw token) ever reaches the view.
 */
class MemoryShareController extends Controller
{
    public function __construct(protected MemorySettingsService $settings) {}

    public function show(Request $request, string $token): View
    {
        $settings = $this->settings->resolveShareToken($token);
        abort_unless($settings, 404);

        $event = $settings->event;
        abort_if($event === null || $event->trashed(), 404);
        abort_if($event->status === EventStatus::Archived, 404);

        $photos = $this->approvedPhotos($event)
            ->latest('created_at')
            ->paginate(config('memory.gallery_page_size', 24));

        $messages = $this->approvedMessages($event)
            ->latest('created_at')
            ->paginate(12, ['*'], 'messages_page');

        $collections = MemoryCollection::where('event_id', $event->id)
            ->where('is_visible_to_guests', true)
            ->whereNull('archived_at')
            ->orderBy('sort_order')
            ->get();

        $photoCount = $this->approvedPhotos($event)->count();
        $messageCount = $this->approvedMessages($event)->count();

        return view('memory.public', compact('event', 'settings', 'photos', 'messages', 'collections', 'token', 'photoCount', 'messageCount'));
    }

    /**
     * Approved, unhidden, untrashed assets whose consent allows public display.
     */
    protected function approvedPhotos(Event $event): Builder
    {
        return MediaAsset::query()
            ->where('event_id', $event->id)
            ->where('status', MediaStatus::Approved)
            ->whereNull('hidden_at')
            ->whereHas('consent', fn ($q) => $q->where('allow_event_display', true)->where('allow_public_display', true))
            ->with('consent');
    }

    /**
     * Approved public messages whose consent allows public display.
     */
    protected function approvedMessages(Event $event): Builder
    {
        return GuestbookMessage::query()
            ->where('event_id', $event->id)
            ->where('status', MessageStatus::Approved)
            ->where('visibility', MessageVisibility::Public)
            ->whereHas('consent', fn ($q) => $q->where('allow_event_display', true)->where('allow_public_display', true));
    }
}
