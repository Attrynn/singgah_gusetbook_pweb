<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\EmailChangedNotice;
use App\Notifications\VerifyPendingEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmailChangeService
{
    public function __construct(protected AuditService $audit) {}

    /**
     * Store the requested address as pending and mail a single-use token to it.
     * The current, verified address remains the login identity.
     */
    public function request(User $user, string $newEmail): void
    {
        $newEmail = Str::lower(trim($newEmail));

        if ($newEmail === Str::lower((string) $user->email)) {
            return;
        }

        $this->assertEmailAvailable($user, $newEmail);

        $raw = Str::random(64);

        DB::transaction(function () use ($user, $newEmail, $raw): void {
            $user->forceFill([
                'pending_email' => $newEmail,
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

        Notification::route('mail', $newEmail)->notify(new VerifyPendingEmail($user, $newEmail, $raw));
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

        Notification::route('mail', $oldEmail)->notify(new EmailChangedNotice($oldEmail, $user->email));

        return $user;
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
