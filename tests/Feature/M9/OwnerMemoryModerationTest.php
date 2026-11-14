<?php

namespace Tests\Feature\M9;

use App\Enums\MediaStatus;
use App\Enums\MessageStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsWedding;
use Tests\Feature\M9\Concerns\BuildsMemory;
use Tests\TestCase;

class OwnerMemoryModerationTest extends TestCase
{
    use BuildsMemory, BuildsWedding, RefreshDatabase;

    public function test_couple_can_approve_a_pending_photo(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $this->actingAs($owner);

        $asset = $this->pendingPhoto($event);
        Storage::disk('local')->put($asset->path, 'test-image-bytes');

        $this->post(route('owner.memories.moderate', $asset), ['action' => 'approve'])
            ->assertRedirect();

        $this->assertSame(MediaStatus::Approved, $asset->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'memory.media_asset.approve',
            'target_id' => $asset->id,
        ]);
    }

    public function test_couple_can_reject_a_pending_photo(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $this->actingAs($owner);

        $asset = $this->pendingPhoto($event);

        $this->post(route('owner.memories.moderate', $asset), ['action' => 'reject'])
            ->assertRedirect();

        $this->assertSame(MediaStatus::Rejected, $asset->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'memory.media_asset.reject',
            'target_id' => $asset->id,
        ]);
    }

    public function test_hiding_and_unhiding_an_approved_photo_only_touches_hidden_at(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $this->actingAs($owner);

        $asset = $this->approvedPhoto($event);
        Storage::disk('local')->put($asset->path, 'test-image-bytes');

        $this->post(route('owner.memories.moderate', $asset), ['action' => 'hide'])
            ->assertRedirect();

        $hidden = $asset->fresh();

        $this->assertNotNull($hidden->hidden_at);
        $this->assertSame(MediaStatus::Approved, $hidden->status);

        $this->post(route('owner.memories.moderate', $asset), ['action' => 'unhide'])
            ->assertRedirect();

        $restored = $asset->fresh();

        $this->assertNull($restored->hidden_at);
        $this->assertSame(MediaStatus::Approved, $restored->status);
    }

    public function test_reopening_a_rejected_photo_returns_it_to_pending(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $this->actingAs($owner);

        $asset = $this->photo($event, null, ['status' => MediaStatus::Rejected]);

        $this->post(route('owner.memories.moderate', $asset), ['action' => 'reopen'])
            ->assertRedirect();

        $this->assertSame(MediaStatus::Pending, $asset->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'memory.media_asset.reopen',
            'target_id' => $asset->id,
        ]);
    }

    public function test_couple_can_moderate_a_guestbook_message(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $this->actingAs($owner);

        $message = $this->pendingMessage($event);

        $this->post(route('owner.wishes.moderate', $message), ['action' => 'approve'])
            ->assertRedirect();

        $this->assertSame(MessageStatus::Approved, $message->fresh()->status);

        $this->post(route('owner.wishes.moderate', $message), ['action' => 'hide'])
            ->assertRedirect();

        $this->assertSame(MessageStatus::Hidden, $message->fresh()->status);

        $this->post(route('owner.wishes.moderate', $message), ['action' => 'reopen'])
            ->assertRedirect();

        $this->assertSame(MessageStatus::Pending, $message->fresh()->status);
    }

    public function test_moderation_rejects_an_unknown_action(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $this->actingAs($owner);

        $asset = $this->pendingPhoto($event);

        $this->post(route('owner.memories.moderate', $asset), ['action' => 'destroy'])
            ->assertSessionHasErrors('action');

        $this->assertSame(MediaStatus::Pending, $asset->fresh()->status);
    }

    public function test_moderation_note_is_persisted_in_audit_metadata(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $this->actingAs($owner);

        $asset = $this->pendingPhoto($event);
        Storage::disk('local')->put($asset->path, 'test-image-bytes');

        $this->post(route('owner.memories.moderate', $asset), [
            'action' => 'approve',
            'note' => 'Cek ulang',
        ])->assertRedirect();

        $metadata = DB::table('audit_logs')
            ->where('action', 'memory.media_asset.approve')
            ->where('target_id', $asset->id)
            ->latest('id')
            ->value('metadata');

        $this->assertIsString($metadata);
        $this->assertSame('Cek ulang', json_decode((string) $metadata, true)['note']);
    }

    public function test_cross_event_moderation_returns_404(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $this->actingAs($owner);

        $otherEvent = $this->makeEvent();
        $asset = $this->pendingPhoto($otherEvent);

        $this->post(route('owner.memories.moderate', $asset), ['action' => 'approve'])
            ->assertNotFound();

        $this->assertSame(MediaStatus::Pending, $asset->fresh()->status);
    }
}
