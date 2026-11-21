<?php

namespace Tests\Feature;

use App\Models\Checkin;
use App\Models\Event;
use App\Models\GuestToken;
use App\Models\User;
use App\Services\InvitationService;
use App\Services\QrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWedding;
use Tests\TestCase;

/**
 * End-to-end coverage of the guest QR pass chain:
 * generate -> render -> (download) -> scan -> check in.
 *
 * The QR payload is a bare QR-type token, not a guest-facing URL. It is
 * consumed by the staff scanner; only Invitation-type tokens resolve the
 * guest-facing `/invite/{token}` routes. The two token types must never be
 * interchangeable, so the separation is asserted explicitly below.
 */
class QrPassChainTest extends TestCase
{
    use BuildsWedding, RefreshDatabase;

    public function test_qr_service_renders_svg_and_png(): void
    {
        $qr = app(QrService::class);

        $svg = $qr->svg('probe-payload');
        $this->assertStringContainsString('<svg', $svg, 'SVG render failed');

        $png = $qr->pngBinary('probe-payload');
        $this->assertSame("\x89PNG", substr($png, 0, 4), 'PNG magic bytes missing');

        $uri = $qr->pngDataUri('probe-payload');
        $this->assertStringStartsWith('data:image/png;base64,', $uri);
    }

    public function test_admin_invitation_page_renders_the_guest_qr(): void
    {
        $admin = User::factory()->superadmin()->create();
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        app(InvitationService::class)->issueForGuest($guest);

        $response = $this->actingAs($admin)->get(route('admin.invitations.show', $guest));

        $response->assertOk();
        $this->assertStringContainsString('<svg', $response->getContent(), 'admin invitation QR not rendered');
    }

    public function test_guest_pass_page_renders_the_qr_for_an_invitation_token(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $response = $this->get(route('guest.pass', $token));

        $response->assertOk();
        $this->assertStringContainsString('<svg', $response->getContent(), 'guest pass QR not rendered');
    }

    public function test_guest_pass_download_returns_a_png(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $response = $this->get(route('guest.pass.download', $token));

        $response->assertOk();
        $this->assertSame("\x89PNG", substr($response->streamedContent(), 0, 4), 'downloaded pass is not a PNG');
    }

    public function test_qr_token_is_accepted_by_the_staff_scanner(): void
    {
        $admin = User::factory()->superadmin()->create();
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event, ['rsvp_status' => 'confirmed']);
        $qr = $this->qrToken($guest);

        $this->actingAs($admin)
            ->postJson(route('admin.checkins.scan'), ['token' => $qr, 'event_id' => $event->id])
            ->assertOk()
            ->assertJson(['status' => 'success']);
    }

    public function test_qr_token_does_not_open_the_guest_facing_pass_url(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $qr = $this->qrToken($guest);

        // A QR-type token is a scanner credential, not an invitation link.
        // Allowing it to open the pass page would blur the two token scopes.
        $this->get(route('guest.pass', $qr))->assertNotFound();
    }

    /**
     * Regression: generating a QR for a finished event reported success while
     * minting an already-expired token, so the page still showed "QR belum
     * tersedia" and the existing invitation link was silently invalidated.
     */
    public function test_qr_regeneration_is_refused_for_a_finished_event(): void
    {
        $admin = User::factory()->superadmin()->create();
        $event = Event::factory()->completed()->create();
        $guest = $this->makeGuest($event);
        app(InvitationService::class)->issueForGuest($guest);
        $guest->refresh();

        $invitationId = $guest->activeInvitationToken()?->id;
        $this->assertNotNull($invitationId);

        $this->actingAs($admin)
            ->from(route('admin.invitations.show', $guest))
            ->post(route('admin.invitations.regenerate', $guest))
            ->assertRedirect(route('admin.invitations.show', $guest))
            ->assertSessionHasErrors('qr');

        // The refused request must not revoke the working invitation link.
        $this->assertSame($invitationId, $guest->fresh()->activeInvitationToken()?->id);
    }

    public function test_admin_invitation_page_explains_a_blocked_qr_for_a_finished_event(): void
    {
        $admin = User::factory()->superadmin()->create();
        $event = Event::factory()->completed()->create();
        $guest = $this->makeGuest($event);
        app(InvitationService::class)->issueForGuest($guest);

        $this->actingAs($admin)
            ->get(route('admin.invitations.show', $guest))
            ->assertOk()
            ->assertSee('QR pass tidak dapat diterbitkan karena acara sudah berakhir', false)
            ->assertDontSee('Generate QR pass');
    }

    public function test_qr_regeneration_works_for_an_upcoming_event(): void
    {
        $admin = User::factory()->superadmin()->create();
        $event = Event::factory()->create();
        $guest = $this->makeGuest($event);
        app(InvitationService::class)->issueForGuest($guest);

        $this->actingAs($admin)
            ->from(route('admin.invitations.show', $guest))
            ->post(route('admin.invitations.regenerate', $guest))
            ->assertRedirect(route('admin.invitations.show', $guest))
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->get(route('admin.invitations.show', $guest))
            ->assertOk()
            ->assertSee('QR ini hanya untuk check-in', false)
            ->assertDontSee('QR belum tersedia');
    }

    public function test_guest_pass_page_shows_the_short_checkin_code(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);
        $code = app(InvitationService::class)->qrShortCode($guest);

        $this->assertNotNull($code);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/', $code);

        $this->get(route('guest.pass', $token))
            ->assertOk()
            ->assertSee('Tunjukkan QR ini kepada petugas registrasi', false)
            ->assertSee($code, false)
            ->assertDontSee('Pindai QR ini pada meja registrasi', false);
    }

    public function test_staff_scanner_accepts_the_short_checkin_code(): void
    {
        $admin = User::factory()->superadmin()->create();
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event, ['rsvp_status' => 'confirmed']);
        $this->invitationToken($guest);
        $code = (string) app(InvitationService::class)->qrShortCode($guest);

        $typed = strtolower(str_replace('-', ' ', $code));

        $this->actingAs($admin)
            ->postJson(route('admin.checkins.scan'), ['token' => $typed, 'event_id' => $event->id])
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $guest->refresh();
        $this->assertNotNull($guest->checked_in_at);
        $this->assertDatabaseHas('checkins', ['guest_id' => $guest->id, 'event_id' => $event->id]);
    }

    public function test_short_checkin_code_cannot_check_in_twice(): void
    {
        $admin = User::factory()->superadmin()->create();
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event, ['rsvp_status' => 'confirmed']);
        $this->invitationToken($guest);
        $code = (string) app(InvitationService::class)->qrShortCode($guest);

        $this->actingAs($admin)
            ->postJson(route('admin.checkins.scan'), ['token' => $code, 'event_id' => $event->id])
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->actingAs($admin)
            ->postJson(route('admin.checkins.scan'), ['token' => $code, 'event_id' => $event->id])
            ->assertOk()
            ->assertJson(['status' => 'duplicate']);

        $this->assertSame(1, Checkin::where('guest_id', $guest->id)->count());
    }

    public function test_short_code_is_unique_per_qr_token(): void
    {
        $event = $this->makeEvent();
        $first = $this->makeGuest($event);
        $second = $this->makeGuest($event);

        $this->qrToken($first);
        $this->qrToken($second);

        $codes = GuestToken::where('type', 'qr')->pluck('short_code')->filter();

        $this->assertCount(2, $codes);
        $this->assertSame($codes->unique()->count(), $codes->count());
    }
}
