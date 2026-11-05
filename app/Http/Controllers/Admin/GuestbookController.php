<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MessageStatus;
use App\Enums\MessageVisibility;
use App\Http\Controllers\Concerns\ExportsCsv;
use App\Models\Event;
use App\Models\GuestbookMessage;
use App\Services\GuestbookService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class GuestbookController extends Controller
{
    use ExportsCsv;

    public function __construct(protected GuestbookService $guestbook)
    {
    }

    public function index(Request $request)
    {
        $query = GuestbookMessage::with(['guest', 'event', 'reviewer']);

        if ($eventId = $request->integer('event_id')) {
            $query->where('event_id', $eventId);
        }

        if ($status = $request->string('status')->value()) {
            $query->where('status', $status);
        }

        if ($visibility = $request->string('visibility')->value()) {
            $query->where('visibility', $visibility);
        }

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(fn ($q) => $q->where('sender_name', 'like', "%{$search}%")
                ->orWhere('message', 'like', "%{$search}%"));
        }

        $messages = $query->latest()->paginate(20)->withQueryString();

        $base = GuestbookMessage::query()->when($request->integer('event_id'), fn ($q, $id) => $q->where('event_id', $id));

        $stats = [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', MessageStatus::Pending->value)->count(),
            'approved' => (clone $base)->where('status', MessageStatus::Approved->value)->count(),
            'private' => (clone $base)->where('visibility', MessageVisibility::Private->value)->count(),
        ];

        return view('admin.guestbook.index', [
            'messages' => $messages,
            'events' => Event::orderBy('event_date')->get(),
            'stats' => $stats,
            'statuses' => MessageStatus::cases(),
            'filters' => $request->only(['event_id', 'status', 'visibility', 'search']),
        ]);
    }

    public function moderate(Request $request, GuestbookMessage $message)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject', 'hide', 'pending'])],
        ]);

        $this->guestbook->moderate($message, $data['action'], $request->user());

        return back()->with('status', 'Ucapan '.$message->sender_name.' diperbarui.');
    }

    public function pin(Request $request, GuestbookMessage $message)
    {
        $this->guestbook->togglePin($message, $request->user());

        return back()->with('status', 'Status pin ucapan diperbarui.');
    }

    public function destroy(Request $request, GuestbookMessage $message)
    {
        $this->guestbook->delete($message, $request->user());

        return back()->with('status', 'Ucapan dihapus.');
    }

    public function export(Request $request)
    {
        $query = GuestbookMessage::with(['guest', 'event'])
            ->when($request->integer('event_id'), fn ($q, $id) => $q->where('event_id', $id))
            ->when($request->string('status')->value(), fn ($q, $status) => $q->where('status', $status));

        $rows = [['Event', 'Sender', 'Message', 'Emoji', 'Visibility', 'Status', 'Pinned', 'Created At']];

        foreach ($query->get() as $message) {
            $rows[] = [
                $message->event?->name,
                $message->sender_name,
                $message->message,
                $message->emoji,
                $message->visibility->label(),
                $message->status->label(),
                $message->is_pinned ? 'Yes' : 'No',
                $message->created_at?->format('Y-m-d H:i'),
            ];
        }

        return $this->csvResponse($rows, 'vowspace-guestbook.csv');
    }
}
