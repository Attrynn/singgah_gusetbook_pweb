<?php

namespace App\Http\Controllers\Owner;

use App\Http\Requests\PasswordRequest;
use App\Http\Requests\ProfileRequest;
use App\Models\Event;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function __construct(protected AuditService $audit)
    {
    }

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
        $data = $request->safe()->only(['name', 'email', 'phone']);

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $data['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($data);
        $this->audit->record('profile.updated', 'Profil owner diperbarui', null, $user);

        return back()->with('status', 'Profil diperbarui.');
    }

    public function password(PasswordRequest $request)
    {
        $request->user()->update(['password' => Hash::make($request->password)]);
        $this->audit->record('profile.password', 'Password owner diperbarui', null, $request->user());

        return back()->with('status', 'Password berhasil diperbarui.');
    }
}
