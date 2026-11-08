<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Undangan Vowspace</title>
    <style>
        body { font-family: sans-serif; color: #1a1a1a; line-height: 1.6; margin: 0; padding: 0; background: #f5f5f5; }
        .container { max-width: 580px; margin: 32px auto; background: #ffffff; border-radius: 8px; padding: 40px; }
        .header { border-bottom: 1px solid #e5e5e5; padding-bottom: 24px; margin-bottom: 24px; }
        .label { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; }
        .value { margin: 2px 0 16px; font-size: 15px; }
        .cta { display: inline-block; margin: 24px 0; padding: 12px 28px; background: #111827; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 600; }
        .expiry { font-size: 13px; color: #6b7280; margin-top: 8px; }
        .footer { margin-top: 32px; padding-top: 24px; border-top: 1px solid #e5e5e5; font-size: 12px; color: #9ca3af; }
        .assignments { list-style: none; padding: 0; margin: 0; }
        .assignments li { padding: 4px 0; font-size: 14px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <p class="label">Undangan dari</p>
        <p class="value"><strong>{{ $invitation->organization->name }}</strong></p>

        <p class="label">Jenis Undangan</p>
        <p class="value">
            @if ($invitation->type->value === 'couple')
                Akses Event (Couple)
            @else
                Bergabung sebagai Staff Workspace
            @endif
        </p>
    </div>

    <p>Halo,</p>

    @if ($invitation->type->value === 'couple')
        <p>
            Anda diundang oleh <strong>{{ $invitation->invitedBy->name }}</strong> untuk mengakses event
            di workspace <strong>{{ $invitation->organization->name }}</strong>.
        </p>
    @else
        <p>
            Anda diundang oleh <strong>{{ $invitation->invitedBy->name }}</strong> untuk bergabung ke workspace
            <strong>{{ $invitation->organization->name }}</strong> sebagai staff.
        </p>
    @endif

    @if (!empty($invitation->event_assignments))
        <p class="label">Penugasan Event</p>
        <ul class="assignments">
            @foreach ($invitation->event_assignments as $assignment)
                @php
                    $event = \App\Models\Event::find($assignment['event_id']);
                    $eventName = $event?->name ?? "Event #{$assignment['event_id']}";
                @endphp
                <li>
                    &bull; {{ $eventName }}
                    @if (!empty($assignment['role']))
                        &mdash;
                        {{ \App\Enums\EventStaffRole::tryFrom($assignment['role'])?->label() ?? $assignment['role'] }}
                    @elseif (!empty($assignment['role_label']))
                        &mdash; {{ ucfirst($assignment['role_label']) }}
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <a href="{{ route('invitations.workspace.accept', ['token' => $rawToken]) }}" class="cta">
        Terima Undangan
    </a>

    <p class="expiry">
        Undangan ini berlaku hingga
        <strong>{{ $invitation->expires_at->translatedFormat('d F Y, H:i') }} WIB</strong>.
        Setelah tanggal tersebut, tautan ini tidak lagi dapat digunakan.
    </p>

    <div class="footer">
        <p>Jika Anda tidak mengharapkan undangan ini, abaikan email ini.</p>
        <p>Email ini dikirim oleh sistem Vowspace. Jangan membalas email ini.</p>
        <p>
            Atau salin tautan berikut ke browser Anda:<br>
            {{ route('invitations.workspace.accept', ['token' => $rawToken]) }}
        </p>
    </div>
</div>
</body>
</html>
