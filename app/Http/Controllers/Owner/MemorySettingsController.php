<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Owner\Concerns\ResolvesOwnerEvent;
use App\Http\Requests\M9\MemorySettingsRequest;
use App\Models\EventMemorySettings;
use App\Services\MemorySettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class MemorySettingsController extends Controller
{
    use ResolvesOwnerEvent;

    public function __construct(protected MemorySettingsService $settings) {}

    public function edit(Request $request): View
    {
        $event = $this->ownerEvent($request);
        $configuration = EventMemorySettings::resolve($event);

        return view('owner.memories.settings', [
            'event' => $event,
            'settings' => $configuration,
            'shareTokenPrefix' => $configuration->share_token_prefix,
            'shareToken' => session('share_token'),
            'shareUrl' => session('share_url'),
        ]);
    }

    public function update(MemorySettingsRequest $request): RedirectResponse
    {
        $event = $this->ownerEvent($request);

        $this->settings->update($event, $request->validated(), $request->user());

        return back()->with('status', 'Pengaturan memori diperbarui.');
    }

    public function close(Request $request): RedirectResponse
    {
        $event = $this->ownerEvent($request);

        $this->settings->closeSubmissions($event, $request->user());

        return back()->with('status', 'Pengiriman kenangan ditutup.');
    }

    public function reopen(Request $request): RedirectResponse
    {
        $event = $this->ownerEvent($request);

        $this->settings->reopenSubmissions($event, $request->user());

        return back()->with('status', 'Pengiriman kenangan dibuka kembali.');
    }

    public function enableShare(Request $request): RedirectResponse
    {
        $event = $this->ownerEvent($request);

        $rawToken = $this->settings->enableSharing($event, $request->user());

        return redirect()
            ->route('owner.memories.settings.edit')
            ->with('share_token', $rawToken)
            ->with('share_url', url('/m/'.$rawToken));
    }

    public function revokeShare(Request $request): RedirectResponse
    {
        $event = $this->ownerEvent($request);

        $this->settings->revokeSharing($event, $request->user());

        return back()->with('status', 'Tautan berbagi memori dinonaktifkan.');
    }
}
