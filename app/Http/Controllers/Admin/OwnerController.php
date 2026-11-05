<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Requests\OwnerRequest;
use App\Models\Event;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OwnerController extends Controller
{
    public function __construct(protected AuditService $audit)
    {
    }

    public function index(Request $request)
    {
        $query = User::owners()->withCount('events');

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        return view('admin.owners.index', [
            'owners' => $query->orderBy('name')->paginate(15)->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }

    public function create()
    {
        return view('admin.owners.create', [
            'owner' => new User(['role' => UserRole::Owner, 'is_active' => true]),
            'events' => Event::orderBy('event_date')->get(),
        ]);
    }

    public function store(OwnerRequest $request)
    {
        $owner = DB::transaction(function () use ($request) {
            $owner = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'role' => UserRole::Owner,
                'is_active' => $request->boolean('is_active'),
            ]);

            $this->syncEvents($owner, $request);

            return $owner;
        });

        $this->audit->record('owner.created', "Akun owner dibuat: {$owner->name}", null, $request->user(), $owner);

        return redirect()->route('admin.owners.index')->with('status', 'Akun owner berhasil dibuat.');
    }

    public function edit(User $owner)
    {
        abort_unless($owner->isOwner(), 404);
        $owner->load('events');

        return view('admin.owners.edit', [
            'owner' => $owner,
            'events' => Event::orderBy('event_date')->get(),
        ]);
    }

    public function update(OwnerRequest $request, User $owner)
    {
        abort_unless($owner->isOwner(), 404);

        DB::transaction(function () use ($request, $owner) {
            $owner->update([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'is_active' => $request->boolean('is_active'),
            ]);

            if ($request->filled('password')) {
                $owner->update(['password' => Hash::make($request->password)]);
            }

            $this->syncEvents($owner, $request);
        });

        $this->audit->record('owner.updated', "Akun owner diperbarui: {$owner->name}", null, $request->user(), $owner);

        return redirect()->route('admin.owners.index')->with('status', 'Akun owner diperbarui.');
    }

    public function destroy(User $owner)
    {
        abort_unless($owner->isOwner(), 404);
        $this->audit->record('owner.deleted', "Akun owner dihapus: {$owner->name}", null, request()->user(), $owner);
        $owner->delete();

        return back()->with('status', 'Akun owner dihapus.');
    }

    protected function syncEvents(User $owner, OwnerRequest $request): void
    {
        $ids = collect($request->input('event_ids', []))->filter()->unique();
        $labels = $request->input('role_labels', []);

        $sync = $ids->mapWithKeys(fn ($id) => [
            $id => ['role_label' => $labels[$id] ?? 'partner'],
        ])->all();

        $owner->events()->sync($sync);
    }
}
