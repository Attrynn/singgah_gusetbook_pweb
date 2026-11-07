<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Concerns\ExportsCsv;
use App\Http\Controllers\Owner\Concerns\ResolvesOwnerEvent;
use App\Models\GuestbookMessage;
use App\Services\GuestbookService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WishController extends Controller
{
    use ExportsCsv, ResolvesOwnerEvent;

    public function __construct(protected GuestbookService $guestbook) {}

    public function index(Request $request)
    {
        $event = $this->ownerEvent($request);
        $query = $event->messages()->with('guest');

        if ($status = $request->string('status')->value()) {
            $query->where('status', $status);
        }

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(fn ($q) => $q->where('sender_name', 'like', "%{$search}%")
                ->orWhere('message', 'like', "%{$search}%"));
        }

        if ($date = $request->string('date')->value()) {
            $query->whereDate('created_at', $date);
        }

        $messages = $query->latest()->paginate(15)->withQueryString();

        $favoriteIds = $request->user()->favoriteMessages()
            ->where('event_id', $event->id)
            ->pluck('guestbook_messages.id')
            ->all();

        return view('owner.wishes.index', [
            'event' => $event,
            'messages' => $messages,
            'favoriteIds' => $favoriteIds,
            'filters' => $request->only(['search', 'date', 'status']),
            'stats' => [
                'total' => $event->messages()->count(),
                'approved' => $event->messages()->where('status', 'approved')->count(),
                'pending' => $event->messages()->where('status', 'pending')->count(),
                'favorites' => count($favoriteIds),
            ],
        ]);
    }

    public function show(Request $request, GuestbookMessage $message)
    {
        $event = $this->ownerEvent($request);
        abort_unless($message->event_id === $event->id, 403);

        $isFavorite = $request->user()->favoriteMessages()->whereKey($message->id)->exists();

        return view('owner.wishes.show', compact('event', 'message', 'isFavorite'));
    }

    public function favorites(Request $request)
    {
        $event = $this->ownerEvent($request);

        $messages = $request->user()->favoriteMessages()
            ->where('event_id', $event->id)
            ->latest('guestbook_favorites.created_at')
            ->paginate(15);

        return view('owner.wishes.favorites', compact('event', 'messages'));
    }

    public function favorite(Request $request, GuestbookMessage $message)
    {
        $event = $this->ownerEvent($request);
        abort_unless($message->event_id === $event->id, 403);
        $this->authorize('favorite', $message);

        $added = $this->guestbook->toggleFavorite($message, $request->user());

        return back()->with('status', $added ? 'Ucapan ditambahkan ke favorit.' : 'Ucapan dihapus dari favorit.');
    }

    public function download(Request $request)
    {
        $event = $this->ownerEvent($request);
        $query = $event->messages();

        if ($status = $request->string('status')->value()) {
            $query->where('status', $status);
        }

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(fn ($q) => $q->where('sender_name', 'like', "%{$search}%")
                ->orWhere('message', 'like', "%{$search}%"));
        }

        if ($date = $request->string('date')->value()) {
            $query->whereDate('created_at', $date);
        }

        $rows = function (callable $write) use ($query): void {
            $write(['Sender', 'Message', 'Visibility', 'Status', 'Created At']);

            $query->orderBy('created_at')->chunk(200, function ($messages) use ($write): void {
                foreach ($messages as $message) {
                    $write([
                        $message->sender_name,
                        $message->message,
                        $message->visibility->label(),
                        $message->status->label(),
                        $message->created_at?->format('Y-m-d H:i'),
                    ]);
                }
            });
        };

        return $this->csvResponse($rows, 'vowspace-wishes-'.$event->slug.'.csv');
    }
}
