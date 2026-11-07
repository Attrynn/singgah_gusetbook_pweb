<?php

namespace App\Http\Controllers\Owner;

use App\Http\Requests\PasswordRequest;
use App\Http\Requests\ProfileRequest;
use App\Services\AuditService;
use App\Services\EmailChangeService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function __construct(
        protected AuditService $audit,
        protected EmailChangeService $emails,
    ) {}

    public function edit(Request $request)
    {
        return view('owner.profile.edit', [
            'user' => $request->user(),
            'events' => $request->user()->events()->orderBy('event_date')->get(),
        ]);
    }

    public function update(ProfileRequest $request)
    {
        $user = $request->user();
        $data = $request->safe()->only(['name', 'phone']);
        $submittedEmail = (string) $request->input('email');

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $data['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($data);

        $status = 'Profil diperbarui.';

        if (strtolower(trim($submittedEmail)) !== strtolower((string) $user->email)) {
            $this->emails->request($user, $submittedEmail);
            $status = 'Profil diperbarui. Tautan verifikasi dikirim ke email baru; email login belum berubah.';
        }

        $this->audit->record('profile.updated', 'Profil owner diperbarui', null, $user);

        return back()->with('status', $status);
    }

    public function password(PasswordRequest $request)
    {
        $user = $request->user();
        $user->forceFill([
            'password' => Hash::make($request->password),
            'remember_token' => Str::random(60),
        ])->save();

        if (Auth::check()) {
            Auth::logoutOtherDevices($request->password);
        }
        $request->session()->regenerate();

        $this->audit->record('profile.password', 'Password owner diperbarui dan sesi lain dicabut', null, $user);

        return back()->with('status', 'Password berhasil diperbarui.');
    }
}
