<?php

namespace Database\Factories;

use App\Enums\TokenType;
use App\Models\Event;
use App\Models\GuestPartyMembership;
use App\Models\GuestPass;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * @extends Factory<GuestPass>
 */
class GuestPassFactory extends Factory
{
    protected $model = GuestPass::class;

    public function definition(): array
    {
        $raw = Str::random(64);

        return [
            'guest_party_membership_id' => GuestPartyMembership::factory(),
            'event_id' => fn (array $attributes) => GuestPartyMembership::find($attributes['guest_party_membership_id'])?->event_id ?? Event::factory(),
            'type' => TokenType::Invitation,
            'token_hash' => GuestPass::hashToken($raw),
            'token_ciphertext' => Crypt::encryptString($raw),
            'token_prefix' => substr($raw, 0, 8),
            'expires_at' => now()->addDays(365),
            'revoked_at' => null,
            'last_used_at' => null,
            'status' => 'active',
        ];
    }

    public function forMember(GuestPartyMembership $member, TokenType $type = TokenType::Invitation): static
    {
        $raw = Str::random(64);

        return $this->state(fn () => [
            'guest_party_membership_id' => $member->id,
            'event_id' => $member->event_id,
            'type' => $type,
            'token_hash' => GuestPass::hashToken($raw),
            'token_ciphertext' => Crypt::encryptString($raw),
            'token_prefix' => substr($raw, 0, 8),
        ]);
    }

    public function qr(): static
    {
        return $this->state(fn () => ['type' => TokenType::Qr]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'revoked_at' => now(),
            'status' => 'revoked',
        ]);
    }
}
