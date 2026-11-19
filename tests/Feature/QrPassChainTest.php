<?php

namespace Tests\Feature;

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
}
