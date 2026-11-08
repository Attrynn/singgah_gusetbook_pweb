<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_security_policy_is_enforced_with_a_per_response_nonce(): void
    {
        $response = $this->get('/login');

        $response->assertOk();

        $policy = (string) $response->headers->get('Content-Security-Policy');

        $this->assertNotSame('', $policy);
        $this->assertNull($response->headers->get('Content-Security-Policy-Report-Only'));
        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString('nonce-', $policy);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $policy);
        $this->assertStringContainsString('frame-ancestors', $policy);

        preg_match('/nonce-([A-Za-z0-9]+)/', $policy, $matches);

        $this->assertNotEmpty($matches[1] ?? null, 'CSP must carry a nonce.');
        $this->assertStringContainsString('nonce="'.$matches[1].'"', $response->getContent());
    }

    public function test_each_response_uses_a_different_nonce(): void
    {
        $first = $this->get('/login')->headers->get('Content-Security-Policy');
        $second = $this->get('/login')->headers->get('Content-Security-Policy');

        preg_match('/nonce-([A-Za-z0-9]+)/', $first, $a);
        preg_match('/nonce-([A-Za-z0-9]+)/', $second, $b);

        $this->assertNotSame($a[1], $b[1]);
    }

    public function test_permissions_policy_allows_same_origin_camera_only(): void
    {
        $header = (string) $this->get('/login')->headers->get('Permissions-Policy');

        $this->assertStringContainsString('camera=(self)', $header);
        $this->assertStringNotContainsString('camera=()', $header);
        $this->assertStringContainsString('microphone=()', $header);
        $this->assertStringContainsString('geolocation=()', $header);
    }

    public function test_scanner_page_renders_the_alpine_scanner_component(): void
    {
        $admin = User::factory()->superadmin()->create();

        $response = $this->actingAs($admin)->get(route('admin.checkins.scanner'));

        $response->assertOk();
        $this->assertStringContainsString('x-data="scanner"', $response->getContent());
        $this->assertStringContainsString('data-scan-url="'.route('admin.checkins.scan').'"', $response->getContent());
        $this->assertStringContainsString('Mulai Kamera', $response->getContent());
        $this->assertStringContainsString('Tempel token QR', $response->getContent());
    }

    public function test_tokenised_pages_are_not_cached_or_referred(): void
    {
        $response = $this->get('/email/verify/example-token');

        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
    }

    public function test_inline_handlers_are_not_used_in_blade_views(): void
    {
        $offenders = [];

        foreach (glob(resource_path('views').'/{,**/}*.blade.php', GLOB_BRACE) as $file) {
            if (preg_match('/\son(click|change|submit|load|input|keyup|keydown)=/i', (string) file_get_contents($file))) {
                $offenders[] = str_replace(resource_path('views').'/', '', $file);
            }
        }

        $this->assertSame([], $offenders, 'Inline event handlers are blocked by the enforced CSP.');
    }
}
