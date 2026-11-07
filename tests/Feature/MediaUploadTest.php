<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsWedding;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    use BuildsWedding, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_guest_upload_is_staged_privately_as_pending(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/gallery', [
                'photo' => UploadedFile::fake()->image('momen.jpg'),
                'caption' => 'Momen indah',
            ])
            ->assertSessionHasNoErrors();

        $asset = MediaAsset::firstOrFail();

        $this->assertSame($event->id, $asset->event_id);
        $this->assertSame($guest->id, $asset->guest_id);
        $this->assertSame('pending', $asset->status->value);
        $this->assertSame('local', $asset->disk);

        // Staged on the private disk, never on the public disk.
        Storage::disk('local')->assertExists($asset->path);
        Storage::disk('public')->assertMissing($asset->path);
    }

    public function test_guest_upload_rejects_non_image_files(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/gallery', [
                'photo' => UploadedFile::fake()->create('dokumen.pdf', 120, 'application/pdf'),
            ])
            ->assertSessionHasErrors('photo');

        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_upload_is_blocked_when_gallery_disabled(): void
    {
        $event = $this->makeEvent(['gallery_enabled' => false]);
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->post('/invite/'.$token.'/gallery', [
            'photo' => UploadedFile::fake()->image('momen.jpg'),
        ])->assertForbidden();

        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_admin_upload_is_auto_approved_on_private_disk(): void
    {
        $admin = User::factory()->superadmin()->create();
        $event = $this->makeEvent();

        $this->actingAs($admin)
            ->post(route('admin.media.store'), [
                'event_id' => $event->id,
                'files' => [UploadedFile::fake()->image('galeri.jpg')],
            ])
            ->assertSessionHasNoErrors();

        $asset = MediaAsset::firstOrFail();

        $this->assertSame('approved', $asset->status->value);
        $this->assertSame('local', $asset->disk);
        Storage::disk('local')->assertExists($asset->path);
        Storage::disk('public')->assertMissing($asset->path);
    }

    public function test_approving_a_guest_upload_keeps_the_file_private(): void
    {
        $admin = User::factory()->superadmin()->create();
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/gallery', [
                'photo' => UploadedFile::fake()->image('momen.jpg'),
            ])
            ->assertSessionHasNoErrors();

        $asset = MediaAsset::firstOrFail();
        $this->assertSame('local', $asset->disk);

        $this->actingAs($admin)
            ->post(route('admin.media.moderate', $asset), ['action' => 'approve'])
            ->assertSessionHasNoErrors();

        $asset->refresh();

        $this->assertSame('approved', $asset->status->value);
        $this->assertSame('local', $asset->disk);
        Storage::disk('local')->assertExists($asset->path);
        Storage::disk('public')->assertMissing($asset->path);
    }

    public function test_rejecting_a_guest_upload_deletes_the_staged_file(): void
    {
        $admin = User::factory()->superadmin()->create();
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/gallery', [
                'photo' => UploadedFile::fake()->image('momen.jpg'),
            ]);

        $asset = MediaAsset::firstOrFail();
        $stagedPath = $asset->path;

        $this->actingAs($admin)
            ->post(route('admin.media.moderate', $asset), ['action' => 'reject'])
            ->assertSessionHasNoErrors();

        $this->assertSame('rejected', $asset->fresh()->status->value);
        Storage::disk('local')->assertMissing($stagedPath);
    }

    public function test_admin_upload_generates_bounded_derivatives(): void
    {
        $admin = User::factory()->superadmin()->create();
        $event = $this->makeEvent();

        $this->actingAs($admin)
            ->post(route('admin.media.store'), [
                'event_id' => $event->id,
                'files' => [UploadedFile::fake()->image('panorama.jpg', 1600, 1200)],
            ])
            ->assertSessionHasNoErrors();

        $asset = MediaAsset::firstOrFail();

        $this->assertNotNull($asset->thumbnail_path);
        $this->assertNotNull($asset->medium_path);
        Storage::disk('local')->assertExists($asset->thumbnail_path);
        Storage::disk('local')->assertExists($asset->medium_path);
        Storage::disk('public')->assertMissing($asset->thumbnail_path);
    }

    public function test_pending_media_preview_requires_a_superadmin(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/gallery', [
                'photo' => UploadedFile::fake()->image('momen.jpg'),
            ]);

        $asset = MediaAsset::firstOrFail();

        // Guest (unauthenticated) cannot reach the preview endpoint.
        $this->get(route('admin.media.preview', $asset))->assertRedirect('/login');

        $owner = User::factory()->owner()->create();
        $owner->events()->attach($event->id);
        $this->actingAs($owner)
            ->get(route('admin.media.preview', $asset))
            ->assertForbidden();

        $admin = User::factory()->superadmin()->create();
        $this->actingAs($admin)
            ->get(route('admin.media.preview', $asset))
            ->assertOk();
    }
}
