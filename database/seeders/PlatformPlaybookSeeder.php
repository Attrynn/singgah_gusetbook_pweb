<?php

namespace Database\Seeders;

use App\Enums\EventStaffRole;
use App\Enums\PlaybookStatus;
use App\Models\Playbook;
use Illuminate\Database\Seeder;

/**
 * Seeds the platform playbook templates. Idempotent: playbooks are keyed by
 * slug and their version 1 config is only created when missing.
 */
class PlatformPlaybookSeeder extends Seeder
{
    /**
     * The seven event staff roles used across playbook team requirements.
     *
     * @var array<string, int>
     */
    protected const TEAM_ROLE_QUANTITIES = [
        EventStaffRole::EventManager->value => 1,
        EventStaffRole::GuestManager->value => 1,
        EventStaffRole::CheckinSupervisor->value => 1,
        EventStaffRole::CheckinStaff->value => 2,
        EventStaffRole::SeatingUsher->value => 2,
        EventStaffRole::MediaCurator->value => 1,
        EventStaffRole::GiftSteward->value => 1,
    ];

    public function run(): void
    {
        foreach ($this->blueprints() as $blueprint) {
            $playbook = Playbook::firstOrCreate(
                ['slug' => $blueprint['slug']],
                [
                    'organization_id' => null,
                    'name' => $blueprint['name'],
                    'description' => $blueprint['description'],
                    'event_type' => 'wedding',
                    'days' => $blueprint['days'],
                    'status' => PlaybookStatus::Active,
                    'created_by' => null,
                ],
            );

            $playbook->versions()->firstOrCreate(
                ['version' => 1],
                [
                    'config' => $this->config($blueprint),
                    'notes' => 'Template platform versi awal.',
                ],
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function blueprints(): array
    {
        return [
            [
                'name' => 'Pernikahan Akad dan Resepsi',
                'slug' => 'akad-resepsi',
                'description' => 'Rangkaian standar akad nikah pada pagi hari dilanjutkan resepsi pada siang hingga malam hari.',
                'days' => 1,
                'phases' => [
                    ['name' => 'Persiapan', 'sequence' => 0],
                    ['name' => 'Akad Nikah', 'sequence' => 1],
                    ['name' => 'Resepsi', 'sequence' => 2],
                ],
                'checklist' => [
                    ['key' => 'venue_confirmed', 'label' => 'Konfirmasi venue akad dan resepsi', 'required' => true],
                    ['key' => 'vendor_confirmed', 'label' => 'Konfirmasi seluruh vendor', 'required' => true],
                    ['key' => 'guest_count_final', 'label' => 'Finalisasi jumlah tamu', 'required' => true],
                    ['key' => 'rundown_shared', 'label' => 'Bagikan rundown ke tim dan keluarga', 'required' => true],
                    ['key' => 'akad_schedule', 'label' => 'Tetapkan jadwal akad nikah', 'required' => true],
                    ['key' => 'reception_decor', 'label' => 'Finalisasi dekorasi resepsi', 'required' => false],
                ],
                'public_schedule' => [
                    ['title' => 'Prosesi Akad Nikah', 'description' => 'Upacara akad nikah dan penyerahan mahar.'],
                    ['title' => 'Undangan & Ramah Tamah', 'description' => 'Penyambutan tamu undangan.'],
                    ['title' => 'Resepsi', 'description' => 'Makan malam dan hiburan.'],
                    ['title' => 'Sesi Foto Keluarga', 'description' => 'Foto bersama kedua keluarga.'],
                ],
                'run_of_show' => [
                    ['title' => 'Persiapan Venue', 'description' => 'Pengecekan kursi, dekorasi dan sound system.'],
                    ['title' => 'Kedatangan Keluarga Inti', 'description' => 'Keluarga inti tiba dan bersiap.'],
                    ['title' => 'Prosesi Akad Nikah', 'description' => 'Akad nikah bersama penghulu.'],
                    ['title' => 'Ramah Tamah', 'description' => 'Menerima ucapan dan doa dari tamu.'],
                    ['title' => 'Resepsi Makan Malam', 'description' => 'Makan malam bersama tamu.'],
                    ['title' => 'Sesi Foto', 'description' => 'Sesi foto keluarga dan tamu.'],
                ],
                'vendor_categories' => ['catering', 'dekorasi', 'fotografi', 'musik', 'makeup'],
                'reception' => ['doors' => 1, 'seating' => 'assigned', 'welcome_drink' => true],
                'invitation_defaults' => ['digital' => true, 'rsvp' => true, 'personal_url' => true],
                'guestbook_defaults' => ['enabled' => true, 'moderation' => true],
            ],
            [
                'name' => 'Pernikahan Satu Sesi',
                'slug' => 'satu-sesi',
                'description' => 'Akad dan resepsi digabung dalam satu sesi singkat untuk acara intim.',
                'days' => 1,
                'phases' => [
                    ['name' => 'Persiapan', 'sequence' => 0],
                    ['name' => 'Acara Inti', 'sequence' => 1],
                ],
                'checklist' => [
                    ['key' => 'venue_confirmed', 'label' => 'Konfirmasi venue', 'required' => true],
                    ['key' => 'vendor_confirmed', 'label' => 'Konfirmasi vendor utama', 'required' => true],
                    ['key' => 'guest_count_final', 'label' => 'Finalisasi jumlah tamu', 'required' => true],
                    ['key' => 'rundown_shared', 'label' => 'Bagikan rundown singkat', 'required' => true],
                    ['key' => 'audio_check', 'label' => 'Cek audio dan pencahayaan', 'required' => true],
                    ['key' => 'mahar_ready', 'label' => 'Siapkan mahar dan cincin', 'required' => false],
                ],
                'public_schedule' => [
                    ['title' => 'Penyambutan Tamu', 'description' => 'Registrasi dan penyambutan tamu.'],
                    ['title' => 'Akad & Resepsi Gabungan', 'description' => 'Akad singkat dilanjutkan resepsi di tempat yang sama.'],
                    ['title' => 'Ucapan Selamat', 'description' => 'Sesi ucapan selamat dan foto bersama.'],
                ],
                'run_of_show' => [
                    ['title' => 'Standby Tim', 'description' => 'Seluruh tim siap di posisi.'],
                    ['title' => 'Kedatangan Tamu', 'description' => 'Pintu dibuka dan tamu masuk.'],
                    ['title' => 'Akad Nikah', 'description' => 'Prosesi akad singkat.'],
                    ['title' => 'Resepsi', 'description' => 'Makan bersama dan hiburan akustik.'],
                    ['title' => 'Sesi Foto & Ucapan', 'description' => 'Foto bersama dan penutupan.'],
                ],
                'vendor_categories' => ['catering', 'dekorasi', 'fotografi'],
                'reception' => ['doors' => 1, 'seating' => 'free', 'welcome_drink' => false],
                'invitation_defaults' => ['digital' => true, 'rsvp' => true, 'personal_url' => false],
                'guestbook_defaults' => ['enabled' => true, 'moderation' => false],
            ],
            [
                'name' => 'Pernikahan Adat dengan Beberapa Rangkaian',
                'slug' => 'adat-rangkaian',
                'description' => 'Pernikahan adat dengan rangkaian prosesi bertahap mengikuti tradisi keluarga.',
                'days' => 1,
                'phases' => [
                    ['name' => 'Persiapan Adat', 'sequence' => 0],
                    ['name' => 'Prosesi Adat', 'sequence' => 1],
                    ['name' => 'Akad Nikah', 'sequence' => 2],
                    ['name' => 'Resepsi', 'sequence' => 3],
                ],
                'checklist' => [
                    ['key' => 'adat_elders', 'label' => 'Koordinasi tokoh adat', 'required' => true],
                    ['key' => 'prosesi_props', 'label' => 'Siapkan perlengkapan prosesi', 'required' => true],
                    ['key' => 'venue_confirmed', 'label' => 'Konfirmasi venue setiap rangkaian', 'required' => true],
                    ['key' => 'vendor_confirmed', 'label' => 'Konfirmasi vendor adat', 'required' => true],
                    ['key' => 'rundown_shared', 'label' => 'Bagikan rundown rangkaian', 'required' => true],
                    ['key' => 'adat_attire', 'label' => 'Finalisasi busana adat', 'required' => true],
                ],
                'public_schedule' => [
                    ['title' => 'Prosesi Adat Pembuka', 'description' => 'Rangkaian pembuka sesuai tradisi.'],
                    ['title' => 'Akad Nikah', 'description' => 'Upacara akad nikah resmi.'],
                    ['title' => 'Prosesi Adat Penutup', 'description' => 'Rangkaian adat penutup.'],
                    ['title' => 'Resepsi', 'description' => 'Resepsi dan ramah tamah tamu.'],
                ],
                'run_of_show' => [
                    ['title' => 'Persiapan Perlengkapan Adat', 'description' => 'Penataan perlengkapan adat.'],
                    ['title' => 'Prosesi Adat Pembuka', 'description' => 'Rangkaian pembuka.'],
                    ['title' => 'Akad Nikah', 'description' => 'Upacara akad.'],
                    ['title' => 'Prosesi Adat Penutup', 'description' => 'Rangkaian penutup.'],
                    ['title' => 'Resepsi', 'description' => 'Resepsi tamu.'],
                    ['title' => 'Ramah Tamah', 'description' => 'Ucapan dan foto bersama.'],
                ],
                'vendor_categories' => ['catering', 'dekorasi', 'fotografi', 'penari', 'adat'],
                'reception' => ['doors' => 2, 'seating' => 'assigned', 'welcome_drink' => true],
                'invitation_defaults' => ['digital' => true, 'rsvp' => true, 'personal_url' => true],
                'guestbook_defaults' => ['enabled' => true, 'moderation' => true],
            ],
            [
                'name' => 'Resepsi dengan Beberapa Pintu Masuk',
                'slug' => 'multi-pintu',
                'description' => 'Resepsi berskala besar dengan beberapa pintu masuk dan zonasi tamu.',
                'days' => 1,
                'phases' => [
                    ['name' => 'Persiapan', 'sequence' => 0],
                    ['name' => 'Resepsi', 'sequence' => 1],
                ],
                'checklist' => [
                    ['key' => 'entrances_planned', 'label' => 'Rencanakan zona dan pintu masuk', 'required' => true],
                    ['key' => 'staff_per_door', 'label' => 'Tetapkan petugas tiap pintu', 'required' => true],
                    ['key' => 'vendor_confirmed', 'label' => 'Konfirmasi seluruh vendor', 'required' => true],
                    ['key' => 'guest_count_final', 'label' => 'Finalisasi jumlah tamu per zona', 'required' => true],
                    ['key' => 'signage_ready', 'label' => 'Siapkan signage dan papan arah', 'required' => true],
                    ['key' => 'rundown_shared', 'label' => 'Bagikan rundown ke petugas', 'required' => false],
                ],
                'public_schedule' => [
                    ['title' => 'Pembukaan Resepsi', 'description' => 'Pintu masuk dibuka serentak.'],
                    ['title' => 'Sesi Makan', 'description' => 'Pergantian sesi makan tamu.'],
                    ['title' => 'Hiburan', 'description' => 'Penampilan musik dan acara panggung.'],
                    ['title' => 'Penutupan', 'description' => 'Ucapan penutup dari keluarga.'],
                ],
                'run_of_show' => [
                    ['title' => 'Briefing Petugas Pintu', 'description' => 'Arahan pembagian pintu dan zona.'],
                    ['title' => 'Pembukaan Pintu', 'description' => 'Semua pintu masuk dibuka.'],
                    ['title' => 'Sesi Makan 1', 'description' => 'Sesi makan tamu zona A dan B.'],
                    ['title' => 'Sesi Makan 2', 'description' => 'Sesi makan tamu zona C dan D.'],
                    ['title' => 'Hiburan Panggung', 'description' => 'Penampilan hiburan utama.'],
                    ['title' => 'Penutupan & Foto', 'description' => 'Penutupan dan foto bersama.'],
                ],
                'vendor_categories' => ['catering', 'dekorasi', 'fotografi', 'keamanan', 'parkir'],
                'reception' => ['doors' => 4, 'seating' => 'zoned', 'welcome_drink' => true],
                'invitation_defaults' => ['digital' => true, 'rsvp' => true, 'personal_url' => true],
                'guestbook_defaults' => ['enabled' => true, 'moderation' => true],
            ],
            [
                'name' => 'Pernikahan Multi-day',
                'slug' => 'multi-day',
                'description' => 'Pernikahan yang digelar selama beberapa hari dengan sesi dan rangkaian terpisah.',
                'days' => 2,
                'phases' => [
                    ['name' => 'Hari Pertama', 'sequence' => 0],
                    ['name' => 'Hari Kedua', 'sequence' => 1],
                ],
                'checklist' => [
                    ['key' => 'venue_confirmed', 'label' => 'Konfirmasi venue setiap hari', 'required' => true],
                    ['key' => 'vendor_confirmed', 'label' => 'Konfirmasi vendor multi-hari', 'required' => true],
                    ['key' => 'akomodasi', 'label' => 'Atur akomodasi keluarga dan tamu', 'required' => true],
                    ['key' => 'transport', 'label' => 'Atur transportasi antar lokasi', 'required' => true],
                    ['key' => 'rundown_shared', 'label' => 'Bagikan rundown per hari', 'required' => true],
                    ['key' => 'guest_count_final', 'label' => 'Finalisasi jumlah tamu per hari', 'required' => true],
                ],
                'public_schedule' => [
                    ['title' => 'Hari 1 — Akad Nikah', 'description' => 'Upacara akad nikah hari pertama.'],
                    ['title' => 'Hari 1 — Makan Malam Keluarga', 'description' => 'Makan malam intim keluarga.'],
                    ['title' => 'Hari 2 — Resepsi', 'description' => 'Resepsi utama hari kedua.'],
                    ['title' => 'Hari 2 — Penutupan', 'description' => 'Ucapan penutup dan foto bersama.'],
                ],
                'run_of_show' => [
                    ['title' => 'Hari 1 — Persiapan Akad', 'description' => 'Persiapan venue akad.'],
                    ['title' => 'Hari 1 — Akad Nikah', 'description' => 'Prosesi akad nikah.'],
                    ['title' => 'Hari 1 — Makan Malam Keluarga', 'description' => 'Makan malam keluarga inti.'],
                    ['title' => 'Hari 2 — Persiapan Resepsi', 'description' => 'Penataan venue resepsi.'],
                    ['title' => 'Hari 2 — Resepsi', 'description' => 'Resepsi utama.'],
                    ['title' => 'Hari 2 — Penutupan', 'description' => 'Penutupan dan foto bersama.'],
                ],
                'vendor_categories' => ['catering', 'dekorasi', 'fotografi', 'akomodasi', 'transportasi'],
                'reception' => ['doors' => 2, 'seating' => 'assigned', 'welcome_drink' => true],
                'invitation_defaults' => ['digital' => true, 'rsvp' => true, 'personal_url' => true],
                'guestbook_defaults' => ['enabled' => true, 'moderation' => true],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $blueprint
     * @return array<string, mixed>
     */
    protected function config(array $blueprint): array
    {
        return [
            'phases' => $blueprint['phases'],
            'checklist' => $blueprint['checklist'],
            'public_schedule' => $blueprint['public_schedule'],
            'run_of_show' => $blueprint['run_of_show'],
            'team_roles' => $this->teamRoles(),
            'vendor_categories' => $blueprint['vendor_categories'],
            'reception' => $blueprint['reception'],
            'invitation_defaults' => $blueprint['invitation_defaults'],
            'guestbook_defaults' => $blueprint['guestbook_defaults'],
        ];
    }

    /**
     * @return array<int, array{role: string, label: string, qty: int}>
     */
    protected function teamRoles(): array
    {
        $roles = [];

        foreach (self::TEAM_ROLE_QUANTITIES as $role => $qty) {
            $roles[] = [
                'role' => $role,
                'label' => EventStaffRole::from($role)->label(),
                'qty' => $qty,
            ];
        }

        return $roles;
    }
}
