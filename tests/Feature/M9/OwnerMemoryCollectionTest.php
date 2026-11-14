<?php

namespace Tests\Feature\M9;

use App\Enums\MemoryItemType;
use App\Models\MemoryCollectionItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsWedding;
use Tests\Feature\M9\Concerns\BuildsMemory;
use Tests\TestCase;

class OwnerMemoryCollectionTest extends TestCase
{
    use BuildsMemory, BuildsWedding, RefreshDatabase;

    public function test_owner_can_attach_a_bulk_selection_of_photos(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $this->actingAs($owner);

        $collection = $this->collection($event);
        $photos = [
            $this->approvedPhoto($event),
            $this->approvedPhoto($event),
            $this->approvedPhoto($event),
        ];
        $photoIds = array_map(fn ($photo) => $photo->id, $photos);

        $response = $this->post(route('owner.memories.collections.attach'), [
            'collection_id' => $collection->id,
            'type' => MemoryItemType::MediaAsset->value,
            'item_ids' => $photoIds,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertDatabaseCount('memory_collection_items', 3);
        $this->assertSame(
            collect($photoIds)->sort()->values()->all(),
            MemoryCollectionItem::query()->orderBy('item_id')->pluck('item_id')->all(),
        );
    }

    public function test_attach_counts_items_already_in_the_collection_as_skipped(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $this->actingAs($owner);

        $collection = $this->collection($event);
        $first = $this->approvedPhoto($event);
        $second = $this->approvedPhoto($event);

        $this->post(route('owner.memories.collections.attach'), [
            'collection_id' => $collection->id,
            'type' => MemoryItemType::MediaAsset->value,
            'item_ids' => [$first->id],
        ]);

        $response = $this->post(route('owner.memories.collections.attach'), [
            'collection_id' => $collection->id,
            'type' => MemoryItemType::MediaAsset->value,
            'item_ids' => [$first->id, $second->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', fn ($value) => str_contains((string) $value, 'sudah ada'));

        $this->assertDatabaseCount('memory_collection_items', 2);
    }

    public function test_attach_rejects_items_from_another_event(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent();
        $otherEvent = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $this->actingAs($owner);

        $collection = $this->collection($event);
        $foreignPhoto = $this->approvedPhoto($otherEvent);

        $this->post(route('owner.memories.collections.attach'), [
            'collection_id' => $collection->id,
            'type' => MemoryItemType::MediaAsset->value,
            'item_ids' => [$foreignPhoto->id],
        ])->assertNotFound();

        $this->assertDatabaseCount('memory_collection_items', 0);
    }

    public function test_attach_rejects_an_unknown_item_type(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $this->actingAs($owner);

        $collection = $this->collection($event);

        $this->post(route('owner.memories.collections.attach'), [
            'collection_id' => $collection->id,
            'type' => 'invoice',
            'item_ids' => [1],
        ])->assertSessionHasErrors('type');

        $this->assertDatabaseCount('memory_collection_items', 0);
    }

    public function test_attach_requires_at_least_one_item(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $this->actingAs($owner);

        $collection = $this->collection($event);

        $this->post(route('owner.memories.collections.attach'), [
            'collection_id' => $collection->id,
            'type' => MemoryItemType::MediaAsset->value,
            'item_ids' => [],
        ])->assertSessionHasErrors('item_ids');

        $this->assertDatabaseCount('memory_collection_items', 0);
    }

    public function test_attach_respects_the_collection_item_limit(): void
    {
        Storage::fake('local');
        config()->set('memory.collection_max_items', 2);

        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $this->actingAs($owner);

        $collection = $this->collection($event);
        $photos = [
            $this->approvedPhoto($event),
            $this->approvedPhoto($event),
            $this->approvedPhoto($event),
        ];

        $response = $this->post(route('owner.memories.collections.attach'), [
            'collection_id' => $collection->id,
            'type' => MemoryItemType::MediaAsset->value,
            'item_ids' => array_map(fn ($photo) => $photo->id, $photos),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('item');

        $this->assertDatabaseCount('memory_collection_items', 0);
    }

    public function test_owner_can_attach_guestbook_messages(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $this->actingAs($owner);

        $collection = $this->collection($event);
        $messages = [
            $this->approvedMessage($event),
            $this->approvedMessage($event),
        ];

        $this->post(route('owner.memories.collections.attach'), [
            'collection_id' => $collection->id,
            'type' => MemoryItemType::GuestbookMessage->value,
            'item_ids' => array_map(fn ($message) => $message->id, $messages),
        ])->assertRedirect();

        $this->assertDatabaseCount('memory_collection_items', 2);
        $this->assertSame(
            MemoryItemType::GuestbookMessage->value,
            MemoryCollectionItem::query()->first()->item_type,
        );
    }

    public function test_guest_cannot_attach_to_a_collection(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent();
        $collection = $this->collection($event);
        $photo = $this->approvedPhoto($event);

        $this->post(route('owner.memories.collections.attach'), [
            'collection_id' => $collection->id,
            'type' => MemoryItemType::MediaAsset->value,
            'item_ids' => [$photo->id],
        ])->assertRedirect();

        $this->assertDatabaseCount('memory_collection_items', 0);
    }

    public function test_attach_to_a_collection_of_another_event_returns_404(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent();
        $otherEvent = $this->makeEvent();
        $owner = $this->ownerUser($otherEvent);
        $this->actingAs($owner);

        $collection = $this->collection($event);
        $photo = $this->approvedPhoto($event);

        $this->post(route('owner.memories.collections.attach'), [
            'collection_id' => $collection->id,
            'type' => MemoryItemType::MediaAsset->value,
            'item_ids' => [$photo->id],
        ])->assertNotFound();

        $this->assertDatabaseCount('memory_collection_items', 0);
    }
}
