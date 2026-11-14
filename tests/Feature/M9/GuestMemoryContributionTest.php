<?php

namespace Tests\Feature\M9;

use App\Enums\EventStatus;
use App\Enums\MediaStatus;
use App\Enums\MemoryItemType;
use App\Enums\MessageStatus;
use App\Enums\MessageVisibility;
use App\Models\GuestbookMessage;
use App\Models\MediaAsset;
use App\Models\MemoryConsent;
use App\Services\MemoryContributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsWedding;
use Tests\Feature\M9\Concerns\BuildsMemory;
use Tests\TestCase;

class GuestMemoryContributionTest extends TestCase
{
    use BuildsMemory, BuildsWedding, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function contributionPayload(array $overrides = []): array
    {
        return array_merge([
            'files' => [UploadedFile::fake()->image('momen.jpg')],
            'caption' => 'Momen pertama bersama keluarga.',
            'accept_terms' => 1,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function messagePayload(array $overrides = []): array
    {
        return array_merge([
            'sender_name' => 'Sinta',
            'message' => 'Selamat menempuh hidup baru, semoga langgeng hingga kakek nenek.',
            'accept_terms' => 1,
        ], $overrides);
    }

    public function test_guest_with_capability_can_open_the_contribution_page(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $quota = app(MemoryContributionService::class)->remainingQuota($event, $guest);

        $this->get('/invite/'.$token.'/memories')
            ->assertOk()
            ->assertSee('Sisa kuota: '.$quota.' foto');
    }

    public function test_contribution_page_is_forbidden_when_submissions_are_closed(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->memorySettings($event, ['submissions_open' => false]);

        $this->get('/invite/'.$token.'/memories')->assertForbidden();
    }

    public function test_contribution_page_requires_a_valid_token(): void
    {
        $this->get('/invite/invalid-token/memories')->assertNotFound();
    }

    public function test_photo_upload_creates_pending_asset_and_consent(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/memories', $this->contributionPayload())
            ->assertSessionHasNoErrors();

        $asset = MediaAsset::query()->firstOrFail();

        $this->assertSame($event->id, $asset->event_id);
        $this->assertSame($guest->id, $asset->guest_id);
        $this->assertNull($asset->guest_party_membership_id);
        $this->assertSame(MediaStatus::Pending->value, $asset->status->value);
        $this->assertSame('image/jpeg', $asset->mime_type);
        $this->assertGreaterThan(0, $asset->width);
        $this->assertGreaterThan(0, $asset->height);
        $this->assertSame(64, strlen($asset->checksum));
        $this->assertStringStartsWith('events/'.$event->id.'/memory/', $asset->path);

        Storage::disk('local')->assertExists($asset->path);

        $consent = MemoryConsent::query()->firstOrFail();

        $this->assertSame($asset->id, $consent->contribution_id);
        $this->assertSame(MemoryItemType::MediaAsset->value, $consent->contribution_type);
        $this->assertTrue($consent->allow_event_display);
        $this->assertFalse($consent->allow_public_display);
        $this->assertSame(config('memory.terms_version'), $consent->terms_version);
    }

    public function test_upload_rejects_a_file_that_is_not_really_an_image(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/memories', [
                'files' => [UploadedFile::fake()->createWithContent('fake.jpg', 'not-an-image')],
                'accept_terms' => 1,
            ])
            ->assertSessionHasErrors('files');

        $this->assertDatabaseCount('media_assets', 0);
        $this->assertDatabaseCount('memory_consents', 0);
    }

    public function test_upload_rejects_an_oversized_file(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/memories', [
                'files' => [UploadedFile::fake()->create('big.jpg', 6000)],
                'accept_terms' => 1,
            ])
            ->assertSessionHasErrors('files.0');

        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_upload_requires_consent(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $payload = $this->contributionPayload();
        unset($payload['accept_terms']);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/memories', $payload)
            ->assertSessionHasErrors('accept_terms');

        $this->assertDatabaseCount('media_assets', 0);
        $this->assertDatabaseCount('memory_consents', 0);
    }

