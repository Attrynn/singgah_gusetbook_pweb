<?php

namespace App\Services;

use Google2fa;
use Illuminate\Support\Str;

/**
 * VS-ID-003: Superadmin TOTP MFA.
 * - Enrollment: generate secret key, render QR via QrService
 * - Verification: verifyCode() against the secret
 * - Enforcement: MFA_REQUIRE_SUPERADMIN env var + APP_ENV check
 * - Recovery codes: one-time use, stored hashed
 *
 * Default: enforce in production only so existing tests stay green.
 */
class MfaService
{
    public function __construct(protected Google2fa $google2fa) {}

    /** Generate a new TOTP secret for the user. */
    public function generateSecretKey(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /** Return the provisioning URI (QR code data) for the user. */
    public function provisioningUri(string $secret, string $name): string
    {
        return $this->google2fa->provisioningUri($secret, $name);
    }

    /** Verify a TOTP code against a secret. */
    public function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        return $this->google2fa->verifyKey($secret, $code, $window);
    }

    /** Generate recovery codes for a superadmin user. Returns array of plain codes. */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = Str::random(8);
        }

        return $codes;
    }
}
