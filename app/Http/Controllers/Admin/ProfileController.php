<?php

namespace App\Http\Controllers\Admin;

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
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function __construct(
        protected AuditService $audit,
        protected EmailChangeService $emails,
    ) {}

    public function edit(Request $request)
    {
        return view('admin.profile.edit', ['user' => $request->user()]);
    }

    public function update(ProfileRequest $request)
    {
        $user = $request->user();
        $data = $request->safe()->only(['name', 'phone']);
        $submittedEmail = (string) $request->input('email');
        $changesEmail = strtolower(trim($submittedEmail)) !== strtolower((string) $user->email);
        $previousAvatar = $user->avatar_path;
        $storedAvatar = null;

        if ($request->hasFile('avatar')) {
            $storedAvatar = $request->file('avatar')->store('avatars', 'public');

            if (! $storedAvatar) {
                throw ValidationException::withMessages(['avatar' => 'Foto profil gagal disimpan. Silakan coba lagi.']);
            }

            $data['avatar_path'] = $storedAvatar;
        }

        try {
            // Name/phone/avatar are independent of the login identity: they are
            // saved even when the email-change request is rejected, and the
            // response never claims the email changed.
            $user->update($data);

            $status = 'Profil diperbarui.';
            $level = 'status';

            if ($changesEmail) {
                $result = $this->emails->request($user, $submittedEmail, (string) $request->input('current_password'));

                $status = $result['delivered']
                    ? 'Profil diperbarui. Tautan verifikasi dikirim ke email baru; email login belum berubah.'
                    : 'Profil diperbarui, tetapi email verifikasi gagal dikirim. Email login belum berubah; gunakan kirim ulang tautan.';
                $level = $result['delivered'] ? 'status' : 'warning';
            }
        } catch (\Throwable $e) {
            if ($storedAvatar) {
                Storage::disk('public')->delete($storedAvatar);
            }

            throw $e;
        }

        if ($storedAvatar && $previousAvatar) {
            Storage::disk('public')->delete($previousAvatar);
        }

        $this->audit->record('profile.updated', 'Profil diperbarui', null, $user);

        return back()->with($level, $status);
    }

    public function resendEmail(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        $result = $this->emails->resend($request->user(), $data['current_password']);

        return back()->with(
            $result['delivered'] ? 'status' : 'warning',
            $result['delivered']
                ? 'Tautan verifikasi baru dikirim ke email yang menunggu verifikasi.'
                : 'Tautan verifikasi gagal dikirim. Coba lagi atau hubungi administrator.'
        );
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

        $this->audit->record('profile.password', 'Password diperbarui dan sesi lain dicabut', null, $user);

        return back()->with('status', 'Password berhasil diperbarui.');
    }
}
