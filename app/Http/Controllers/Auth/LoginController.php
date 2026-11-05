<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __construct(protected AuditService $audit)
    {
    }

    public function show()
    {
        if (Auth::check()) {
            return $this->redirectFor(Auth::user());
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'login:'.strtolower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Terlalu banyak percobaan login. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'email' => 'Email atau password tidak sesuai.',
            ]);
        }

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Akun Anda dinonaktifkan. Hubungi administrator.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();
        $this->audit->record('auth.login', "{$user->name} masuk ke sistem", null, $user);

        return $this->redirectFor($user)->with('status', 'Selamat datang kembali, '.$user->name.'.');
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            $this->audit->record('auth.logout', "{$user->name} keluar dari sistem", null, $user);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Anda telah keluar.');
    }

    protected function redirectFor(User $user)
    {
        return redirect()->route($user->isSuperadmin() ? 'admin.dashboard' : 'owner.dashboard');
    }
}
