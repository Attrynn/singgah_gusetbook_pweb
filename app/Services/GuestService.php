<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Guest;
use App\Models\GuestbookMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuestService
{
    public function __construct(
        protected InvitationService $invitations,
        protected AuditService $audit,
    ) {}

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
     * Strict, row-level CSV import.
     *
     * The whole file is validated before any row is written. Files that exceed
     * the configured guest limit, contain duplicate/unknown headers, malformed
     * rows or invalid values are rejected instead of being silently clamped.
     *
     * @return array{imported:int,skipped:int,errors:array<int,string>}
     */
    public function importCsv(Event $event, string $path, User $actor): array
    {
        $handle = fopen($path, 'r');

        if (! $handle) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['File tidak dapat dibaca.']];
        }

        $header = fgetcsv($handle);

        if (! is_array($header) || $header === [null]) {
            fclose($handle);

            return ['imported' => 0, 'skipped' => 0, 'errors' => ['CSV kosong atau header tidak ditemukan.']];
        }

        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $normalized = array_map(fn ($value) => $this->normalizeHeader((string) $value), $header);

        if (in_array('', $normalized, true)) {
            fclose($handle);

            return ['imported' => 0, 'skipped' => 0, 'errors' => ['CSV memiliki kolom header tanpa nama.']];
        }

        $duplicates = array_keys(array_filter(array_count_values($normalized), fn (int $count) => $count > 1));

        if ($duplicates !== []) {
            fclose($handle);

            return ['imported' => 0, 'skipped' => 0, 'errors' => ['Kolom header duplikat: '.implode(', ', $duplicates).'.']];
        }

        $known = ['name', 'email', 'phone', 'category', 'group', 'max_attendees', 'notes'];
        $unknown = array_values(array_diff($normalized, $known));

        if ($unknown !== []) {
            fclose($handle);

            return ['imported' => 0, 'skipped' => 0, 'errors' => ['Kolom tidak dikenal: '.implode(', ', $unknown).'. Hapus kolom tersebut atau gunakan format yang didokumentasikan.']];
        }

        if (! in_array('name', $normalized, true)) {
            fclose($handle);

            return ['imported' => 0, 'skipped' => 0, 'errors' => ['CSV harus memiliki kolom "name".']];
        }

        $limit = (int) config('vowspace.guest_import_limit', 1000);
        $valid = [];
        $errors = [];
        $seen = [];
        $lineNumber = 1;

        while (($line = fgetcsv($handle)) !== false) {
            $lineNumber++;

            if ($this->isEmptyLine($line)) {
                continue;
            }

            if (! mb_check_encoding(implode(',', array_map('strval', $line)), 'UTF-8')) {
                $errors[] = "Baris {$lineNumber}: encoding bukan UTF-8 yang valid.";

                continue;
            }

            if (count($line) > count($normalized)) {
                $errors[] = "Baris {$lineNumber}: jumlah kolom (".count($line).') melebihi header ('.count($normalized).').';

                continue;
            }

            if (count($line) < count($normalized)) {
                $line = array_pad($line, count($normalized), null);
            }

            $data = array_combine($normalized, $line);

            if (! is_array($data)) {
                $errors[] = "Baris {$lineNumber}: format baris tidak dapat dibaca.";

                continue;
            }

            $name = trim((string) ($data['name'] ?? ''));

            if ($name === '') {
                $errors[] = "Baris {$lineNumber}: nama kosong.";

                continue;
            }

            $email = trim((string) ($data['email'] ?? '')) ?: null;

            if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Baris {$lineNumber}: email tidak valid ({$email}).";

                continue;
            }

            $maxRaw = trim((string) ($data['max_attendees'] ?? ''));
            $maxAttendees = 2;

            if ($maxRaw !== '') {
                if (! ctype_digit($maxRaw)) {
                    $errors[] = "Baris {$lineNumber}: max_attendees harus bilangan bulat (ditemukan \"{$maxRaw}\").";

                    continue;
                }

                $maxAttendees = (int) $maxRaw;

                if ($maxAttendees < 1 || $maxAttendees > 20) {
                    $errors[] = "Baris {$lineNumber}: max_attendees harus antara 1 dan 20.";

                    continue;
                }
            }

            $dedupeKey = mb_strtolower($name).'|'.mb_strtolower((string) $email);

            if (isset($seen[$dedupeKey])) {
                $errors[] = "Baris {$lineNumber}: tamu duplikat di dalam file ({$name}).";

                continue;
            }

            $seen[$dedupeKey] = true;

            $exists = $event->guests()
                ->where(fn ($query) => $query->where('name', $name)
                    ->when($email, fn ($nested) => $nested->orWhere('email', $email)))
                ->exists();

            if ($exists) {
                $errors[] = "Baris {$lineNumber}: tamu sudah ada ({$name}).";

                continue;
            }

            $valid[] = [
                'row' => $lineNumber,
                'name' => $name,
                'email' => $email,
                'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
                'category' => trim((string) ($data['category'] ?? '')) ?: null,
                'group' => trim((string) ($data['group'] ?? '')) ?: null,
                'max_attendees' => $maxAttendees,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            ];
        }

        fclose($handle);

        if (count($valid) > $limit) {
            return [
                'imported' => 0,
                'skipped' => count($valid),
                'errors' => array_merge($errors, ['Jumlah tamu valid ('.count($valid).") melebihi batas {$limit}. Tidak ada data yang diimpor."]),
            ];
        }

        $imported = 0;

        try {
            DB::transaction(function () use ($event, $valid, $actor, &$imported): void {
                foreach ($valid as $row) {
                    $category = $row['category']
                        ? $event->categories()->firstOrCreate(
                            ['slug' => Str::slug($row['category'])],
                            ['name' => $row['category']]
                        )
                        : null;

                    $group = $row['group']
                        ? $event->groups()->firstOrCreate(['name' => $row['group']])
                        : null;

                    $this->create($event, [
                        'name' => $row['name'],
                        'email' => $row['email'],
                        'phone' => $row['phone'],
                        'guest_category_id' => $category?->id,
                        'guest_group_id' => $group?->id,
                        'max_attendees' => $row['max_attendees'],
                        'notes' => $row['notes'],
                    ], $actor);

                    $imported++;
                }
            });
        } catch (\Throwable $exception) {
            report($exception);

            return [
                'imported' => 0,
                'skipped' => count($valid),
                'errors' => array_merge($errors, ['Import dibatalkan karena kesalahan penyimpanan. Tidak ada data yang diimpor.']),
            ];
        }

        $this->audit->record('guest.imported', "Import CSV: {$imported} tamu", $event, $actor, null, [
            'imported' => $imported,
            'skipped' => count($errors),
        ]);

        return [
            'imported' => $imported,
            'skipped' => count($errors),
            'errors' => array_slice($errors, 0, 100),
        ];
    }

    protected function normalizeHeader(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace([' ', '-'], '_', $value);

        return preg_replace('/[^a-z0-9_]/', '', $value) ?? '';
    }

    /**
     * @param  array<int,string|null>  $line
     */
    protected function isEmptyLine(array $line): bool
    {
        foreach ($line as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
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
     * Stream guest rows from the database instead of materialising the whole
     * dataset in memory.
     */
    public function exportRows(Event $event): callable
    {
        return function (callable $write) use ($event): void {
            $write([
                'Name', 'Email', 'Phone', 'Category', 'Group', 'RSVP Status', 'Attendance',
                'Max Attendees', 'Check-in', 'Invitation Status', 'Created At',
            ]);

            $event->guests()->with(['category', 'group', 'rsvp', 'invitation'])
                ->orderBy('name')
                ->chunk(200, function ($guests) use ($write): void {
                    foreach ($guests as $guest) {
                        $write([
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
                        ]);
                    }
                });
        };
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