    public function test_upload_rejects_more_files_than_the_configured_limit(): void
    {
        Config::set('memory.guest_max_files', 2);

        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/memories', [
                'files' => [
                    UploadedFile::fake()->image('satu.jpg'),
                    UploadedFile::fake()->image('dua.jpg'),
                    UploadedFile::fake()->image('tiga.jpg'),
                ],
                'accept_terms' => 1,
            ])
            ->assertSessionHasErrors('files');

        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_upload_enforces_the_per_guest_quota(): void
    {
        Config::set('memory.guest_max_per_event', 2);

        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->approvedPhoto($event, $guest);
        $this->approvedPhoto($event, $guest);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/memories', $this->contributionPayload())
            ->assertSessionHasErrors('files');

        $this->assertDatabaseCount('media_assets', 2);
    }

    public function test_repeated_submission_with_the_same_client_id_is_idempotent(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);
        $clientId = (string) Str::uuid();

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/memories', $this->contributionPayload(['client_submission_id' => $clientId]))
            ->assertSessionHasNoErrors();

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/memories', $this->contributionPayload(['client_submission_id' => $clientId]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('media_assets', 1);
        $this->assertDatabaseCount('memory_consents', 1);
    }

    public function test_guest_cannot_exceed_the_rate_limit(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        // The array cache store is rebuilt for every test, but clear the
        // limiter anyway so a shared backend cannot leak state into this test.
        RateLimiter::clear($token.'|127.0.0.1');

        $responses = [];

        for ($attempt = 1; $attempt <= 11; $attempt++) {
            $responses[] = $this->from('/invite/'.$token)
                ->post('/invite/'.$token.'/memories', [
                    'files' => [UploadedFile::fake()->image('momen-'.$attempt.'.jpg')],
                    'accept_terms' => 1,
                    'client_submission_id' => (string) Str::uuid(),
                ]);
        }

        $this->assertTrue(
            collect($responses)->contains(static fn ($response): bool => $response->getStatusCode() === 429),
            'Expected the memory-contribute rate limit to answer 429.'
        );
    }

    public function test_message_submission_creates_pending_message_and_consent(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/memories/message', $this->messagePayload())
            ->assertSessionHasNoErrors();

        $message = GuestbookMessage::query()->firstOrFail();

        $this->assertSame($event->id, $message->event_id);
        $this->assertSame($guest->id, $message->guest_id);
        $this->assertSame('Sinta', $message->sender_name);
        $this->assertSame(MessageVisibility::Public, $message->visibility);
        $this->assertSame(MessageStatus::Pending, $message->status);

        $consent = MemoryConsent::query()->firstOrFail();

        $this->assertSame($message->id, $consent->contribution_id);
        $this->assertSame(MemoryItemType::GuestbookMessage->value, $consent->contribution_type);
    }

    public function test_message_rejects_html_markup(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/memories/message', $this->messagePayload([
                'message' => 'Selamat! <script>alert(1)</script>',
            ]))
            ->assertSessionHasErrors('message');

        $this->assertDatabaseCount('guestbook_messages', 0);
    }

    public function test_message_rejects_urls(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/memories/message', $this->messagePayload([
                'message' => 'Selamat! Kunjungi https://spam.example untuk hadiah.',
            ]))
            ->assertSessionHasErrors('message');

        $this->assertDatabaseCount('guestbook_messages', 0);
    }

    public function test_message_requires_a_minimum_length(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/memories/message', $this->messagePayload(['message' => 'Ok']))
            ->assertSessionHasErrors('message');

        $this->assertDatabaseCount('guestbook_messages', 0);
    }

    public function test_message_respects_the_public_display_consent(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/memories/message', $this->messagePayload([
                'allow_public_display' => false,
            ]))
            ->assertSessionHasNoErrors();

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/memories/message', $this->messagePayload([
                'message' => 'Ucapan kedua untuk kedua mempelai, semoga bahagia.',
                'allow_public_display' => true,
            ]))
            ->assertSessionHasNoErrors();

        $consents = MemoryConsent::query()->orderBy('id')->get();

        $this->assertCount(2, $consents);
        $this->assertFalse($consents[0]->allow_public_display);
        $this->assertTrue($consents[1]->allow_public_display);
    }

    public function test_completed_event_blocks_contribution_by_default(): void
    {
        $event = $this->makeEvent(['status' => EventStatus::Completed]);
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->memorySettings($event, ['post_event_submissions' => false]);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/memories', $this->contributionPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_completed_event_accepts_contribution_when_the_window_is_open(): void
    {
        $event = $this->makeEvent(['status' => EventStatus::Completed]);
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->memorySettings($event, [
            'post_event_submissions' => true,
            'submissions_open' => true,
        ]);

        $this->from('/invite/'.$token.'/memories')
            ->followingRedirects()
            ->post('/invite/'.$token.'/memories', $this->contributionPayload())
            ->assertOk();

        $this->assertDatabaseHas('media_assets', [
            'event_id' => $event->id,
            'guest_id' => $guest->id,
            'status' => MediaStatus::Pending->value,
        ]);
    }

    public function test_closed_submission_blocks_a_published_event(): void
    {
        $event = $this->makeEvent(['status' => EventStatus::Published]);
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->memorySettings($event, ['submissions_open' => false]);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/memories', $this->contributionPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_submission_window_in_the_future_blocks_contribution(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->memorySettings($event, ['submission_opens_at' => now()->addDay()]);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/memories', $this->contributionPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('media_assets', 0);
    }
}
