<?php

namespace App\Http\Middleware;

use App\Enums\GuestStatus;
use App\Services\InvitationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveGuestToken
{
    public function __construct(protected InvitationService $invitations) {}

    public function handle(Request $request, Closure $next): Response
    {
        $raw = (string) $request->route('token');
        $token = $this->invitations->resolveInvitation($raw);

        if (! $token) {
            return response()->view('guest.invalid', [
                'reason' => 'Link undangan tidak valid, sudah kedaluwarsa, atau telah dicabut.',
            ], 404);
        }

        $guest = $token->guest;

        if ($guest->status === GuestStatus::Blocked) {
            return response()->view('guest.invalid', [
                'reason' => 'Akses undangan untuk tamu ini telah diblokir.',
            ], 403);
        }

        if ($guest->status === GuestStatus::Inactive) {
            return response()->view('guest.invalid', [
                'reason' => 'Undangan untuk tamu ini sedang dinonaktifkan.',
            ], 403);
        }

        $request->attributes->set('guestToken', $token);
        $request->attributes->set('guest', $guest);
        $request->attributes->set('event', $token->event);

        view()->share('token', $raw);
        view()->share('currentGuest', $guest);
        view()->share('currentEvent', $token->event);

        return $next($request);
    }
}
