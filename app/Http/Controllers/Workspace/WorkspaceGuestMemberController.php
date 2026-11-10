<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\TokenType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\AddGuestMemberRequest;
use App\Http\Requests\Workspace\MoveGuestMemberRequest;
use App\Http\Requests\Workspace\UpdateGuestPartyMembershipRequest;
use App\Models\Event;
use App\Models\Guest;
use App\Models\GuestPartyMembership;
use App\Models\GuestPass;
use App\Models\Organization;
use App\Services\GuestPartyService;
use App\Services\GuestPassService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class WorkspaceGuestMemberController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly GuestPartyService $parties,
        private readonly GuestPassService $passService,
    ) {}

    /**
     * Add an identified individual to a party.
     */
    public function store(AddGuestMemberRequest $request, Organization $organization, Event $event, Guest $guest)
    {
        $this->assertEventInOrg($organization, $event);
        $this->assertGuestInEvent($event, $guest);
        $this->authorize('manage', $guest);

        $member = $this->parties->addMember($guest, $request->validated(), $request->user());

        return back()->with('status', "Individu '{$member->name}' berhasil ditambahkan ke party.");
    }

    /**
     * Update an individual member's details.
     */
    public function update(UpdateGuestPartyMembershipRequest $request, Organization $organization, Event $event, Guest $guest, GuestPartyMembership $member)
    {
        $this->assertEventInOrg($organization, $event);
        $this->assertGuestInEvent($event, $guest);
        abort_unless((int) $member->guest_id === (int) $guest->id, 404);
        $this->authorize('update', $member);

        $this->parties->updateMember($member, $request->validated(), $request->user());

        return back()->with('status', "Data individu '{$member->name}' berhasil diperbarui.");
    }

    /**
     * Remove an individual from a party (soft delete + pass revocation).
     */
    public function destroy(Organization $organization, Event $event, Guest $guest, GuestPartyMembership $member)
    {
        $this->assertEventInOrg($organization, $event);
        $this->assertGuestInEvent($event, $guest);
        abort_unless((int) $member->guest_id === (int) $guest->id, 404);
        $this->authorize('delete', $member);

        $name = $member->name;
        $this->parties->removeMember($member, auth()->user());

        return back()->with('status', "Individu '{$name}' berhasil dihapus dari party.");
    }

    /**
     * Move an individual to another party within the same event.
     */
    public function move(MoveGuestMemberRequest $request, Organization $organization, Event $event, Guest $guest, GuestPartyMembership $member)
    {
        $this->assertEventInOrg($organization, $event);
        $this->assertGuestInEvent($event, $guest);
        abort_unless((int) $member->guest_id === (int) $guest->id, 404);
        $this->authorize('update', $member);

        $targetParty = Guest::findOrFail($request->validated('target_party_id'));
        $this->assertGuestInEvent($event, $targetParty);

        $this->parties->moveMember($member, $targetParty, $request->user());

        return back()->with('status', "Individu '{$member->name}' berhasil dipindahkan ke party '{$targetParty->name}'.");
    }

    /**
     * Issue a fresh admission pass for an individual member.
     */
    public function issuePass(Request $request, Organization $organization, Event $event, Guest $guest, GuestPartyMembership $member)
    {
        $this->assertEventInOrg($organization, $event);
        $this->assertGuestInEvent($event, $guest);
        abort_unless((int) $member->guest_id === (int) $guest->id, 404);
        $this->authorize('issuePass', $member);

        $type = TokenType::tryFrom((string) $request->input('type', 'invitation')) ?? TokenType::Invitation;
        $pass = $this->passService->issue($member, $type, $request->user());

        return back()->with('status', "Guest pass ({$pass->type->value}) berhasil diterbitkan untuk '{$member->name}'.");
    }

    /**
     * Revoke a member's active guest pass.
     */
    public function revokePass(Request $request, Organization $organization, Event $event, Guest $guest, GuestPartyMembership $member, GuestPass $pass)
    {
        $this->assertEventInOrg($organization, $event);
        $this->assertGuestInEvent($event, $guest);
        abort_unless((int) $pass->guest_party_membership_id === (int) $member->id, 404);
        abort_unless((int) $pass->event_id === (int) $event->id, 404);
        $this->authorize('revokePass', $member);

        $this->passService->revoke($pass, $request->user(), $request->input('reason'));

        return back()->with('status', "Guest pass untuk '{$member->name}' berhasil dicabut.");
    }

    protected function assertEventInOrg(Organization $organization, Event $event): void
    {
        abort_unless((int) $event->organization_id === (int) $organization->id, 404);
    }

    protected function assertGuestInEvent(Event $event, Guest $guest): void
    {
        abort_unless((int) $guest->event_id === (int) $event->id, 404);
    }
}
