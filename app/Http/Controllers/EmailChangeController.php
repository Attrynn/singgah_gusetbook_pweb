<?php

namespace App\Http\Controllers;

use App\Services\EmailChangeService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

/**
 * Two-step email verification.
 *
 * GET only renders the confirmation screen: link scanners and preview bots
 * must never be able to change the login identity. The identity change
 * happens exclusively in the CSRF-protected POST.
 */
class EmailChangeController extends Controller
{
    public function __construct(protected EmailChangeService $emails) {}

    public function show(Request $request, string $token)
    {
        $user = $this->emails->preview($token);

        if (! $user) {
            return redirect()->route('login')->withErrors([
                'token' => 'Tautan verifikasi tidak valid atau sudah kedaluwarsa.',
            ]);
        }

        return view('auth.email-confirm', [
            'token' => $token,
            'pendingEmail' => $user->pending_email,
            'currentEmail' => $user->email,
            'expiresAt' => $user->email_change_expires_at,
        ]);
    }

    public function store(Request $request, string $token)
    {
        try {
            $user = $this->emails->confirm($token);
        } catch (ValidationException $exception) {
            return redirect()->route('login')->withErrors($exception->errors());
        }

        if ($request->user()?->is($user)) {
            $request->session()->regenerate();
            $request->session()->regenerateToken();

            $target = $user->isSuperadmin()
                ? route('admin.profile.edit')
                : route('owner.profile.edit');

            return redirect($target)->with('status', 'Email berhasil diverifikasi dan diperbarui.');
        }

        return redirect()->route('login')->with(
            'status',
            'Email berhasil diverifikasi. Silakan login menggunakan email baru Anda.'
        );
    }
}
