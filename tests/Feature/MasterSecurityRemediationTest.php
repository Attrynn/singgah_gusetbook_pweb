<?php

namespace Tests\Feature;

use App\Enums\GuestStatus;
use App\Enums\MessageStatus;
use App\Models\AppSetting;
use App\Models\EventSchedule;
use App\Models\Guest;
use App\Models\GuestbookMessage;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\CheckinService;
use App\Services\EventService;
use App\Services\GuestbookService;
use App\Services\MediaDerivativeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsWedding;
use Tests\TestCase;

class MasterSecurityRemediationTest extends TestCase
{
    use BuildsWedding, RefreshDatabase;

    public function test_sync_owners_removes_all_owners_when_empty(): void
    {
        $admin = User::factory()->superadmin()->create();
        $owner = User::factory()->owner()->create();
        $event = $this->makeEvent();
        $event->owners()->sync([$owner->id => ['role_label' => 'groom']]);

        $this->assertCount(1, $event->fresh()->owners);

        $payload = array_merge($event->toArray(), [
            'owner_ids' => [],
        ]);

        $response = $this->actingAs($admin)->put(route('admin.events.update', $event), $payload);

        $response->assertRedirect(route('admin.events.show', $event));
        $this->assertCount(0, $event->fresh()->owners);
    }

    public function test_sync_owners_rejects_non_owner_user(): void
    {
        $admin = User::factory()->superadmin()->create();
        $nonOwner = User::factory()->superadmin()->create();
        $event = $this->makeEvent();

        $payload = array_merge($event->toArray(), [
            'owner_ids' => [$nonOwner->id],
        ]);

        $response = $this->actingAs($admin)->put(route('admin.events.update', $event), $payload);

        $response->assertSessionHasErrors('owner_ids');
        $this->assertCount(0, $event->fresh()->owners);
    }

