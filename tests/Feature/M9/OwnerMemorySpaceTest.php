<?php

namespace Tests\Feature\M9;

use App\Enums\EventStatus;
use App\Enums\MediaStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsWedding;
use Tests\Feature\M9\Concerns\BuildsMemory;
use Tests\TestCase;

class OwnerMemorySpaceTest extends TestCase
{
    use BuildsMemory, BuildsWedding, RefreshDatabase;

    public function test_couple_can_open_the_memory_space(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $this->actingAs($owner);

        $this->approvedPhoto($event, null, ['caption' => 'Momen resepsi pertama']);

        $response = $this->get(route('owner.memories.index'));

        $response->assertOk();
        $response->assertSee('Momen resepsi pertama');
    }

    public function test_pending_queue_lists_both_types(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $this->actingAs($owner);

        $this->pendingPhoto($event, null, ['caption' => 'Foto menunggu']);
        $this->pendingMessage($event, null, ['message' => 'Ucapan menunggu']);

        $response = $this->get(route('owner.memories.pending'));

        $response->assertOk();
        $response->assertSee('Foto menunggu');
        $response->assertSee('Ucapan menunggu');
    }

    public function test_pending_queue_empty_state(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $this->actingAs($owner);

        $response = $this->get(route('owner.memories.pending'));

        $response->assertOk();
        $response->assertSee('Tidak ada konten yang menunggu pemeriksaan');
    }

    public function test_photo_from_another_event_returns_404(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $this->actingAs($owner);

        $otherEvent = $this->makeEvent();
        $otherAsset = $this->approvedPhoto($otherEvent);

        $response = $this->get(route('owner.memories.show', $otherAsset));

        $response->assertNotFound();
    }

    public function test_collection_from_another_event_returns_404(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $this->actingAs($owner);

        $otherEvent = $this->makeEvent();
        $otherCollection = $this->collection($otherEvent);

        $response = $this->patch(route('owner.memories.collections.update', $otherCollection), [
            'name' => 'Koleksi lain',
        ]);

        $response->assertNotFound();
    }

    public function test_user_without_an_event_gets_403(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('owner.memories.index'));

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        Storage::fake('local');

        $response = $this->get(route('owner.memories.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_archived_event_remains_readable(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent(['status' => EventStatus::Archived]);
        $owner = $this->ownerUser($event);
        $this->actingAs($owner);

        $this->approvedPhoto($event, null, ['caption' => 'Kenangan acara arsip']);

        $response = $this->get(route('owner.memories.index'));

        $response->assertOk();
        $response->assertSee('Kenangan acara arsip');
    }

    public function test_gallery_only_shows_approved_and_unhidden(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $this->actingAs($owner);

        $this->pendingPhoto($event, null, ['caption' => 'Foto masih menunggu']);
        $this->photo($event, null, ['status' => MediaStatus::Rejected, 'caption' => 'Foto sudah ditolak']);
        $this->approvedPhoto($event, null, ['caption' => 'Foto disembunyikan', 'hidden_at' => now()]);
        $this->approvedPhoto($event, null, ['caption' => 'Foto tampil di galeri']);

        $response = $this->get(route('owner.memories.index'));

        $response->assertOk();
        $response->assertSee('Foto tampil di galeri');
        $response->assertDontSee('Foto masih menunggu');
        $response->assertDontSee('Foto sudah ditolak');
        $response->assertDontSee('Foto disembunyikan');
    }

    public function test_timeline_orders_by_submission_time(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $this->actingAs($owner);

        $olderPhoto = $this->approvedPhoto($event, null, ['caption' => 'Foto dua hari lalu']);
        $olderPhoto->created_at = now()->subDays(2);
        $olderPhoto->save();

        $this->approvedMessage($event, null, ['message' => 'Ucapan paling baru']);

        $response = $this->get(route('owner.memories.timeline'));

        $response->assertOk();

        $content = $response->getContent();
        $newerPosition = strpos($content, 'Ucapan paling baru');
        $olderPosition = strpos($content, 'Foto dua hari lalu');

        $this->assertNotFalse($newerPosition);
        $this->assertNotFalse($olderPosition);
        $this->assertLessThan($olderPosition, $newerPosition);
    }
}
