<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\WorkspaceRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    public function __construct(
        private readonly WorkspaceRegistrationService $registration
    ) {}

    public function show()
    {
        if (Auth::check()) {
            return redirect()->route('owner.dashboard');
        }

        return view('auth.register');
    }

    public function store(Request $request)
    {
        // Never accept platform_role, role, or organization_id from request
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc,dns', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'workspace_name' => ['required', 'string', 'max:255'],
        ]);

        $user = $this->registration->register($data);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('owner.dashboard')
            ->with('status', 'Workspace "'.$data['workspace_name'].'" berhasil dibuat. Selamat datang, '.$data['name'].'!');
    }
}
