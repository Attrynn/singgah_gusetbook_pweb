<?php

namespace Tests\Feature\M9;

use App\Enums\MemoryExportStatus;
use App\Models\MemoryExportJob;
use App\Services\MemoryExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsWedding;
use Tests\Feature\M9\Concerns\BuildsMemory;
use Tests\TestCase;
use ZipArchive;

class OwnerMemoryExportTest extends TestCase
{
    use BuildsMemory, BuildsWedding, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_requesting_an_export_creates_a_queued_job_with_a_snapshot(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $this->actingAs($owner);

        $photo = $this->approvedPhoto($event);
        $message = $this->approvedMessage($event);

        $this->post(route('owner.memories.export.store'))
            ->assertRedirect();

        $job = MemoryExportJob::query()->firstOrFail();

        $this->assertSame(MemoryExportStatus::Queued, $job->status);
        $this->assertSame(2, $job->item_count);
        $this->assertContains($photo->id, $job->scope['media_asset_ids']);
        $this->assertContains($message->id, $job->scope['guestbook_message_ids']);
    }

    public function test_export_excludes_pending_and_hidden_photos(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $this->actingAs($owner);

        $this->pendingPhoto($event);
        $this->approvedPhoto($event, null, ['hidden_at' => now()]);
        $approved = $this->approvedPhoto($event);

        $this->post(route('owner.memories.export.store'))
            ->assertRedirect();

        $job = MemoryExportJob::query()->firstOrFail();

        $this->assertSame(1, $job->item_count);
        $this->assertSame([$approved->id], $job->scope['media_asset_ids']);
    }

    public function test_export_respects_the_include_flags(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $this->actingAs($owner);

        $this->approvedPhoto($event);
        $message = $this->approvedMessage($event);

        $this->post(route('owner.memories.export.store'), [
            'include_photos' => 0,
            'include_messages' => 1,
        ])->assertRedirect();

        $job = MemoryExportJob::query()->firstOrFail();

        $this->assertSame(1, $job->item_count);
        $this->assertSame([], $job->scope['media_asset_ids']);
        $this->assertSame([$message->id], $job->scope['guestbook_message_ids']);
    }

    public function test_export_is_rejected_above_the_item_cap(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $this->actingAs($owner);

        Config::set('memory.export_max_items', 1);

        $this->approvedPhoto($event);
        $this->approvedMessage($event);

        $this->post(route('owner.memories.export.store'))
            ->assertSessionHasErrors('export');

        $this->assertDatabaseCount('memory_export_jobs', 0);
    }

    public function test_completed_job_is_downloadable(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $this->actingAs($owner);

        $job = MemoryExportJob::create([
            'event_id' => $event->id,
            'requested_by' => $owner->id,
            'status' => MemoryExportStatus::Completed,
            'scope' => ['media_asset_ids' => [], 'guestbook_message_ids' => []],
            'file_disk' => 'local',
            'file_path' => 'exports/memory/x.zip',
            'item_count' => 1,
            'expires_at' => now()->addDay(),
        ]);

        Storage::disk('local')->put('exports/memory/x.zip', 'archive-bytes');

        $this->get(route('owner.memories.export.download', $job))
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'memory.export.downloaded',
            'target_id' => $job->id,
        ]);
    }

    public function test_expired_or_missing_file_is_not_downloadable(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $this->actingAs($owner);

        $job = MemoryExportJob::create([
            'event_id' => $event->id,
            'requested_by' => $owner->id,
            'status' => MemoryExportStatus::Completed,
            'scope' => ['media_asset_ids' => [], 'guestbook_message_ids' => []],
            'file_disk' => 'local',
            'file_path' => 'exports/memory/x.zip',
            'item_count' => 1,
            'expires_at' => now()->subDay(),
        ]);

        Storage::disk('local')->put('exports/memory/x.zip', 'archive-bytes');

        $this->get(route('owner.memories.export.download', $job))
            ->assertNotFound();

        $job->update(['expires_at' => now()->addDay()]);
        Storage::disk('local')->delete('exports/memory/x.zip');

        $this->get(route('owner.memories.export.download', $job))
            ->assertNotFound();
    }

    public function test_building_the_archive_writes_manifest_and_csv(): void
    {
        Storage::fake('local');
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $this->actingAs($owner);

        $photo = $this->approvedPhoto($event);
        $message = $this->approvedMessage($event);

        Storage::disk('local')->put($photo->path, 'test-image-bytes');

        $job = MemoryExportJob::create([
            'event_id' => $event->id,
            'requested_by' => $owner->id,
            'status' => MemoryExportStatus::Queued,
            'scope' => [
                'media_asset_ids' => [$photo->id],
                'guestbook_message_ids' => [$message->id],
            ],
            'expires_at' => null,
        ]);

        $built = app(MemoryExportService::class)->build($job);

        $this->assertSame(MemoryExportStatus::Completed, $built->status);
        Storage::disk('local')->assertExists($built->file_path);

        $zip = new ZipArchive;

        $this->assertTrue($zip->open(Storage::disk('local')->path($built->file_path)));

        $this->assertNotFalse($zip->locateName('manifest.json'));
        $this->assertNotFalse($zip->locateName('messages.csv'));

        $hasPhotoEntry = false;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            if (str_starts_with((string) $zip->getNameIndex($index), 'photos/')) {
                $hasPhotoEntry = true;

                break;
            }
        }

        $this->assertTrue($hasPhotoEntry);

        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);

        $this->assertIsArray($manifest);
        $this->assertArrayHasKey('counts', $manifest);
        $this->assertArrayHasKey('snapshot_semantics', $manifest);

        $zip->close();
    }
}
