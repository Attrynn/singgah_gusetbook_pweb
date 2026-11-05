<?php

namespace Database\Seeders;

use App\Enums\CheckinMethod;
use App\Enums\EventStatus;
use App\Enums\GuestStatus;
use App\Enums\InvitationStatus;
use App\Enums\MediaStatus;
use App\Enums\MessageStatus;
use App\Enums\MessageVisibility;
use App\Enums\RsvpStatus;
use App\Enums\SongStatus;
use App\Enums\UserRole;
use App\Models\AppSetting;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\EventActivity;
use App\Models\EventSchedule;
use App\Models\EventVenue;
use App\Models\Game;
use App\Models\GameAttempt;
use App\Models\GiftAccount;
use App\Models\Guest;
use App\Models\GuestbookMessage;
use App\Models\MediaAlbum;
use App\Models\MediaAsset;
use App\Models\Poll;
use App\Models\PollResponse;
use App\Models\Rsvp;
use App\Models\SeatingTable;
use App\Models\SongRequest;
use App\Models\TableAssignment;
use App\Models\User;
use App\Services\AuditService;
use App\Services\EventService;
use App\Services\InvitationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $audit = app(AuditService::class);
        $invitations = app(InvitationService::class);
        $eventService = app(EventService::class);

        $admin = User::updateOrCreate(
            ['email' => 'admin@vowspace.test'],
            [
                'name' => 'VOWSPACE Admin',
                'password' => Hash::make('password'),
                'role' => UserRole::Superadmin,
                'phone' => '081200000001',
                'is_active' => true,
            ]
        );

        $raka = User::updateOrCreate(
            ['email' => 'raka@vowspace.test'],
            [
                'name' => 'Raka Pratama',
                'password' => Hash::make('password'),
                'role' => UserRole::Owner,
                'phone' => '081200000002',
                'is_active' => true,
            ]
        );

        $alya = User::updateOrCreate(
            ['email' => 'alya@vowspace.test'],
            [
                'name' => 'Alya Putri',
                'password' => Hash::make('password'),
                'role' => UserRole::Owner,
                'phone' => '081200000003',
                'is_active' => true,
            ]
        );

        $dimas = User::updateOrCreate(
            ['email' => 'dimas@vowspace.test'],
            [
                'name' => 'Dimas Anggara',
                'password' => Hash::make('password'),
                'role' => UserRole::Owner,
                'phone' => '081200000004',
                'is_active' => true,
            ]
        );

        $event = Event::updateOrCreate(
            ['slug' => 'raka-alya'],
            [
                'name' => 'Raka & Alya',
                'groom_name' => 'Raka',
                'bride_name' => 'Alya',
                'groom_profile' => 'Anak pertama dari keluarga Pratama. Senang kopi, gunung, dan malam yang tenang.',
                'bride_profile' => 'Anak kedua dari keluarga Putri. Pecinta bunga, buku, dan senja di tepi pantai.',
                'couple_description' => 'Kami bertemu di sebuah pameran buku di Bandung, bertukar rekomendasi, lalu menemukan rumah pada satu sama lain. Dengan penuh syukur, kami mengundang Anda untuk merayakan awal baru kami.',
                'event_date' => now()->addDays(38)->toDateString(),
                'start_time' => '10:00',
                'end_time' => '14:00',
                'venue_name' => 'The Aruna Hall',
                'venue_address' => 'Jl. Senopati No. 21, Kebayoran Baru, Jakarta Selatan',
                'google_maps_url' => 'https://maps.google.com/?q=The+Aruna+Hall+Jakarta',
                'dress_code' => 'Formal — soft neutral tones',
                'description' => 'Akad nikah pukul 10.00, dilanjutkan resepsi dan makan siang bersama. Kami menantikan kehadiran Anda.',
                'status' => EventStatus::Live,
                'created_by' => $admin->id,
                'rsvp_deadline' => now()->addDays(30),
                'rsvp_enabled' => true,
                'guestbook_enabled' => true,
                'gallery_enabled' => true,
                'gift_enabled' => true,
                'live_wall_enabled' => true,
                'timezone' => 'Asia/Jakarta',
                'published_at' => now()->subDays(7),
            ]
        );

        $event->owners()->sync([
            $raka->id => ['role_label' => 'groom'],
            $alya->id => ['role_label' => 'bride'],
        ]);

        $archive = Event::updateOrCreate(
            ['slug' => 'dimas-nadia'],
            [
                'name' => 'Dimas & Nadia',
                'groom_name' => 'Dimas',
                'bride_name' => 'Nadia',
                'groom_profile' => 'Pecinta fotografi dan perjalanan darat.',
                'bride_profile' => 'Guru tari dan pencinta musik lama.',
                'couple_description' => 'Sebuah perjalanan tujuh tahun yang berakhir di pelaminan, dan baru saja dimulai kembali sebagai arsip kenangan.',
                'event_date' => now()->subDays(45)->toDateString(),
                'start_time' => '09:00',
                'end_time' => '13:00',
                'venue_name' => 'Padma Garden',
                'venue_address' => 'Jl. Raya Canggu No. 88, Badung, Bali',
                'google_maps_url' => 'https://maps.google.com/?q=Padma+Garden+Bali',
                'dress_code' => 'Garden formal',
                'description' => 'Terima kasih telah menjadi bagian dari hari kami.',
                'status' => EventStatus::Completed,
                'created_by' => $admin->id,
                'rsvp_deadline' => now()->subDays(50),
                'rsvp_enabled' => true,
                'guestbook_enabled' => true,
                'gallery_enabled' => true,
                'gift_enabled' => true,
                'live_wall_enabled' => false,
                'timezone' => 'Asia/Makassar',
                'published_at' => now()->subDays(90),
            ]
        );

        $archive->owners()->sync([$dimas->id => ['role_label' => 'groom']]);

        foreach ([$event, $archive] as $e) {
            $eventService->seedDefaultTaxonomies($e);
            $this->seedVenue($e);
            $this->seedSchedules($e);
        }

        $this->seedEventCover($event, 'Raka & Alya');
        $this->seedEventCover($archive, 'Dimas & Nadia');

        if (! $event->guests()->exists()) {
            $this->seedGuests($event, $invitations, $admin, true);
        }

        if (! $archive->guests()->exists()) {
            $this->seedGuests($archive, $invitations, $admin, false);
        }

        $this->seedGuestbook($event, $archive);
        $this->seedGifts($event, $archive);
        $this->seedMedia($event, $archive, $admin);
        $this->seedInteractions($event, $archive, $admin);
        $this->seedSeating($event);
        $this->seedActivities($event, $audit, $admin);
        $this->seedInvitationStatuses($event);
        $this->seedSettings();

        $audit->record('demo.seeded', 'Demo data VOWSPACE dimuat', $event, $admin);
    }

    protected function seedVenue(Event $event): void
    {
        EventVenue::updateOrCreate(
            ['event_id' => $event->id, 'is_primary' => true],
            [
                'name' => $event->venue_name,
                'address' => $event->venue_address,
                'google_maps_url' => $event->google_maps_url,
                'latitude' => -6.2,
                'longitude' => 106.8,
            ]
        );
    }

    protected function seedSchedules(Event $event): void
    {
        if ($event->schedules()->exists()) {
            return;
        }

        $base = $event->event_date->copy()->setTime(9, 0);
        $rows = [
            ['Guest arrival & registrasi', 'Silakan tunjukkan QR pass Anda di meja registrasi.', 0, 30, 'planned'],
            ['Akad nikah', 'Prosesi sakral di hadapan keluarga dan sahabat.', 30, 60, 'planned'],
            ['Foto keluarga', 'Sesi foto bersama keluarga besar kedua mempelai.', 90, 30, 'planned'],
            ['Resepsi & makan siang', 'Santap bersama, musik, dan ucapan hangat.', 120, 120, 'planned'],
            ['Penutupan', 'Terima kasih atas kehadiran dan doa Anda.', 240, 30, 'planned'],
        ];

        foreach ($rows as $i => [$title, $desc, $offset, $duration, $status]) {
            EventSchedule::create([
                'event_id' => $event->id,
                'title' => $title,
                'description' => $desc,
                'start_time' => $base->copy()->addMinutes($offset),
                'end_time' => $base->copy()->addMinutes($offset + $duration),
                'status' => $status,
                'sort_order' => $i,
            ]);
        }
    }

    protected function seedGuests(Event $event, InvitationService $invitations, User $admin, bool $active): void
    {
        $categories = $event->categories()->get()->keyBy('slug');
        $groups = $event->groups()->get()->keyBy('name');

        $rows = $active
            ? [
                ['Raka Pratama', 'raka@vowspace.test', '081200000002', 'family', 'Family', 'confirmed', 2, true],
                ['Alya Putri', 'alya@vowspace.test', '081200000003', 'family', 'Family', 'confirmed', 2, true],
                ['Bimo Saputra', 'bimo@example.test', '081211110001', 'friend', 'Friends', 'confirmed', 2, true],
                ['Citra Lestari', 'citra@example.test', '081211110002', 'friend', 'Friends', 'confirmed', 1, true],
                ['Dewi Anggraini', 'dewi@example.test', '081211110003', 'family', 'Family', 'confirmed', 3, true],
                ['Eko Prasetyo', 'eko@example.test', '081211110004', 'colleague', 'Colleagues', 'confirmed', 2, true],
                ['Fajar Nugroho', 'fajar@example.test', '081211110005', 'friend', 'Friends', 'maybe', 2, false],
                ['Gita Permata', 'gita@example.test', '081211110006', 'friend', 'Friends', 'confirmed', 2, true],
                ['Hendra Wijaya', 'hendra@example.test', '081211110007', 'business-relation', 'VIP', 'confirmed', 2, true],
                ['Indah Sari', 'indah@example.test', '081211110008', 'family', 'Family', 'confirmed', 2, true],
                ['Joko Susilo', 'joko@example.test', '081211110009', 'colleague', 'Colleagues', 'declined', 0, false],
                ['Kartika Dewi', 'kartika@example.test', '081211110010', 'friend', 'Friends', 'confirmed', 1, true],
                ['Lukman Hakim', 'lukman@example.test', '081211110011', 'colleague', 'Colleagues', 'pending', 2, false],
                ['Maya Sinta', 'maya@example.test', '081211110012', 'friend', 'Friends', 'confirmed', 2, true],
                ['Naufal Rizky', 'naufal@example.test', '081211110013', 'friend', 'Friends', 'maybe', 1, false],
                ['Oktavia Rahma', 'oktavia@example.test', '081211110014', 'family', 'Family', 'confirmed', 2, true],
                ['Putra Aditya', 'putra@example.test', '081211110015', 'business-relation', 'VIP', 'confirmed', 2, true],
                ['Qori Amalia', 'qori@example.test', '081211110016', 'friend', 'Friends', 'pending', 1, false],
                ['Rizky Ramadhan', 'rizky@example.test', '081211110017', 'colleague', 'Colleagues', 'confirmed', 1, true],
                ['Sinta Maharani', 'sinta@example.test', '081211110018', 'friend', 'Friends', 'declined', 0, false],
                ['Taufik Hidayat', 'taufik@example.test', '081211110019', 'family', 'Family', 'confirmed', 4, true],
                ['Umi Kalsum', 'umi@example.test', '081211110020', 'family', 'Family', 'confirmed', 2, true],
                ['Vina Oktaviani', 'vina@example.test', '081211110021', 'friend', 'Friends', 'pending', 2, false],
                ['Wahyu Setiawan', 'wahyu@example.test', '081211110022', 'colleague', 'Colleagues', 'confirmed', 2, false],
                ['Yuni Astuti', 'yuni@example.test', '081211110023', 'friend', 'Friends', 'maybe', 2, false],
                ['Zaki Firmansyah', 'zaki@example.test', '081211110024', 'other', 'VIP', 'confirmed', 1, false],
            ]
            : [
                ['Andi Wijaya', 'andi@example.test', '081222220001', 'family', 'Family', 'confirmed', 2, true],
                ['Bella Safira', 'bella@example.test', '081222220002', 'friend', 'Friends', 'confirmed', 1, true],
                ['Candra Kirana', 'candra@example.test', '081222220003', 'friend', 'Friends', 'confirmed', 2, true],
                ['Dian Puspita', 'dian@example.test', '081222220004', 'family', 'Family', 'confirmed', 2, true],
                ['Erwin Gunawan', 'erwin@example.test', '081222220005', 'colleague', 'Colleagues', 'declined', 0, false],
                ['Fitri Handayani', 'fitri@example.test', '081222220006', 'friend', 'Friends', 'confirmed', 1, true],
                ['Galih Pratama', 'galih@example.test', '081222220007', 'friend', 'Friends', 'confirmed', 2, true],
                ['Hana Maulida', 'hana@example.test', '081222220008', 'family', 'Family', 'confirmed', 3, true],
                ['Irfan Maulana', 'irfan@example.test', '081222220009', 'colleague', 'Colleagues', 'maybe', 1, false],
                ['Jihan Aulia', 'jihan@example.test', '081222220010', 'friend', 'Friends', 'confirmed', 2, true],
                ['Krisna Bayu', 'krisna@example.test', '081222220011', 'friend', 'Friends', 'pending', 2, false],
                ['Laras Ayu', 'laras@example.test', '081222220012', 'family', 'Family', 'confirmed', 2, true],
            ];

        foreach ($rows as $index => [$name, $email, $phone, $categorySlug, $groupName, $rsvpStatus, $count, $checkedIn]) {
            $guest = Guest::create([
                'event_id' => $event->id,
                'guest_category_id' => $categories[$categorySlug]?->id,
                'guest_group_id' => $groups[$groupName]?->id,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'status' => GuestStatus::Active,
                'max_attendees' => max(1, $count),
                'rsvp_status' => RsvpStatus::from($rsvpStatus),
                'created_by' => $admin->id,
            ]);

            $invitations->issueForGuest($guest);

            if ($rsvpStatus !== 'pending') {
                Rsvp::create([
                    'event_id' => $event->id,
                    'guest_id' => $guest->id,
                    'status' => RsvpStatus::from($rsvpStatus),
                    'attendance_count' => $count,
                    'companion_names' => $count > 1 ? 'Pasangan / keluarga' : null,
                    'dietary_preference' => $index % 7 === 0 ? 'No seafood' : null,
                    'notes' => $rsvpStatus === 'declined' ? 'Mohon maaf tidak dapat hadir.' : null,
                    'responded_at' => now()->subDays(10 - ($index % 8)),
                ]);
            }

            if ($checkedIn) {
                $checkedAt = $event->event_date->copy()->setTime(9, 30)->addMinutes($index * 4);
                Checkin::create([
                    'event_id' => $event->id,
                    'guest_id' => $guest->id,
                    'checked_in_by' => $admin->id,
                    'method' => $index % 4 === 0 ? CheckinMethod::Manual : CheckinMethod::Qr,
                    'checked_in_at' => $checkedAt,
                    'notes' => $index % 4 === 0 ? 'Registrasi meja utama' : null,
                ]);
                $guest->forceFill(['checked_in_at' => $checkedAt])->save();
            }
        }
    }

    protected function seedGuestbook(Event $event, Event $archive): void
    {
        if ($event->messages()->exists()) {
            return;
        }

        $guests = $event->guests()->orderBy('id')->get();
        $messages = [
            ['Selamat menempuh hidup baru, Raka & Alya! Semoga menjadi keluarga yang penuh cinta dan keberkahan.', '🤍', 'public', 'approved', true],
            ['Tidak sabar melihat kalian di pelaminan. Sampai jumpa di hari bahagia itu!', '✨', 'public', 'approved', false],
            ['Semoga perjalanan kalian selalu dipenuhi tawa, kesabaran, dan kopi yang hangat.', '💐', 'public', 'approved', false],
            ['Doa terbaik dari kami sekeluarga. Selamat menikah!', '🕊️', 'public', 'approved', false],
            ['Raka, jangan lupa makan. Alya, jangan lupa istirahat. Kalian berdua luar biasa.', '💍', 'public', 'approved', false],
            ['Terima kasih sudah mengundang kami. Sampai ketemu di Aruna Hall.', '🤍', 'public', 'approved', false],
            ['Pesan pribadi untuk kalian berdua — semoga selalu saling menguatkan.', null, 'private', 'approved', false],
            ['Selamat ya! Semoga langgeng sampai kakek nenek.', '✨', 'public', 'pending', false],
            ['Kalian pasangan yang saling melengkapi. Bahagia selalu!', '💐', 'public', 'pending', false],
            ['Saya akan datang bersama keluarga. Terima kasih undangannya.', '🤍', 'public', 'approved', false],
            ['Doa dari jauh, semoga hari besarnya lancar.', '🕊️', 'public', 'approved', false],
            ['Sampai jumpa di resepsi!', '✨', 'public', 'hidden', false],
            ['Ini pesan yang tidak seharusnya tampil di publik.', null, 'private', 'pending', false],
            ['Selamat menempuh hidup baru. Semoga cinta kalian terus bertumbuh.', '💍', 'public', 'approved', false],
        ];

        foreach ($messages as $i => [$text, $emoji, $visibility, $status, $pinned]) {
            $guest = $guests[$i % $guests->count()];

            GuestbookMessage::create([
                'event_id' => $event->id,
                'guest_id' => $guest->id,
                'sender_name' => $guest->name,
                'message' => $text,
                'emoji' => $emoji,
                'visibility' => MessageVisibility::from($visibility),
                'status' => MessageStatus::from($status),
                'is_pinned' => $pinned,
                'reviewed_by' => $status !== 'pending' ? $event->created_by : null,
                'reviewed_at' => $status !== 'pending' ? now()->subDays(3) : null,
                'created_at' => now()->subDays(14 - $i),
                'updated_at' => now()->subDays(14 - $i),
            ]);
        }

        $archiveGuests = $archive->guests()->orderBy('id')->get();

        foreach ($archiveGuests->take(8) as $i => $guest) {
            GuestbookMessage::create([
                'event_id' => $archive->id,
                'guest_id' => $guest->id,
                'sender_name' => $guest->name,
                'message' => 'Selamat menikah Dimas & Nadia. Terima kasih sudah menjadi tuan rumah yang hangat di Bali.',
                'emoji' => '🤍',
                'visibility' => MessageVisibility::Public,
                'status' => MessageStatus::Approved,
                'is_pinned' => $i === 0,
                'reviewed_by' => $archive->created_by,
                'reviewed_at' => now()->subDays(40),
            ]);
        }
    }

    protected function seedGifts(Event $event, Event $archive): void
    {
        if ($event->giftAccounts()->exists()) {
            return;
        }

        $qris = $this->makeImage('demo/gifts/qris-raka-alya.jpg', 700, 900, [142, 180, 211], [165, 199, 212], 'QRIS  ·  RAKA & ALYA');

        GiftAccount::create([
            'event_id' => $event->id,
            'bank_name' => 'BCA',
            'account_number' => '5271 8890 1123',
            'account_owner' => 'Raka Pratama',
            'qris_path' => $qris,
            'gift_address' => 'Jl. Senopati No. 21, Kebayoran Baru, Jakarta Selatan 12190',
            'note' => 'Terima kasih atas doa dan kasih Anda. Hadiah bukan kewajiban, kehadiran Anda sudah lebih dari cukup.',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        GiftAccount::create([
            'event_id' => $event->id,
            'bank_name' => 'Mandiri',
            'account_number' => '1370 0099 8877',
            'account_owner' => 'Alya Putri',
            'gift_address' => null,
            'note' => 'Konfirmasi transfer dapat dikirim ke nomor WhatsApp yang tertera di undangan.',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        GiftAccount::create([
            'event_id' => $archive->id,
            'bank_name' => 'BRI',
            'account_number' => '0206 0102 3344 505',
            'account_owner' => 'Dimas Anggara',
            'note' => 'Arsip hadiah acara yang telah selesai.',
            'is_active' => false,
            'sort_order' => 0,
        ]);
    }

    protected function seedMedia(Event $event, Event $archive, User $admin): void
    {
        if ($event->mediaAssets()->exists()) {
            return;
        }

        $album = MediaAlbum::create([
            'event_id' => $event->id,
            'name' => 'Akad & Reception',
            'description' => 'Pilihan foto utama dari hari akad dan resepsi.',
            'created_by' => $admin->id,
        ]);

        $guestIds = $event->guests()->orderBy('id')->pluck('id');

        $palettes = [
            [[142, 180, 211], [220, 234, 245], 'Akad'],
            [[165, 199, 212], [223, 239, 237], 'Reception'],
            [[213, 173, 112], [248, 250, 252], 'Details'],
            [[93, 173, 145], [220, 234, 245], 'Family'],
            [[216, 140, 150], [241, 245, 249], 'Together'],
            [[37, 52, 71], [142, 180, 211], 'The Vow'],
        ];

        for ($i = 0; $i < 12; $i++) {
            [$from, $to, $label] = $palettes[$i % count($palettes)];
            $path = $this->makeImage(
                "demo/events/{$event->id}/photo-".($i + 1).'.jpg',
                1200,
                900,
                $from,
                $to,
                $label.'  ·  Raka & Alya'
            );

            $status = $i < 8 ? MediaStatus::Approved : ($i < 10 ? MediaStatus::Pending : MediaStatus::Rejected);

            $asset = MediaAsset::create([
                'event_id' => $event->id,
                'media_album_id' => $i < 8 ? $album->id : null,
                'guest_id' => $i % 3 === 0 ? $guestIds[$i % $guestIds->count()] : null,
                'uploaded_by' => $i % 3 === 0 ? null : $admin->id,
                'path' => $path,
                'original_name' => 'raka-alya-'.($i + 1).'.jpg',
                'mime_type' => 'image/jpeg',
                'size_bytes' => Storage::disk('public')->size($path),
                'caption' => $label.' — momen Raka & Alya',
                'status' => $status,
                'is_featured' => $i < 3,
                'reviewed_by' => $status !== MediaStatus::Pending ? $admin->id : null,
                'reviewed_at' => $status !== MediaStatus::Pending ? now()->subDays(2) : null,
            ]);

            if ($i < 8) {
                $album->items()->create(['media_asset_id' => $asset->id, 'sort_order' => $i]);
            }
        }

        $album->update(['cover_media_asset_id' => $album->assets()->first()?->id]);

        $archiveAlbum = MediaAlbum::create([
            'event_id' => $archive->id,
            'name' => 'Bali Wedding Archive',
            'description' => 'Arsip pernikahan Dimas & Nadia di Padma Garden.',
            'created_by' => $admin->id,
        ]);

        for ($i = 0; $i < 6; $i++) {
            $path = $this->makeImage(
                "demo/events/{$archive->id}/archive-".($i + 1).'.jpg',
                1200,
                900,
                [165, 199, 212],
                [248, 250, 252],
                'Archive  ·  Dimas & Nadia'
            );

            $asset = MediaAsset::create([
                'event_id' => $archive->id,
                'media_album_id' => $archiveAlbum->id,
                'uploaded_by' => $admin->id,
                'path' => $path,
                'original_name' => 'dimas-nadia-'.($i + 1).'.jpg',
                'mime_type' => 'image/jpeg',
                'size_bytes' => Storage::disk('public')->size($path),
                'caption' => 'Arsip pernikahan Dimas & Nadia',
                'status' => MediaStatus::Approved,
                'is_featured' => $i < 2,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now()->subDays(40),
            ]);

            $archiveAlbum->items()->create(['media_asset_id' => $asset->id, 'sort_order' => $i]);
        }

        $archiveAlbum->update(['cover_media_asset_id' => $archiveAlbum->assets()->first()?->id]);
    }

    protected function seedInteractions(Event $event, Event $archive, User $admin): void
    {
        $guests = $event->guests()->orderBy('id')->get();

        if (! $event->polls()->exists()) {
            $poll = Poll::create([
                'event_id' => $event->id,
                'question' => 'Lagu apa yang wajib diputar saat resepsi?',
                'description' => 'Bantu kami menyusun playlist yang akan membuat semua orang menari.',
                'is_active' => true,
                'show_results' => true,
                'closes_at' => now()->addDays(25),
                'created_by' => $admin->id,
            ]);

            $options = ['Perfect — Ed Sheeran', 'Sempurna — Andra and The Backbone', 'Melukis Senja — Budi Doremi', 'Can\'t Help Falling in Love — Elvis Presley'];

            foreach ($options as $i => $label) {
                $option = $poll->options()->create(['label' => $label, 'sort_order' => $i]);

                foreach ($guests->slice($i * 4, 3) as $guest) {
                    PollResponse::create([
                        'poll_id' => $poll->id,
                        'poll_option_id' => $option->id,
                        'guest_id' => $guest->id,
                    ]);
                }
            }

            $archivePoll = Poll::create([
                'event_id' => $archive->id,
                'question' => 'Momen favorit dari pernikahan Dimas & Nadia?',
                'description' => 'Polling arsip yang sudah ditutup.',
                'is_active' => false,
                'show_results' => true,
                'closes_at' => now()->subDays(40),
                'created_by' => $admin->id,
            ]);

            foreach (['Akad di taman', 'First dance', 'Ucapan keluarga'] as $i => $label) {
                $archivePoll->options()->create(['label' => $label, 'sort_order' => $i]);
            }
        }

        if (! $event->games()->exists()) {
            $game = Game::create([
                'event_id' => $event->id,
                'title' => 'Seberapa kenal kamu dengan Raka & Alya?',
                'description' => 'Lima pertanyaan ringan tentang perjalanan kami. Selamat bermain!',
                'type' => 'quiz',
                'is_active' => true,
                'created_by' => $admin->id,
            ]);

            $questions = [
                ['Di mana Raka dan Alya pertama kali bertemu?', ['Pameran buku di Bandung', 'Kantor', 'Kafe di Jakarta', 'Pesta teman'], 0],
                ['Siapa yang lebih dulu menyatakan perasaan?', ['Raka', 'Alya', 'Mereka bersamaan', 'Teman mereka'], 0],
                ['Minuman favorit pasangan ini?', ['Kopi', 'Teh', 'Jus', 'Air mineral'], 0],
                ['Bulan apa mereka bertunangan?', ['Januari', 'Maret', 'Juni', 'September'], 2],
                ['Apa warna dominan undangan mereka?', ['Soft blue', 'Merah', 'Hitam', 'Hijau'], 0],
            ];

            foreach ($questions as $qi => [$question, $options, $correct]) {
                $q = $game->questions()->create(['question' => $question, 'sort_order' => $qi]);

                foreach ($options as $oi => $label) {
                    $q->options()->create([
                        'label' => $label,
                        'is_correct' => $oi === $correct,
                        'sort_order' => $oi,
                    ]);
                }
            }

            foreach ($guests->take(3) as $index => $guest) {
                $score = 4 - $index;
                $attempt = GameAttempt::create([
                    'game_id' => $game->id,
                    'guest_id' => $guest->id,
                    'score' => $score,
                    'total_questions' => $game->questions()->count(),
                    'completed_at' => now()->subDays(2),
                ]);

                foreach ($game->questions()->with('options')->get() as $qIndex => $question) {
                    $correct = $question->options->firstWhere('is_correct', true);
                    $chosen = $qIndex < $score ? $correct : $question->options->firstWhere('is_correct', false);

                    $attempt->answers()->create([
                        'game_question_id' => $question->id,
                        'game_option_id' => $chosen?->id,
                        'is_correct' => $chosen?->is_correct ?? false,
                    ]);
                }
            }
        }

        if (! $event->songRequests()->exists()) {
            $songs = [
                ['Perfect', 'Ed Sheeran', 'approved'],
                ['Sempurna', 'Andra and The Backbone', 'played'],
                ['Melukis Senja', 'Budi Doremi', 'approved'],
                ['Can\'t Help Falling in Love', 'Elvis Presley', 'played'],
                ['A Thousand Years', 'Christina Perri', 'approved'],
                ['Kupu-Kupu', 'Tiara Andini', 'pending'],
                ['Rumah Ke Rumah', 'Hindia', 'pending'],
                ['Laskar Pelangi', 'Nidji', 'approved'],
                ['Bertaut', 'Nadin Amizah', 'played'],
                ['Dandelions', 'Ruth B.', 'rejected'],
            ];

            foreach ($songs as $i => [$title, $artist, $status]) {
                SongRequest::create([
                    'event_id' => $event->id,
                    'guest_id' => $guests[$i % $guests->count()]->id,
                    'song_title' => $title,
                    'artist' => $artist,
                    'note' => $i % 4 === 0 ? 'Tolong diputar saat makan malam.' : null,
                    'status' => SongStatus::from($status),
                ]);
            }

            foreach ($archive->guests()->take(5)->get() as $i => $guest) {
                SongRequest::create([
                    'event_id' => $archive->id,
                    'guest_id' => $guest->id,
                    'song_title' => ['September', 'Photograph', 'Cinta Luar Biasa', 'Akad', 'Kiss Me'][$i],
                    'artist' => ['Earth, Wind & Fire', 'Ed Sheeran', 'Andmesh', 'Payung Teduh', 'Sixpence None the Richer'][$i],
                    'status' => SongStatus::Played,
                ]);
            }
        }
    }

    protected function seedSeating(Event $event): void
    {
        if ($event->seatingTables()->exists()) {
            return;
        }

        $tables = [
            ['Meja 01 — Keluarga', 10, 'Family'],
            ['Meja 02 — Keluarga', 10, 'Family'],
            ['Meja 03 — Sahabat', 8, 'Friends'],
            ['Meja 04 — Rekan Kerja', 8, 'Colleagues'],
            ['Meja 05 — VIP', 6, 'VIP'],
        ];

        $created = [];

        foreach ($tables as $i => [$name, $capacity, $zone]) {
            $created[] = SeatingTable::create([
                'event_id' => $event->id,
                'name' => $name,
                'capacity' => $capacity,
                'zone' => $zone,
                'notes' => null,
            ]);
        }

        $guests = $event->guests()
            ->where('rsvp_status', RsvpStatus::Confirmed->value)
            ->orderBy('id')
            ->take(20)
            ->get();

        foreach ($guests as $index => $guest) {
            $table = $created[min((int) floor($index / 5), count($created) - 1)];

            TableAssignment::create([
                'event_id' => $event->id,
                'seating_table_id' => $table->id,
                'guest_id' => $guest->id,
                'seat_number' => ($index % 5) + 1,
            ]);
        }
    }

    protected function seedActivities(Event $event, AuditService $audit, User $admin): void
    {
        if ($event->activities()->exists()) {
            return;
        }

        $guests = $event->guests()->orderByDesc('updated_at')->take(6)->get();
        $types = [
            ['rsvp.submitted', 'RSVP baru diterima'],
            ['guestbook.submitted', 'Ucapan baru masuk dan menunggu moderasi'],
            ['checkin.qr', 'Tamu check-in melalui QR pass'],
            ['media.uploaded', 'Foto baru diunggah ke galeri'],
            ['song.requested', 'Permintaan lagu baru'],
            ['invitation.shared', 'Undangan dibagikan melalui WhatsApp'],
        ];

        foreach ($types as $i => [$type, $description]) {
            $guest = $guests[$i % max(1, $guests->count())] ?? null;

            EventActivity::create([
                'event_id' => $event->id,
                'user_id' => $i % 2 === 0 ? $admin->id : null,
                'guest_id' => $guest?->id,
                'type' => $type,
                'description' => $description.($guest ? " — {$guest->name}" : ''),
                'metadata' => ['demo' => true],
                'created_at' => now()->subHours($i * 3 + 1),
                'updated_at' => now()->subHours($i * 3 + 1),
            ]);
        }

        $audit->record('event.published', "Event {$event->name} dipublikasikan", $event, $admin, $event);
        $audit->record('guestbook.approved', 'Ucapan disetujui oleh admin', $event, $admin);
    }

    protected function seedInvitationStatuses(Event $event): void
    {
        $guests = $event->guests()->with('invitation')->orderBy('id')->take(12)->get();

        foreach ($guests as $index => $guest) {
            if (! $guest->invitation) {
                continue;
            }

            $status = match (true) {
                $index < 4 => InvitationStatus::LinkOpened,
                $index < 7 => InvitationStatus::SharedManually,
                $index < 9 => InvitationStatus::Sent,
                default => InvitationStatus::NotSent,
            };

            $guest->invitation->forceFill([
                'status' => $status,
                'opened_at' => $index < 4 ? now()->subDays(5) : null,
                'open_count' => $index < 4 ? 3 : 0,
                'last_shared_at' => $index >= 4 && $index < 7 ? now()->subDays(2) : null,
                'sent_at' => $index >= 4 && $index < 9 ? now()->subDays(4) : null,
            ])->save();
        }
    }

    protected function seedSettings(): void
    {
        AppSetting::set('app_name', 'VOWSPACE');
        AppSetting::set('support_email', 'hello@vowspace.test');
        AppSetting::set('default_timezone', 'Asia/Jakarta');
        AppSetting::set('guestbook_auto_approve', '0');
    }

    protected function seedEventCover(Event $event, string $label): void
    {
        if ($event->cover_image && Storage::disk('public')->exists($event->cover_image)) {
            return;
        }

        $path = $this->makeImage(
            "demo/events/{$event->id}/cover.jpg",
            1600,
            1000,
            [142, 180, 211],
            [241, 245, 249],
            $label.'  ·  Save the date'
        );

        $event->update(['cover_image' => $path]);
    }

    /**
     * Generate a deterministic soft-gradient placeholder image so the demo gallery has real files.
     *
     * @param  array{0:int,1:int,2:int}  $from
     * @param  array{0:int,1:int,2:int}  $to
     */
    protected function makeImage(string $relative, int $width, int $height, array $from, array $to, string $caption): string
    {
        Storage::disk('public')->makeDirectory(dirname($relative));
        $absolute = Storage::disk('public')->path($relative);

        if (file_exists($absolute)) {
            return $relative;
        }

        $image = imagecreatetruecolor($width, $height);

        for ($y = 0; $y < $height; $y++) {
            $t = $y / max(1, $height - 1);
            $r = (int) round($from[0] + ($to[0] - $from[0]) * $t);
            $g = (int) round($from[1] + ($to[1] - $from[1]) * $t);
            $b = (int) round($from[2] + ($to[2] - $from[2]) * $t);
            imagefilledrectangle($image, 0, $y, $width, $y + 1, imagecolorallocate($image, $r, $g, $b));
        }

        for ($i = 0; $i < 7; $i++) {
            $circle = imagecolorallocatealpha(
                $image,
                255,
                255,
                255,
                random_int(96, 118)
            );
            $radius = random_int((int) ($width * 0.08), (int) ($width * 0.26));
            imagefilledellipse(
                $image,
                random_int(0, $width),
                random_int(0, $height),
                $radius,
                $radius,
                $circle
            );
        }

        $ink = imagecolorallocate($image, 37, 52, 71);
        $white = imagecolorallocate($image, 255, 255, 255);

        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($caption);
        $x = max(24, (int) (($width - $textWidth) / 2));
        $y = (int) ($height / 2);

        imagefilledrectangle($image, $x - 24, $y - 18, $x + $textWidth + 24, $y + 28, $white);
        imagestring($image, $font, $x, $y - 8, $caption, $ink);

        $small = 'VOWSPACE  ·  BEYOND THE GUESTBOOK';
        $smallWidth = imagefontwidth(3) * strlen($small);
        imagestring($image, 3, max(16, (int) (($width - $smallWidth) / 2)), (int) ($height / 2) + 52, $small, $white);

        imagejpeg($image, $absolute, 84);
        imagedestroy($image);

        return $relative;
    }
}
