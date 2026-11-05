<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->get('/owner')->assertRedirect('/login');
    }

    public function test_owner_cannot_access_admin_area(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->get('/admin')->assertForbidden();
        $this->actingAs($owner)->get('/admin/guests')->assertForbidden();
    }

    public function test_superadmin_cannot_access_owner_area(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)->get('/owner')->assertForbidden();
    }

    public function test_owner_can_access_owner_area(): void
    {
        $owner = User::factory()->owner()->create();
        $event = \App\Models\Event::factory()->create();
        $owner->events()->attach($event->id, ['role_label' => 'groom']);

        $this->actingAs($owner)->get('/owner')->assertOk();
    }

    public function test_owner_without_event_is_forbidden_with_message(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->get('/owner')->assertForbidden();
    }

    public function test_superadmin_can_access_admin_area(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_inactive_user_session_is_invalidated(): void
    {
        $admin = User::factory()->superadmin()->inactive()->create();

        $this->actingAs($admin)->get('/admin')->assertRedirect('/login');
        $this->assertGuest();
    }
}
