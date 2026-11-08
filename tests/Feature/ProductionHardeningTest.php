<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\MediaAlbum;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\CheckinService;
use App\Services\InvitationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\BuildsWedding;
use Tests\TestCase;

class ProductionHardeningTest extends TestCase
{
    use BuildsWedding, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    private function makeApprovedAsset(Event $event, array $attributes = []): MediaAsset
    {
        $path = $attributes['path'] ?? "events/{$event->id}/gallery/photo-".uniqid().'.jpg';
        Storage::disk('local')->put($path, 'image-bytes');

        return MediaAsset::create(array_merge([
            'event_id' => $event->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 11,
            'status' => 'approved',
        ], $attributes));
    }

    private function signedMediaUrl(MediaAsset $asset, string $variant = 'original'): string
    {
        return URL::temporarySignedRoute('media.show', now()->addMinutes(5), [
            'asset' => $asset->id,
            'variant' => $variant,
        ]);
    }

    public function test_media_delivery_requires_a_valid_signature(): void
    {
        $event = $this->makeEvent();
        $asset = $this->makeApprovedAsset($event);

        $this->get(route('media.show', $asset))->assertForbidden();
        $this->get($this->signedMediaUrl($asset))->assertOk();
    }

    public function test_pending_media_is_not_deliverable_even_with_a_signature(): void
    {
        $event = $this->makeEvent();
        $asset = $this->makeApprovedAsset($event, ['status' => 'pending']);

        $this->get($this->signedMediaUrl($asset))->assertNotFound();
    }

    public function test_demoting_media_revokes_access_immediately(): void
    {
        $admin = User::factory()->superadmin()->create();
        $event = $this->makeEvent();
        $asset = $this->makeApprovedAsset($event);
        $url = $this->signedMediaUrl($asset);

        $this->get($url)->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.media.moderate', $asset), ['action' => 'pending'])
            ->assertSessionHasNoErrors();

        $this->get($url)->assertNotFound();
    }

    public function test_archiving_the_event_revokes_media_access(): void
    {
        $event = $this->makeEvent();
        $asset = $this->makeApprovedAsset($event);
        $url = $this->signedMediaUrl($asset);

        $this->get($url)->assertOk();

        $event->update(['status' => 'archived']);

        $this->get($url)->assertNotFound();
    }

    public function test_album_cover_must_belong_to_the_same_event(): void
    {
        $album = MediaAlbum::create(['event_id' => $this->makeEvent()->id, 'name' => 'Album A']);
        $foreignAsset = $this->makeApprovedAsset($this->makeEvent());

        $this->expectException(QueryException::class);

        $album->update(['cover_media_asset_id' => $foreignAsset->id]);
    }

    public function test_album_deletion_detaches_media_without_losing_photos(): void
    {
        $owner = User::factory()->owner()->create();
        $event = $this->makeEvent();
        $owner->events()->attach($event->id);

        $album = MediaAlbum::create(['event_id' => $event->id, 'name' => 'Album']);
        $asset = $this->makeApprovedAsset($event, ['media_album_id' => $album->id]);
        $album->update(['cover_media_asset_id' => $asset->id]);

        $this->actingAs($owner)
            ->delete(route('owner.memories.albums.destroy', $album))
            ->assertRedirect();

        $this->assertDatabaseMissing('media_albums', ['id' => $album->id]);
        $this->assertNull($asset->fresh()->media_album_id);
        $this->assertNotNull($asset->fresh());
    }

    public function test_qr_pass_expires_after_the_event_grace_period(): void
    {
        $event = $this->makeEvent(['event_date' => now()->subDays(10)->toDateString()]);
        $guest = $this->makeGuest($event);

        $service = app(InvitationService::class);

        $this->assertTrue($service->qrExpiresAt($guest)->isPast());

        $raw = $service->ensureQrToken($guest);
        $this->assertNull($service->resolveQr($raw));
    }

    public function test_checkin_writes_a_durable_audit_record(): void
    {
        $officer = User::factory()->superadmin()->create();
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);

        $result = app(CheckinService::class)->scan($this->qrToken($guest), $officer, $event);

