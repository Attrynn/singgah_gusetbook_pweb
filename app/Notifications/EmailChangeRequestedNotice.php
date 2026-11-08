<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailChangeRequestedNotice extends Notification
{
    public function __construct(
        public string $oldEmail,
        public string $pendingEmail,
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
            ->subject('Permintaan perubahan email VOWSPACE')
            ->line('Seseorang meminta untuk mengubah email login akun VOWSPACE Anda.')
            ->line("Email login saat ini tetap {$this->oldEmail} sampai alamat baru diverifikasi.")
            ->line('Jika Anda tidak meminta perubahan ini, segera ganti password Anda dan hubungi administrator.');
    }
}
