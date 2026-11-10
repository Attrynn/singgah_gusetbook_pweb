<?php

namespace App\Notifications;

use App\Models\EventAnnouncement;
use Illuminate\Notifications\Notification;

class EventAnnouncementPublished extends Notification
{
    public function __construct(public readonly EventAnnouncement $announcement) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'event_announcement',
            'announcement_id' => $this->announcement->id,
            'event_id' => $this->announcement->event_id,
            'title' => $this->announcement->title,
            'body' => mb_strimwidth((string) $this->announcement->body, 0, 240, '…'),
            'priority' => $this->announcement->priority->value,
            'requires_acknowledgement' => $this->announcement->requires_acknowledgement,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
