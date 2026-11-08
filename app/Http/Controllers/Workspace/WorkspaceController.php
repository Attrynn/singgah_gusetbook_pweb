<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Services\WorkspaceRegistrationService;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function __construct(
        private readonly WorkspaceRegistrationService $registration
    ) {}

    public function create()
    {
        return view('workspace.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'workspace_name' => ['required', 'string', 'max:255'],
        ]);

        $org = $this->registration->createWorkspaceForExistingUser(
            $request->user(),
            $data
        );

        return redirect()->route('owner.dashboard')
            ->with('status', 'Workspace "'.$org->name.'" berhasil dibuat.');
    }
}