    public function test_password_change_rotates_remember_token_and_logs_audit(): void
    {
        $user = User::factory()->superadmin()->create([
            'password' => Hash::make('OldPassword123!'),
            'remember_token' => 'original-remember-token',
        ]);

        $response = $this->actingAs($user)->put(route('admin.profile.password'), [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ]);

        $response->assertSessionHas('status');
        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword456!', $user->password));
        $this->assertNotSame('original-remember-token', $user->remember_token);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'profile.password',
            'user_id' => $user->id,
        ]);
    }

    public function test_guestbook_auto_approve_setting_applied(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event, ['name' => 'Budi']);
        $service = app(GuestbookService::class);

        // Auto approve disabled (default)
        AppSetting::set('guestbook_auto_approve', '0');
        $msgPending = $service->submit($guest, [
            'message' => 'Semoga bahagia selalu!',
            'visibility' => 'public',
        ]);
        $this->assertSame(MessageStatus::Pending, $msgPending->status);

        // Auto approve enabled
        AppSetting::set('guestbook_auto_approve', '1');
        $msgApproved = $service->submit($guest, [
            'message' => 'Selamat dan sukses selalu!',
            'visibility' => 'public',
        ]);
        $this->assertSame(MessageStatus::Approved, $msgApproved->status);
        $this->assertNotNull($msgApproved->reviewed_at);
    }

    public function test_checkin_rejects_inactive_guest(): void
    {
        $officer = User::factory()->superadmin()->create();
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event, ['status' => GuestStatus::Inactive]);
        $token = $this->qrToken($guest);

        $service = app(CheckinService::class);

        // Scan attempt
        $scanResult = $service->scan($token, $officer, $event);
        $this->assertSame('inactive', $scanResult['status']);

        // Manual attempt
        $manualResult = $service->manual($guest, $officer);
        $this->assertSame('inactive', $manualResult['status']);

        $this->assertNull($guest->fresh()->checked_in_at);
        $this->assertDatabaseMissing('checkins', ['guest_id' => $guest->id]);
    }

    public function test_checkin_rejects_blocked_guest(): void
    {
        $officer = User::factory()->superadmin()->create();
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event, ['status' => GuestStatus::Blocked]);
        $token = $this->qrToken($guest);

        $service = app(CheckinService::class);

        $scanResult = $service->scan($token, $officer, $event);
        $this->assertSame('blocked', $scanResult['status']);

        $manualResult = $service->manual($guest, $officer);
        $this->assertSame('blocked', $manualResult['status']);

        $this->assertNull($guest->fresh()->checked_in_at);
        $this->assertDatabaseMissing('checkins', ['guest_id' => $guest->id]);
    }

    public function test_checkin_rejects_soft_deleted_guest(): void
    {
        $officer = User::factory()->superadmin()->create();
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->qrToken($guest);
        $guest->delete();

        $service = app(CheckinService::class);
        $result = $service->scan($token, $officer, $event);

        $this->assertSame('invalid', $result['status']);
        $this->assertDatabaseMissing('checkins', ['guest_id' => $guest->id]);
    }

    public function test_schedule_sync_preserves_stable_ids(): void
    {
        $event = $this->makeEvent();
        $schedule1 = EventSchedule::create([
            'event_id' => $event->id,
            'title' => 'Akad Nikah',
            'start_time' => now()->addHours(2),
            'sort_order' => 0,
        ]);
        $schedule2 = EventSchedule::create([
            'event_id' => $event->id,
            'title' => 'Resepsi Lama',
            'start_time' => now()->addHours(4),
            'sort_order' => 1,
        ]);

        $service = app(EventService::class);
        $admin = User::factory()->superadmin()->create();

        // Update schedule1 title, delete schedule2, add new schedule3
        $payload = array_merge($event->toArray(), [
            'schedules' => [
                [
                    'id' => $schedule1->id,
                    'title' => 'Akad Nikah Utama',
                    'start_time' => now()->addHours(2)->format('Y-m-d H:i:s'),
                ],
                [
                    'title' => 'Resepsi Baru',
                    'start_time' => now()->addHours(5)->format('Y-m-d H:i:s'),
                ],
            ],
        ]);
        $service->update($event, $payload, $admin);

        $schedules = $event->fresh()->schedules()->orderBy('sort_order')->get();
        $this->assertCount(2, $schedules);

        // Schedule 1 retained its stable ID
        $this->assertSame($schedule1->id, $schedules[0]->id);
        $this->assertSame('Akad Nikah Utama', $schedules[0]->title);

        // Schedule 2 was removed
        $this->assertFalse($schedules->contains('id', $schedule2->id));

        // New schedule was created with new ID
        $this->assertSame('Resepsi Baru', $schedules[1]->title);
        $this->assertNotSame($schedule1->id, $schedules[1]->id);
    }

    public function test_media_derivatives_strip_exif_and_generate_private_renditions(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $event = $this->makeEvent();
        $user = User::factory()->superadmin()->create();

        // Create a small test image
        $image = imagecreatetruecolor(200, 200);
        $bg = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, 200, 200, $bg);
        ob_start();
        imagejpeg($image);
        $content = ob_get_clean();
        imagedestroy($image);

        $path = "events/{$event->id}/gallery/test.jpg";
        Storage::disk('local')->put($path, $content);

        $asset = MediaAsset::create([
            'event_id' => $event->id,
            'disk' => 'local',
            'uploaded_by' => $user->id,
            'path' => $path,
            'original_name' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => strlen($content),
            'status' => 'approved',
        ]);

        $derivativeService = app(MediaDerivativeService::class);
        $updates = $derivativeService->generate($asset);

        $this->assertNotNull($updates['thumbnail_path']);
        $this->assertNotNull($updates['medium_path']);
        Storage::disk('local')->assertExists($updates['thumbnail_path']);
        Storage::disk('local')->assertExists($updates['medium_path']);
        Storage::disk('public')->assertMissing($updates['thumbnail_path']);
    }

    public function test_derivative_generation_applies_predecode_pixel_guard(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $event = $this->makeEvent();
        $user = User::factory()->superadmin()->create();

        // A PNG header declaring 8000 x 8000 (64 megapixels > 36 megapixel budget)
        // must be skipped before allocating a GD canvas.
        $width = 8000;
        $height = 8000;
        $ihdrData = pack('NN', $width, $height).pack('NN', 8, 2).pack('CCC', 0, 0, 0);
        $oversized = "\x89PNG\r\n\x1a\n"
            .pack('N', 13).'IHDR'.$ihdrData.pack('N', crc32('IHDR'.$ihdrData))
            .pack('N', 0).'IEND'.pack('N', crc32('IEND'));

        $this->assertSame([8000, 8000], array_slice(getimagesizefromstring($oversized), 0, 2));

        $path = "events/{$event->id}/gallery/oversized.png";
        Storage::disk('local')->put($path, $oversized);

        $asset = MediaAsset::create([
            'event_id' => $event->id,
            'disk' => 'local',
            'uploaded_by' => $user->id,
            'path' => $path,
            'original_name' => 'oversized.png',
            'mime_type' => 'image/png',
            'size_bytes' => strlen($oversized),
            'status' => 'approved',
        ]);

        $updates = app(MediaDerivativeService::class)->generate($asset);

        $this->assertNull($updates['thumbnail_path']);
        $this->assertNull($updates['medium_path']);
        $this->assertNull($asset->fresh()->thumbnail_path);
    }

    public function test_purge_command_removes_trashed_records_and_files(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $guest->delete();
        Guest::withTrashed()->where('id', $guest->id)->update(['deleted_at' => now()->subDays(40)]);

        $msg = GuestbookMessage::create([
            'event_id' => $event->id,
            'guest_id' => $guest->id,
            'sender_name' => $guest->name,
            'message' => 'Ucapan untuk dihapus',
            'status' => MessageStatus::Pending,
        ]);
        $msg->delete();
        GuestbookMessage::withTrashed()->where('id', $msg->id)->update(['deleted_at' => now()->subDays(40)]);

        $user = User::factory()->superadmin()->create();
        $filePath = "events/{$event->id}/gallery/purge_test.jpg";
        Storage::disk('public')->put($filePath, 'fake-image-content');
        $asset = MediaAsset::create([
            'event_id' => $event->id,
            'disk' => 'public',
            'uploaded_by' => $user->id,
            'path' => $filePath,
            'original_name' => 'purge_test.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 18,
            'status' => 'approved',
        ]);
        $asset->delete();
        MediaAsset::withTrashed()->where('id', $asset->id)->update(['deleted_at' => now()->subDays(40)]);

        $this->artisan('vowspace:purge-trashed', ['--days' => 30])
            ->assertSuccessful();

        $this->assertDatabaseMissing('guests', ['id' => $guest->id]);
        $this->assertDatabaseMissing('guestbook_messages', ['id' => $msg->id]);
        $this->assertDatabaseMissing('media_assets', ['id' => $asset->id]);
        Storage::disk('public')->assertMissing($filePath);
    }
}
