<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\EmailChangedNotice;
use App\Notifications\EmailChangeRequestedNotice;
use App\Notifications\VerifyPendingEmail;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class EmailChangeService
{
    public function __construct(protected AuditService $audit) {}

    /**
     * Step-up, store the requested address as pending and mail a single-use
     * token to it. The current verified address remains the login identity
     * until the POST confirmation consumes the token.
     *
     * @return array{delivered:bool}
     */
    public function request(User $user, string $newEmail, string $currentPassword): array
    {
        $newEmail = Str::lower(trim($newEmail));

        if ($newEmail === Str::lower((string) $user->email)) {
            return ['delivered' => true];
        }

        $this->assertCurrentPassword($user, $currentPassword);
        $this->assertEmailAvailable($user, $newEmail);

        $raw = Str::random(64);
        $oldEmail = (string) $user->email;

        try {
            DB::transaction(function () use ($user, $newEmail, $raw): void {
                $user->forceFill([
                    'pending_email' => $newEmail,
                    // A new request replaces any older outstanding token.
                    'email_change_token_hash' => hash('sha256', $raw),
                    'email_change_expires_at' => now()->addMinutes(
                        (int) config('vowspace.tokens.email_change_ttl_minutes', 60)
                    ),
                ])->save();

                $this->audit->recordCritical(
                    'profile.email_change_requested',
                    'Permintaan perubahan email dikirim untuk verifikasi',
                    null,
                    $user,
                    $user,
                    ['pending_email' => $newEmail]
                );
            });
        } catch (UniqueConstraintViolationException) {
            // Another account claimed the address between the check and save.
            throw ValidationException::withMessages([
                'email' => 'Email tersebut sudah digunakan atau sedang menunggu verifikasi akun lain.',
            ]);
        }

        return ['delivered' => $this->deliverVerification($user, $newEmail, $raw, $oldEmail)];
    }

    /**
     * Reissue the verification mail for the address already pending. The
     * previous token is invalidated by rotation.
     *
     * @return array{delivered:bool}
     */
    public function resend(User $user, string $currentPassword): array
    {
        $this->assertCurrentPassword($user, $currentPassword);

        $newEmail = Str::lower((string) $user->pending_email);

        if ($newEmail === '') {
            throw ValidationException::withMessages([
                'email' => 'Tidak ada perubahan email yang menunggu verifikasi.',
            ]);
        }

        $this->assertEmailAvailable($user, $newEmail);

        $raw = Str::random(64);
        $oldEmail = (string) $user->email;

        DB::transaction(function () use ($user, $raw): void {
            $user->forceFill([
                'email_change_token_hash' => hash('sha256', $raw),
                'email_change_expires_at' => now()->addMinutes(
                    (int) config('vowspace.tokens.email_change_ttl_minutes', 60)
                ),
            ])->save();

            $this->audit->recordCritical(
                'profile.email_change_resent',
                'Tautan verifikasi perubahan email dikirim ulang',
                null,
                $user,
                $user,
                ['pending_email' => $user->pending_email]
            );
        });

        return ['delivered' => $this->deliverVerification($user, $newEmail, $raw, $oldEmail)];
    }

    /**
     * Read-only lookup for the confirmation screen. Never changes state and
     * never consumes the token, but does clean up expired rows so the
     * database stays tidy between requests.
     */
    public function preview(string $raw): ?User
    {
        $hash = hash('sha256', $raw);

        $user = User::where('email_change_token_hash', $hash)->first();

        if (! $user || ! $user->pending_email || ! $user->email_change_expires_at) {
            return null;
        }

        // Also clean the row so it doesn't linger with a null expires_at later.
        if ($user->email_change_expires_at->isPast()) {
            User::whereKey($user->getKey())->update([
                'pending_email' => null,
                'email_change_token_hash' => null,
                'email_change_expires_at' => null,
            ]);

            return null;
        }

        return $user;
    }

    /**
     * Atomically confirm the pending address. The token is single-use and
     * expires, and the previous address is notified after the change commits.
     */
    public function confirm(string $raw): User
    {
        $hash = hash('sha256', $raw);

        $candidate = User::where('email_change_token_hash', $hash)->first();

        if (! $candidate || ! $candidate->pending_email || ! $candidate->email_change_expires_at) {
            throw ValidationException::withMessages([
                'token' => 'Tautan verifikasi tidak valid atau sudah digunakan.',
            ]);
        }

        // Expiry cleanup must commit before the validation error is raised,
        // otherwise the surrounding transaction would roll the cleanup back.
        if ($candidate->email_change_expires_at->isPast()) {
            User::whereKey($candidate->getKey())->update([
                'pending_email' => null,
                'email_change_token_hash' => null,
                'email_change_expires_at' => null,
            ]);

            throw ValidationException::withMessages([
                'token' => 'Tautan verifikasi sudah kedaluwarsa. Ajukan perubahan email ulang.',
            ]);
        }

        [$user, $oldEmail] = DB::transaction(function () use ($hash): array {
            /** @var User|null $user */
            $user = User::where('email_change_token_hash', $hash)->lockForUpdate()->first();

            if (! $user || ! $user->pending_email || ! $user->email_change_expires_at) {
                throw ValidationException::withMessages([
                    'token' => 'Tautan verifikasi tidak valid atau sudah digunakan.',
                ]);
            }

            if ($user->email_change_expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'token' => 'Tautan verifikasi sudah kedaluwarsa. Ajukan perubahan email ulang.',
                ]);
            }

            $newEmail = Str::lower($user->pending_email);

            if (User::withTrashed()->where('email', $newEmail)->whereKeyNot($user->getKey())->exists()) {
                throw ValidationException::withMessages([
                    'token' => 'Email tujuan sudah digunakan akun lain.',
                ]);
            }

            $oldEmail = $user->email;

            $user->forceFill([
                'email' => $newEmail,
                'email_verified_at' => now(),
                'pending_email' => null,
                'email_change_token_hash' => null,
                'email_change_expires_at' => null,
                'remember_token' => Str::random(60),
            ])->save();

            $this->audit->recordCritical(
                'profile.email_changed',
                "Email akun diubah dari {$oldEmail} menjadi {$newEmail}",
                null,
                $user,
                $user,
                ['old_email' => $oldEmail, 'new_email' => $newEmail]
            );

            return [$user, $oldEmail];
        });

        try {
            Notification::route('mail', $oldEmail)->notify(new EmailChangedNotice($oldEmail, $user->email));
        } catch (Throwable $e) {
            // The identity change is already committed and audited; the notice
            // is retried from the monitoring backlog rather than rolled back.
            report($e);
        }

        return $user;
    }

    protected function deliverVerification(User $user, string $newEmail, string $raw, string $oldEmail): bool
    {
        try {
            Notification::route('mail', $newEmail)->notify(new VerifyPendingEmail($user, $newEmail, $raw));

            // The verified address is warned even if the change never completes.
            Notification::route('mail', $oldEmail)->notify(
                new EmailChangeRequestedNotice($oldEmail, $newEmail)
            );

            return true;
        } catch (Throwable $e) {
            report($e);
            Log::warning('Email change verification delivery failed', ['user_id' => $user->id]);

            return false;
        }
    }

    protected function assertCurrentPassword(User $user, string $password): void
    {
        if (! Hash::check($password, (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Password saat ini tidak sesuai.',
            ]);
        }
    }

    protected function assertEmailAvailable(User $user, string $email): void
    {
        $taken = User::withTrashed()
            ->where(fn ($query) => $query->where('email', $email)->orWhere('pending_email', $email))
            ->whereKeyNot($user->getKey())
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'email' => 'Email tersebut sudah digunakan atau sedang menunggu verifikasi akun lain.',
            ]);
        }
    }
}
