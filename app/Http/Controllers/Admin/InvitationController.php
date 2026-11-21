<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvitationStatus;
use App\Enums\TokenType;
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
    ) {}

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
            'events' => Event::orderByRaw("CASE WHEN status IN ('live', 'published') THEN 0 ELSE 1 END, event_date DESC")->get(),
            'stats' => $stats,
            'filters' => $request->only(['event_id', 'search', 'status']),
            'statuses' => InvitationStatus::cases(),
        ]);
    }

    public function show(Guest $guest)
    {
        $guest->load(['event', 'invitation.token', 'category', 'group']);
        $invitationUrl = $this->invitations->invitationUrl($guest);
        $whatsappUrl = $this->invitations->whatsappUrl($guest);
        $qrRaw = $this->invitations->rawToken($guest, TokenType::Qr);
        $qrSvg = $qrRaw ? $this->qr->svg($qrRaw) : null;

        // A QR pass is bounded by the event end plus the grace period, so a
        // finished event can never mint a usable pass. Surface that instead of
        // offering a "generate" button that can only produce an expired token.
        $qrExpiresAt = $this->invitations->qrExpiresAt($guest);
        $qrBlocked = $qrExpiresAt !== null && $qrExpiresAt->isPast();

        return view('admin.invitations.show', compact('guest', 'invitationUrl', 'whatsappUrl', 'qrSvg', 'qrBlocked'));
    }

    public function preview(Guest $guest)
    {
        $guest->load('event');
        $token = $this->invitations->rawToken($guest);
        $qrRaw = $this->invitations->rawToken($guest, TokenType::Qr);
        $qrSvg = $qrRaw ? $this->qr->svg($qrRaw, 220) : null;

        return view('guest.invitation', [
            'event' => $guest->event,
            'guest' => $guest,
            'token' => $token,
            'preview' => true,
            'qrSvg' => $qrSvg,
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
        $qrExpiresAt = $this->invitations->qrExpiresAt($guest);

        // Refuse before touching existing tokens: minting an already-expired
        // QR would silently invalidate the current invitation link while
        // reporting success and still showing no QR.
        if ($qrExpiresAt !== null && $qrExpiresAt->isPast()) {
            return back()->withErrors([
                'qr' => 'QR pass tidak dapat diterbitkan karena acara sudah berakhir lebih dari masa tenggang.',
            ]);
        }

        $this->invitations->issueForGuest($guest);

        return back()->with('status', 'Tautan undangan dan QR pass dibuat ulang.');
    }
}
