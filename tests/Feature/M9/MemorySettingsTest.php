<?php

namespace Tests\Feature\M9;

use App\Models\EventMemorySettings;
use App\Services\MemorySettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsWedding;
use Tests\Feature\M9\Concerns\BuildsMemory;
use Tests\TestCase;

class MemorySettingsTest extends TestCase
{
    use BuildsMemory, BuildsWedding, RefreshDatabase;

    public function test_settings_are_created_with_safe_defaults(): void
    {
        $event = $this->makeEvent();

        $settings = EventMemorySettings::for($event);

        $this->assertTrue($settings->submissions_open);
        $this->assertFalse($settings->post_event_submissions);
        $this->assertFalse($settings->auto_approve);
        $this->assertFalse($settings->public_sharing);
    }

    public function test_update_persists_allowed_fields_and_writes_audit(): void
    {
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $service = app(MemorySettingsService::class);

        $opensAt = now()->addDay();
        $closesAt = now()->addDays(3);

        $service->update($event, [
            'submissions_open' => false,
            'post_event_submissions' => true,
            'submission_opens_at' => $opensAt->toDateTimeString(),
            'submission_closes_at' => $closesAt->toDateTimeString(),
            'auto_approve' => true,
            'public_sharing' => true,
            'retention_days' => 90,
        ], $owner);

        $fresh = $service->for($event);

        $this->assertFalse($fresh->submissions_open);
        $this->assertTrue($fresh->post_event_submissions);
        $this->assertTrue($fresh->auto_approve);
        $this->assertTrue($fresh->public_sharing);
        $this->assertSame(90, $fresh->retention_days);
        $this->assertEqualsWithDelta($opensAt->getTimestamp(), $fresh->submission_opens_at->getTimestamp(), 5);
        $this->assertEqualsWithDelta($closesAt->getTimestamp(), $fresh->submission_closes_at->getTimestamp(), 5);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'memory.settings.updated',
            'event_id' => $event->id,
        ]);
    }

    public function test_update_ignores_unknown_keys(): void
    {
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $service = app(MemorySettingsService::class);

        $before = $service->for($event);

        $service->update($event, [
            'event_id' => 999,
            'id' => 5,
        ], $owner);

        $fresh = $service->for($event);

        $this->assertSame($before->id, $fresh->id);
        $this->assertSame($event->id, $fresh->event_id);
        $this->assertSame($before->submissions_open, $fresh->submissions_open);
        $this->assertSame($before->post_event_submissions, $fresh->post_event_submissions);
        $this->assertSame($before->auto_approve, $fresh->auto_approve);
        $this->assertSame($before->public_sharing, $fresh->public_sharing);
        $this->assertDatabaseCount('event_memory_settings', 1);
        $this->assertDatabaseMissing('event_memory_settings', ['event_id' => 999]);
    }

    public function test_invalid_submission_window_is_rejected(): void
    {
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $service = app(MemorySettingsService::class);

        $this->expectException(ValidationException::class);

        $service->update($event, [
            'submission_opens_at' => now()->addDay()->toDateTimeString(),
            'submission_closes_at' => now()->subDay()->toDateTimeString(),
        ], $owner);
    }

    public function test_close_and_reopen_submissions(): void
    {
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $service = app(MemorySettingsService::class);

        $closed = $service->closeSubmissions($event, $owner);

        $this->assertFalse($closed->submissions_open);
        $this->assertNotNull($closed->closed_at);
        $this->assertSame($owner->id, $closed->closed_by);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'memory.submissions.closed',
            'event_id' => $event->id,
        ]);

        $reopened = $service->reopenSubmissions($event, $owner);

        $this->assertTrue($reopened->submissions_open);
        $this->assertNull($reopened->closed_at);
        $this->assertNull($reopened->closed_by);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'memory.submissions.reopened',
            'event_id' => $event->id,
        ]);
    }

    public function test_enabling_sharing_returns_a_raw_token_and_stores_only_its_hash(): void
    {
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $service = app(MemorySettingsService::class);

        $raw = $service->enableSharing($event, $owner);

        $settings = $service->for($event);

        $this->assertSame(64, strlen($raw));
        $this->assertSame(hash('sha256', $raw), $settings->share_token_hash);
        $this->assertSame(substr($raw, 0, 8), $settings->share_token_prefix);
        $this->assertTrue($settings->public_sharing);

        $row = EventMemorySettings::query()->findOrFail($settings->id)->toArray();

        $this->assertStringNotContainsString($raw, json_encode($row));
    }

    public function test_resolve_share_token_matches_only_an_active_token(): void
    {
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $service = app(MemorySettingsService::class);

        $raw = $service->enableSharing($event, $owner);

        $resolved = $service->resolveShareToken($raw);

        $this->assertNotNull($resolved);
        $this->assertSame($service->for($event)->id, $resolved->id);

        $service->revokeSharing($event, $owner);

        $this->assertNull($service->resolveShareToken($raw));

        $raw = $service->enableSharing($event, $owner);
        $service->for($event)->update(['share_token_expires_at' => now()->subDay()]);

        $this->assertNull($service->resolveShareToken($raw));

        $raw = $service->enableSharing($event, $owner);
        $service->for($event)->update(['public_sharing' => false]);

        $this->assertNull($service->resolveShareToken($raw));
    }

    public function test_disabling_public_sharing_revokes_the_token(): void
    {
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $service = app(MemorySettingsService::class);

        $service->enableSharing($event, $owner);

        $settings = $service->update($event, ['public_sharing' => false], $owner);

        $this->assertFalse($settings->public_sharing);
        $this->assertNotNull($settings->share_token_revoked_at);
    }

    public function test_share_token_ttl_is_applied_when_configured(): void
    {
        $event = $this->makeEvent();
        $owner = $this->ownerUser($event);
        $service = app(MemorySettingsService::class);

        Config::set('memory.share_token_ttl_days', 7);

        $service->enableSharing($event, $owner);

        $expiresAt = $service->for($event)->share_token_expires_at;

        $this->assertNotNull($expiresAt);
        $this->assertEqualsWithDelta(now()->addDays(7)->getTimestamp(), $expiresAt->getTimestamp(), 5);

        Config::set('memory.share_token_ttl_days', 0);

        $service->enableSharing($event, $owner);

        $this->assertNull($service->for($event)->share_token_expires_at);
    }
}