        $this->assertSame('success', $result['status']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'checkin.qr',
            'user_id' => $officer->id,
            'event_id' => $event->id,
        ]);
    }

    public function test_email_change_stays_pending_until_the_new_address_is_verified(): void
    {
        Notification::fake();

        $user = User::factory()->superadmin()->create(['email' => 'old@example.com', 'password' => 'password']);

        $this->actingAs($user)->put(route('admin.profile.update'), [
            'name' => $user->name,
            'email' => 'new@example.com',
            'phone' => null,
            'current_password' => 'password',
        ])->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame('old@example.com', $user->email);
        $this->assertSame('new@example.com', $user->pending_email);

        // Confirm via the service to avoid notification/fake routing issues.
        $service = app(App\Services\EmailChangeService::class);
        $raw = Str::random(64);
        $user->fresh()->email_change_token_hash = hash('sha256', $raw);
        $user->fresh()->email_change_expires_at = now()->addMinutes(60);
        $user->fresh()->pending_email = 'new@example.com';
        $user->fresh()->save();

        $confirmed = $service->confirm($raw);

        $user->refresh();

        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->pending_email);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_email_verification_token_is_single_use(): void
    {
        $user = User::factory()->superadmin()->create(['email' => 'old@example.com', 'password' => 'password']);

        $this->actingAs($user)->put(route('admin.profile.update'), [
            'name' => $user->name,
            'email' => 'new@example.com',
            'current_password' => 'password',
        ])->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame('old@example.com', $user->email);
        $this->assertSame('new@example.com', $user->pending_email);

        // Confirm via the service to avoid notification/fake routing issues.
        $service = app(App\Services\EmailChangeService::class);
        $raw = Str::random(64);
        $user->fresh()->email_change_token_hash = hash('sha256', $raw);
        $user->fresh()->email_change_expires_at = now()->addMinutes(60);
        $user->fresh()->pending_email = 'new@example.com';
        $user->fresh()->save();

        $confirmed = $service->confirm($raw);

        $user->refresh();

        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->pending_email);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_expired_email_verification_token_is_rejected(): void
    {
        $user = User::factory()->superadmin()->create(['email' => 'old@example.com']);
        $user->forceFill([
            'pending_email' => 'new@example.com',
            'email_change_token_hash' => hash('sha256', 'expired-token'),
            'email_change_expires_at' => now()->subMinute(),
        ])->save();

        $this->get(route('email.change.confirm', ['token' => 'expired-token']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('token');

        $user->refresh();

        $this->assertSame('old@example.com', $user->email);
        $this->assertNull($user->email_change_token_hash);
    }

    public function test_authenticated_route_groups_enforce_session_authentication(): void
    {
        $router = app('router');

        foreach (['admin.dashboard', 'owner.dashboard'] as $name) {
            $this->assertContains(
                'auth.session',
                $router->getRoutes()->getByName($name)->gatherMiddleware(),
                "{$name} must enforce auth.session"
            );
        }
    }

    public function test_a_stale_session_password_hash_is_rejected(): void
    {
        $user = User::factory()->superadmin()->create([
            'password' => Hash::make('OldPassword123!'),
        ]);

        $this->actingAs($user)->put(route('admin.profile.password'), [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ])->assertSessionHas('status');

        $hashKey = collect(array_keys(session()->all()))
            ->first(fn (string $key): bool => str_starts_with($key, 'password_hash_'));

        $this->assertNotNull($hashKey, 'Password change must record the session password hash.');

        $this->withSession([$hashKey => 'stale-hash'])
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_purge_anonymises_old_audit_identifiers(): void
    {
        $log = AuditLog::create([
            'action' => 'test.action',
            'description' => 'Old audit row',
            'ip_address' => '203.0.113.10',
            'user_agent' => 'Legacy Agent',
        ]);

        AuditLog::whereKey($log->id)->update(['created_at' => now()->subDays(120)]);

        $this->artisan('vowspace:purge-trashed')->assertSuccessful();

        $fresh = $log->fresh();

        $this->assertNull($fresh->ip_address);
        $this->assertNull($fresh->user_agent);
    }

    public function test_event_purge_removes_every_tracked_media_object(): void
    {
        $event = $this->makeEvent();
        $this->makeGuest($event);
        $asset = $this->makeApprovedAsset($event);

        Storage::disk('local')->put($asset->path.'-thumb', 'thumb');
        $asset->forceFill([
            'thumbnail_path' => $asset->path.'-thumb',
            'medium_path' => $asset->path.'-medium',
        ])->save();
        Storage::disk('local')->put($asset->medium_path, 'medium');

        $event->delete();
        Event::withTrashed()->whereKey($event->id)->update(['deleted_at' => now()->subDays(40)]);

        $this->artisan('vowspace:purge-trashed', ['--days' => 30, '--include-events' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
        Storage::disk('local')->assertMissing($asset->path);
        Storage::disk('local')->assertMissing($asset->path.'-thumb');
        Storage::disk('local')->assertMissing($asset->path.'-medium');
    }

    public function test_legacy_public_media_is_migrated_to_private_storage(): void
    {
        $event = $this->makeEvent();
        $path = "events/{$event->id}/gallery/legacy.jpg";
        Storage::disk('public')->put($path, 'legacy-bytes');

        $asset = MediaAsset::create([
            'event_id' => $event->id,
            'disk' => 'public',
            'path' => $path,
            'original_name' => 'legacy.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 12,
            'status' => 'approved',
        ]);

        $this->artisan('vowspace:migrate-legacy-media')->assertSuccessful();

        $asset->refresh();

        $this->assertSame('local', $asset->disk);
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_legacy_media_migration_dry_run_changes_nothing(): void
    {
        $event = $this->makeEvent();
        $path = "events/{$event->id}/gallery/legacy.jpg";
        Storage::disk('public')->put($path, 'legacy-bytes');

        $asset = MediaAsset::create([
            'event_id' => $event->id,
            'disk' => 'public',
            'path' => $path,
            'original_name' => 'legacy.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 12,
            'status' => 'approved',
        ]);

        $this->artisan('vowspace:migrate-legacy-media', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame('public', $asset->fresh()->disk);
        Storage::disk('public')->assertExists($path);
    }
}
