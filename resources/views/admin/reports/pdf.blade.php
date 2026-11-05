<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan {{ $event->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #253447; font-size: 12px; margin: 0; padding: 32px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        h2 { font-size: 14px; margin: 24px 0 8px; color: #253447; }
        .muted { color: #64748B; }
        .kicker { font-size: 10px; letter-spacing: 2px; text-transform: uppercase; color: #94A3B8; }
        .header { border-bottom: 2px solid #DCE5EF; padding-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { text-align: left; padding: 7px 8px; border-bottom: 1px solid #E9EFF6; font-size: 11px; }
        th { background: #F1F5F9; font-weight: bold; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px; font-size: 9px; }
        .stats { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .stats td { width: 25%; padding: 10px; border: 1px solid #E9EFF6; }
        .stats .label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; color: #94A3B8; }
        .stats .value { font-size: 18px; font-weight: bold; margin-top: 4px; }
        .footer { margin-top: 32px; border-top: 1px solid #E9EFF6; padding-top: 10px; font-size: 9px; color: #94A3B8; }
    </style>
</head>
<body>
    <div class="header">
        <p class="kicker">VOWSPACE · Laporan Event</p>
        <h1>{{ $event->name }}</h1>
        <p class="muted">
            {{ $event->coupleNames() }} · {{ $event->event_date?->translatedFormat('d F Y') }}
            @if ($event->venue_name) · {{ $event->venue_name }} @endif
        </p>
    </div>

    <h2>Ringkasan</h2>
    <table class="stats">
        <tr>
            <td><div class="label">Total tamu</div><div class="value">{{ $stats['total_guests'] }}</div></td>
            <td><div class="label">Konfirmasi hadir</div><div class="value">{{ $stats['confirmed'] }}</div></td>
            <td><div class="label">Check-in</div><div class="value">{{ $stats['checked_in'] }}</div></td>
            <td><div class="label">Belum merespons</div><div class="value">{{ $stats['pending'] }}</div></td>
        </tr>
        <tr>
            <td><div class="label">Perkiraan hadir</div><div class="value">{{ $stats['expected_attendance'] }}</div></td>
            <td><div class="label">RSVP rate</div><div class="value">{{ $stats['rsvp_rate'] }}%</div></td>
            <td><div class="label">Kehadiran</div><div class="value">{{ $stats['attendance_rate'] }}%</div></td>
            <td><div class="label">Meja</div><div class="value">{{ $stats['tables'] }}</div></td>
        </tr>
    </table>

    <h2>Distribusi RSVP</h2>
    <table>
        <thead>
            <tr><th>Status</th><th>Jumlah</th></tr>
        </thead>
        <tbody>
            @foreach ($rsvpBreakdown as $row)
                <tr><td>{{ $row['label'] }}</td><td>{{ $row['count'] }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>Timeline check-in</h2>
    @if (count($timeline))
        <table>
            <thead>
                <tr><th>Jam</th><th>Jumlah check-in</th></tr>
            </thead>
            <tbody>
                @foreach ($timeline as $row)
                    <tr><td>{{ $row['label'] }}</td><td>{{ $row['count'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">Belum ada data check-in.</p>
    @endif

    <h2>Konten &amp; interaksi</h2>
    <table>
        <thead>
            <tr><th>Metrik</th><th>Nilai</th></tr>
        </thead>
        <tbody>
            <tr><td>Ucapan</td><td>{{ $stats['total_messages'] }} ({{ $stats['approved_messages'] }} disetujui, {{ $stats['pending_messages'] }} menunggu)</td></tr>
            <tr><td>Foto</td><td>{{ $stats['total_photos'] }} ({{ $stats['approved_photos'] }} disetujui)</td></tr>
            <tr><td>Request lagu</td><td>{{ $stats['song_requests'] }}</td></tr>
            <tr><td>Undangan dibuka</td><td>{{ $stats['opened_invitations'] }} dari {{ $stats['total_invitations'] }}</td></tr>
            <tr><td>Tamu duduk</td><td>{{ $stats['seated_guests'] }} dari kapasitas {{ $stats['seat_capacity'] }}</td></tr>
        </tbody>
    </table>

    <div class="footer">
        Dibuat {{ now()->translatedFormat('d F Y H:i') }} · VOWSPACE
    </div>
</body>
</html>
