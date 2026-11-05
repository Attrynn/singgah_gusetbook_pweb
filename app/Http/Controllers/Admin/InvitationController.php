<?php

namespace App\Http\Controllers\Admin;

use App\Models\Event;
use App\Models\Guest;
use App\Models\Invitation;
use App\Services\InvitationService;
use App\Services\QrService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class InvitationController extends Controller
{
    public function __construct(
        protected InvitationService $invitations,
        protected QrService $qr,
    ) {
    }

    public function index(Request $request)
    {
        $query = Guest::query()->with(['event', 'invitation', 'category']);

        if ($eventId = $request->integer('event_id')) {
            $query->where('event_id', $eventId);
        }

        if ($search = $request->string('search')->trim()->value()) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($status = $request->string('status')->value()) {
            $query->whereHas('invitation', fn ($q) => $q->where('status', $status));
        }

        $guests = $query->orderBy('name')->paginate(20)->withQueryString();

        $stats = [
            'total' => Invitation::count(),
            'opened' => Invitation::whereNotNull('opened_at')->count(),
            'shared' => Invitation::whereIn('status', ['shared_manually', 'sent'])->count(),
            'not_sent' => Invitation::where('status', 'not_sent')->count(),
        ];

        return view('admin.invitations.index', [
            'guests' => $guests,
            'events' => Event::orderBy('event_date')->get(),
            'stats' => $stats,
            'filters' => $request->only(['event_id', 'search', 'status']),
            'statuses' => \App\Enums\InvitationStatus::cases(),
        ]);
    }

    public function show(Guest $guest)
    {
        $guest->load(['event', 'invitation.token', 'category', 'group']);
        $invitationUrl = $this->invitations->invitationUrl($guest);
        $whatsappUrl = $this->invitations->whatsappUrl($guest);
        $qrRaw = $this->invitations->rawToken($guest, \App\Enums\TokenType::Qr);
        $qrSvg = $qrRaw ? $this->qr->svg($qrRaw) : null;

        return view('admin.invitations.show', compact('guest', 'invitationUrl', 'whatsappUrl', 'qrSvg'));
    }

    public function preview(Guest $guest)
    {
        $guest->load('event');
        $token = $this->invitations->rawToken($guest);

        return view('guest.invitation', [
            'event' => $guest->event,
            'guest' => $guest,
            'token' => $token,
            'preview' => true,
        ]);
    }

    public function whatsapp(Guest $guest)
    {
        $url = $this->invitations->whatsappUrl($guest);

        if (! $url) {
            return back()->withErrors(['invitation' => 'Tautan undangan belum tersedia.']);
        }

        $this->invitations->markShared($guest);
        $guest->event->activities()->create([
            'guest_id' => $guest->id,
            'user_id' => request()->user()?->id,
            'type' => 'invitation.whatsapp_opened',
            'description' => "Tautan WhatsApp dibuka untuk {$guest->name}",
        ]);

        return redirect()->away($url);
    }

    public function shared(Guest $guest)
    {
        $this->invitations->markShared($guest);

        return back()->with('status', 'Status dibagikan manual dicatat untuk '.$guest->name.'.');
    }

    public function regenerate(Guest $guest)
    {
        $this->invitations->issueForGuest($guest);

        return back()->with('status', 'Tautan undangan dan QR pass dibuat ulang.');
    }
}
