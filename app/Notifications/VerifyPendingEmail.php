<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyPendingEmail extends Notification
{
    public function __construct(
        public User $user,
        public string $newEmail,
        public string $rawToken,
    ) {}

    /**
     * @return array<int,string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = (int) config('vowspace.tokens.email_change_ttl_minutes', 60);

        return (new MailMessage)
            ->subject('Verifikasi email baru VOWSPACE')
            ->line('Kami menerima permintaan untuk mengubah email akun VOWSPACE Anda.')
            ->action('Verifikasi email baru', route('email.change.confirm', ['token' => $this->rawToken]))
            ->line("Tautan berlaku {$minutes} menit dan hanya dapat digunakan sekali.")
            ->line('Jika Anda tidak meminta perubahan ini, abaikan email ini. Email login Anda tidak berubah.');
    }
}
