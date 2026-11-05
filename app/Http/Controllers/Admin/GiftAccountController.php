<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\GiftAccountRequest;
use App\Models\Event;
use App\Models\GiftAccount;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

class GiftAccountController extends Controller
{
    public function __construct(protected AuditService $audit)
    {
    }

    public function index(Event $event)
    {
        $event->load(['giftAccounts']);

        return view('admin.events.gifts', [
            'event' => $event,
            'gifts' => $event->giftAccounts,
        ]);
    }

    public function store(GiftAccountRequest $request, Event $event)
    {
        $data = $request->validated();
        unset($data['qris']);

        if ($request->hasFile('qris')) {
            $data['qris_path'] = $request->file('qris')->store("events/{$event->id}/gift", 'public');
        }

        $gift = $event->giftAccounts()->create($data);
        $this->audit->record('gift.created', "Rekening hadiah ditambahkan: {$gift->bank_name}", $event, $request->user(), $gift);

        return back()->with('status', 'Rekening hadiah ditambahkan.');
    }

    public function update(GiftAccountRequest $request, GiftAccount $gift)
    {
        $data = $request->validated();
        unset($data['qris']);

        if ($request->hasFile('qris')) {
            if ($gift->qris_path) {
                Storage::disk('public')->delete($gift->qris_path);
            }
            $data['qris_path'] = $request->file('qris')->store("events/{$gift->event_id}/gift", 'public');
        }

        $gift->update($data);
        $this->audit->record('gift.updated', "Rekening hadiah diperbarui: {$gift->bank_name}", $gift->event, $request->user(), $gift);

        return back()->with('status', 'Rekening hadiah diperbarui.');
    }

    public function destroy(Request $request, GiftAccount $gift)
    {
        if ($gift->qris_path) {
            Storage::disk('public')->delete($gift->qris_path);
        }
        $this->audit->record('gift.deleted', "Rekening hadiah dihapus: {$gift->bank_name}", $gift->event, $request->user(), $gift);
        $gift->delete();

        return back()->with('status', 'Rekening hadiah dihapus.');
    }
}
