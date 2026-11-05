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
    }

    public function test_guest_upload_is_stored_as_pending(): void
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
        Storage::disk('public')->assertExists($asset->path);
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

    public function test_admin_upload_is_auto_approved(): void
    {
        $admin = User::factory()->superadmin()->create();
        $event = $this->makeEvent();

        $this->actingAs($admin)
            ->post(route('admin.media.store'), [
                'event_id' => $event->id,
                'files' => [UploadedFile::fake()->image('galeri.jpg')],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', MediaAsset::firstOrFail()->status->value);
    }

    public function test_admin_can_moderate_guest_upload(): void
    {
        $admin = User::factory()->superadmin()->create();
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $asset = MediaAsset::create([
            'event_id' => $event->id,
            'guest_id' => $guest->id,
            'path' => 'events/'.$event->id.'/guest-uploads/test.jpg',
            'original_name' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.media.moderate', $asset), ['action' => 'approve'])
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $asset->fresh()->status->value);
    }
}
