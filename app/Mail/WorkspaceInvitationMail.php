<?php

namespace App\Mail;

use App\Models\WorkspaceInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkspaceInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $rawToken  Only used in the view to build the acceptance URL; NOT stored.
     */
    public function __construct(
        public readonly WorkspaceInvitation $invitation,
        public readonly string $rawToken,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->invitation->type->value === 'couple'
            ? 'Undangan Akses Event dari '.$this->invitation->organization->name
            : 'Undangan Bergabung ke Workspace '.$this->invitation->organization->name;

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.workspace-invitation');
    }

    public function attachments(): array
    {
        return [];
    }
}
