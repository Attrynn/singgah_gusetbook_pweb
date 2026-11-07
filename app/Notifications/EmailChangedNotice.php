<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailChangedNotice extends Notification
{
    public function __construct(
        public string $oldEmail,
        public string $newEmail,
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
        return (new MailMessage)
            ->subject('Email akun VOWSPACE Anda telah diubah')
            ->line("Email akun VOWSPACE Anda baru saja diubah menjadi {$this->newEmail}.")
            ->line('Jika Anda tidak melakukan perubahan ini, segera hubungi administrator dan ganti password Anda.');
    }
}
