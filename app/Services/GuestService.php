<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Guest;
use App\Models\GuestbookMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GuestService
{
    public function __construct(
        protected InvitationService $invitations,
        protected AuditService $audit,
    ) {
    }

    public function create(Event $event, array $data, User $actor): Guest
    {
        return DB::transaction(function () use ($event, $data, $actor) {
            $guest = Guest::create([
                'event_id' => $event->id,
                'guest_category_id' => $data['guest_category_id'] ?? null,
                'guest_group_id' => $data['guest_group_id'] ?? null,
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => $data['status'] ?? 'active',
                'max_attendees' => $data['max_attendees'] ?? 2,
                'rsvp_status' => 'pending',
                'created_by' => $actor->id,
            ]);

            $this->invitations->issueForGuest($guest);
            $this->audit->record('guest.created', "Tamu ditambahkan: {$guest->name}", $event, $actor, $guest);
            $this->audit->activity($event, 'guest.created', "Tamu baru: {$guest->name}", $guest, $actor);

            return $guest->fresh(['category', 'group', 'invitation']);
        });
    }

    public function update(Guest $guest, array $data, User $actor): Guest
    {
        $guest->update([
            'guest_category_id' => $data['guest_category_id'] ?? null,
            'guest_group_id' => $data['guest_group_id'] ?? null,
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? $guest->status->value,
            'max_attendees' => $data['max_attendees'] ?? $guest->max_attendees,
        ]);

        $this->audit->record('guest.updated', "Tamu diperbarui: {$guest->name}", $guest->event, $actor, $guest);

        return $guest->fresh();
    }

    public function delete(Guest $guest, User $actor): void
    {
        DB::transaction(function () use ($guest, $actor) {
            $guest->tokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $this->audit->record('guest.deleted', "Tamu dihapus: {$guest->name}", $guest->event, $actor, $guest);
            $guest->delete();
        });
    }

    public function regenerateTokens(Guest $guest, User $actor): void
    {
        $this->invitations->issueForGuest($guest);
        $this->audit->record('invitation.regenerated', "Token undangan diperbarui: {$guest->name}", $guest->event, $actor, $guest);
    }

    /**
     * @return array{imported:int,skipped:int,errors:array<int,string>}
     */
    public function importCsv(Event $event, string $path, User $actor): array
    {
        $handle = fopen($path, 'r');

        if (! $handle) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['File tidak dapat dibaca.']];
        }

        $header = fgetcsv($handle);
        $header = $header ? array_map(fn ($h) => strtolower(trim((string) $h)), $header) : [];
        $required = ['name'];

        if (array_diff($required, $header)) {
            fclose($handle);

            return ['imported' => 0, 'skipped' => 0, 'errors' => ['CSV harus memiliki kolom "name".']];
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $row = 1;

        while (($line = fgetcsv($handle)) !== false) {
            $row++;
            $data = array_combine($header, array_pad($line, count($header), null));

            if (! $data || blank(trim((string) ($data['name'] ?? '')))) {
                $skipped++;
                $errors[] = "Baris {$row}: nama kosong.";

                continue;
            }

            $name = trim((string) $data['name']);
            $email = trim((string) ($data['email'] ?? '')) ?: null;
            $phone = trim((string) ($data['phone'] ?? '')) ?: null;

            if ($email && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                $errors[] = "Baris {$row}: email tidak valid ({$email}).";

                continue;
            }

            $exists = $event->guests()
                ->where(fn ($q) => $q->where('name', $name)
                    ->when($email, fn ($qq) => $qq->orWhere('email', $email)))
                ->exists();

            if ($exists) {
                $skipped++;
                $errors[] = "Baris {$row}: tamu sudah ada ({$name}).";

                continue;
            }

            $categoryName = trim((string) ($data['category'] ?? ''));
            $groupName = trim((string) ($data['group'] ?? ''));

            $category = $categoryName
                ? $event->categories()->firstOrCreate(
                    ['slug' => \Illuminate\Support\Str::slug($categoryName)],
                    ['name' => $categoryName]
                )
                : null;

            $group = $groupName
                ? $event->groups()->firstOrCreate(['name' => $groupName])
                : null;

            $this->create($event, [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'guest_category_id' => $category?->id,
                'guest_group_id' => $group?->id,
                'max_attendees' => max(1, (int) ($data['max_attendees'] ?? 2)),
                'notes' => $data['notes'] ?? null,
            ], $actor);

            $imported++;
        }

        fclose($handle);

        $this->audit->record('guest.imported', "Import CSV: {$imported} tamu", $event, $actor, null, ['imported' => $imported, 'skipped' => $skipped]);

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20)];
    }

    public function bulkDelete(Event $event, array $ids, User $actor): int
    {
        $guests = $event->guests()->whereIn('id', $ids)->get();
        $count = 0;

        foreach ($guests as $guest) {
            $this->delete($guest, $actor);
            $count++;
        }

        return $count;
    }

    /**
     * @return array<int,array<int,string>>
     */
    public function exportRows(Event $event): array
    {
        $rows = [[
            'Name', 'Email', 'Phone', 'Category', 'Group', 'RSVP Status', 'Attendance',
            'Max Attendees', 'Check-in', 'Invitation Status', 'Created At',
        ]];

        $event->guests()->with(['category', 'group', 'rsvp', 'invitation'])
            ->orderBy('name')
            ->chunk(200, function ($guests) use (&$rows) {
                foreach ($guests as $guest) {
                    $rows[] = [
                        $guest->name,
                        $guest->email,
                        $guest->phone,
                        $guest->category?->name,
                        $guest->group?->name,
                        $guest->rsvp_status->label(),
                        $guest->rsvp?->attendance_count ?? 0,
                        $guest->max_attendees,
                        $guest->checked_in_at?->format('Y-m-d H:i') ?? '',
                        $guest->invitation?->status->label() ?? 'Not sent',
                        $guest->created_at?->format('Y-m-d H:i'),
                    ];
                }
            });

        return $rows;
    }

    public function createMessage(Guest $guest, array $data): GuestbookMessage
    {
        return GuestbookMessage::create([
            'event_id' => $guest->event_id,
            'guest_id' => $guest->id,
            'sender_name' => $guest->name,
            'message' => trim($data['message']),
            'emoji' => $data['emoji'] ?? null,
            'visibility' => $data['visibility'] ?? 'public',
            'status' => 'pending',
            'ip_address' => request()?->ip(),
        ]);
    }
}
