<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StorePlaybookRequest;
use App\Http\Requests\Workspace\UpdatePlaybookRequest;
use App\Models\Organization;
use App\Models\Playbook;
use App\Services\PlaybookService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class WorkspacePlaybookController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly PlaybookService $playbooks,
    ) {}

    public function index(Organization $organization)
    {
        $this->authorize('managePlaybooks', $organization);

        return view('workspace.playbooks.index', [
            'organization' => $organization,
            'platformPlaybooks' => $this->playbooks->platformTemplates(),
            'organizationPlaybooks' => $this->playbooks->forOrganization($organization),
        ]);
    }

    public function show(Organization $organization, Playbook $playbook)
    {
        // Platform templates are readable to any authorized user; an
        // organization playbook must belong to the route organization.
        if (! $playbook->isPlatform() && (int) $playbook->organization_id !== (int) $organization->id) {
            abort(404);
        }

        $this->authorize('managePlaybooks', $organization);

        return view('workspace.playbooks.show', [
            'organization' => $organization,
            'playbook' => $playbook->load(['versions', 'latestVersion']),
            'versions' => $playbook->versions()->orderByDesc('version')->get(),
            'latestVersion' => $this->playbooks->latestVersion($playbook),
        ]);
    }

    public function store(StorePlaybookRequest $request, Organization $organization)
    {
        $this->authorize('managePlaybooks', $organization);

        $playbook = $this->playbooks->create($organization, $request->validated(), $request->user());

        return redirect()->route('workspace.playbooks.show', [$organization, $playbook])
            ->with('status', 'Playbook dibuat.');
    }

    public function update(UpdatePlaybookRequest $request, Organization $organization, Playbook $playbook)
    {
        if ($playbook->isPlatform() || (int) $playbook->organization_id !== (int) $organization->id) {
            abort(404);
        }

        $this->authorize('managePlaybooks', $organization);

        $this->playbooks->update($playbook, $request->validated(), $request->user());

        return redirect()->route('workspace.playbooks.show', [$organization, $playbook])
            ->with('status', 'Playbook diperbarui.');
    }

    public function duplicate(Request $request, Organization $organization, Playbook $playbook)
    {
        if (! $playbook->isPlatform() && (int) $playbook->organization_id !== (int) $organization->id) {
            abort(404);
        }

        $this->authorize('managePlaybooks', $organization);

        $copy = $this->playbooks->duplicate($playbook, $organization, $request->user());

        return redirect()->route('workspace.playbooks.show', [$organization, $copy])
            ->with('status', 'Playbook diduplikasi.');
    }

    public function deactivate(Organization $organization, Playbook $playbook)
    {
        if ($playbook->isPlatform() || (int) $playbook->organization_id !== (int) $organization->id) {
            abort(404);
        }

        $this->authorize('managePlaybooks', $organization);

        $this->playbooks->deactivate($playbook, request()->user());

        return back()->with('status', 'Playbook dinonaktifkan.');
    }
}
