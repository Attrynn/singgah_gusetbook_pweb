<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\UserRole;
use App\Enums\WorkspaceInvitationStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Services\WorkspaceInvitationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class WorkspaceInvitationController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly WorkspaceInvitationService $service) {}

    public function show(Request $request, string $token)
    {
        $invitation = WorkspaceInvitation::where('token_hash', hash('sha256', $token))
            ->with(['organization', 'invitedBy'])
            ->first();

        if (! $invitation) {
            abort(404);
        }

        $reason = null;

        if ($invitation->status !== WorkspaceInvitationStatus::Pending) {
            $state = 'unusable';
            $reason = $invitation->isExpired() ? 'kedaluwarsa' : $invitation->status->value;
        } elseif ($invitation->isExpired()) {
            $invitation->update(['status' => WorkspaceInvitationStatus::Expired]);
            $state = 'unusable';
            $reason = 'kedaluwarsa';
        } elseif (! $request->user()) {
            $state = 'login_required';
        } elseif (strtolower($request->user()->email) !== strtolower($invitation->email)) {
            $state = 'email_mismatch';
        } else {
            $state = 'ready';
        }

        return view('workspace.invitations.accept', [
            'invitation' => $invitation,
            'token' => $token,
            'state' => $state,
            'reason' => $reason,
        ]);
    }

    public function accept(Request $request, string $token)
    {
        $invitation = $this->service->acceptInvitation($token, $request->user());

        session(['active_organization_id' => $invitation->organization_id]);

        return redirect()->route('home')->with('status', 'Undangan berhasil diterima.');
    }

    public function register(Request $request, string $token)
    {
        $invitation = WorkspaceInvitation::where('token_hash', hash('sha256', $token))
            ->with('organization')
            ->first();

        if (! $invitation) {
            abort(404);
        }

        if (! $invitation->isUsable()) {
            abort(422, 'Undangan sudah tidak dapat digunakan.');
        }

        // Query the base table directly so soft-deleted accounts still block
        // reuse of their email address during invitation registration.
        if (DB::table('users')->where('email', strtolower($invitation->email))->exists()) {
            return redirect()->route('login')->withErrors([
                'email' => 'Akun dengan email ini sudah ada. Silakan masuk.',
            ]);
        }

        // The email is always taken from the invitation, never from the request.
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($invitation->email),
            'password' => Hash::make($data['password']),
            'role' => UserRole::Owner,
            'is_active' => true,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        $this->service->acceptInvitation($token, $user);

        session(['active_organization_id' => $invitation->organization_id]);

        return redirect()->route('home')->with('status', 'Akun dibuat dan undangan diterima.');
    }
}
