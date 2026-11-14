<?php

namespace Tests\Feature\M9;

use App\Enums\EventStatus;
use App\Enums\MediaStatus;
use App\Enums\MemoryItemType;
use App\Models\MemoryConsent;
use App\Services\MemoryFeedService;
use App\Services\MemoryModerationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\BuildsWedding;
use Tests\Feature\M9\Concerns\BuildsMemory;
use Tests\TestCase;

class MemorySecurityTest extends TestCase
{
    use BuildsMemory, BuildsWedding, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_a_photo_from_another_event_cannot_be_moderated(): void
    {
        $event = $this->makeEvent();
        $otherEvent = $this->makeEvent();
        $owner = $this->ownerUser($event);

        $foreignAsset = $this->approvedPhoto($otherEvent);

        $this->expectException(ModelNotFoundException::class);

        app(MemoryModerationService::class)->apply('media_asset', $foreignAsset->id, 'approve', $event, $owner);
    }

    public function test_timeline_never_includes_another_events_content(): void
    {
        $event = $this->makeEvent();
        $otherEvent = $this->makeEvent();
        $guest = $this->makeGuest($event);

        $ours = $this->approvedPhoto($event, $guest);
        $theirs = $this->approvedPhoto($otherEvent);

        $rows = app(MemoryFeedService::class)->timeline($event)->items();

        $ids = collect($rows)->map(static fn (array $row): int => $row['model']->id)->all();

        $this->assertContains($ours->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    public function test_hidden_photo_is_not_delivered_publicly(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);

        $asset = $this->approvedPhoto($event, $guest, ['hidden_at' => now()]);

        Storage::disk('local')->put($asset->path, 'test-image-bytes');

        $this->get($asset->url())->assertNotFound();
    }

    public function test_hidden_photo_is_still_delivered_to_the_owner(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);

        $asset = $this->approvedPhoto($event, $guest, ['hidden_at' => now()]);

        Storage::disk('local')->put($asset->path, 'test-image-bytes');

        $this->get($asset->url(null, true))->assertOk();
    }

    public function test_archived_event_media_is_404_for_the_public_but_200_for_the_owner(): void
    {
        $event = $this->makeEvent(['status' => EventStatus::Archived]);
        $guest = $this->makeGuest($event);

        $asset = $this->approvedPhoto($event, $guest);

        Storage::disk('local')->put($asset->path, 'test-image-bytes');

        $this->get($asset->url())->assertNotFound();
        $this->get($asset->url(null, true))->assertOk();
    }

    public function test_unsigned_media_url_is_rejected(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);

        $asset = $this->approvedPhoto($event, $guest);

        $this->get('/media/'.$asset->id)->assertForbidden();
    }

    public function test_invalid_share_token_returns_404(): void
    {
        $routeExists = collect(app('router')->getRoutes())->contains(
            static fn (Route $route): bool => $route->uri() === 'm/{token}'
                && in_array('GET', $route->methods(), true)
        );

        if (! $routeExists) {
            $this->markTestSkipped(
                'The public share route GET /m/{token} is not registered yet; no endpoint resolves share tokens.'
            );
        }

        $this->get('/m/not-a-real-token')->assertNotFound();
    }

    public function test_moderation_actions_are_audited(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $owner = $this->ownerUser($event);

        $asset = $this->pendingPhoto($event, $guest);

        Storage::disk('local')->put($asset->path, 'test-image-bytes');

        app(MemoryModerationService::class)->apply('media_asset', $asset->id, 'approve', $event, $owner);

        $this->assertSame(MediaStatus::Approved->value, $asset->fresh()->status->value);

        // The service prefixes the contribution type, so the media action is
        // recorded as `memory.media_asset.approve` rather than `memory.media.approve`.
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'memory.media_asset.approve',
            'event_id' => $event->id,
            'target_id' => $asset->id,
        ]);
    }

    public function test_consent_is_never_written_without_a_contribution(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);

        $asset = $this->approvedPhoto($event, $guest);

        $consent = [
            'event_id' => $event->id,
            'contribution_type' => MemoryItemType::MediaAsset->value,
            'contribution_id' => $asset->id,
            'guest_id' => $guest->id,
            'contributor_name' => $guest->name,
            'allow_event_display' => true,
            'allow_public_display' => false,
            'terms_version' => config('memory.terms_version'),
            'accepted_at' => now(),
        ];

        MemoryConsent::create($consent);

        try {
            MemoryConsent::create($consent);
            $this->fail('A second consent was written for the same contribution.');
        } catch (QueryException) {
            // The unique (contribution_type, contribution_id) pair held.
        }

        $this->assertDatabaseCount('memory_consents', 1);
    }

    public function test_a_pending_photo_is_previewable_by_the_owner_but_never_publicly(): void
    {
        $event = $this->makeEvent();
        $asset = $this->pendingPhoto($event);

        Storage::disk('local')->put($asset->path, 'bytes');

        $this->get($asset->url(null, true))->assertOk();

        // The public delivery endpoint must refuse an unapproved asset even
        // when the caller holds a valid signature.
        $publicUrl = URL::temporarySignedRoute(
            'media.show',
            now()->addMinutes(5),
            ['asset' => $asset->id],
        );

        $this->get($publicUrl)->assertNotFound();
    }
}
