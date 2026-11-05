<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\SeatingTable;
use App\Models\TableAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SeatingService
{
    public function __construct(protected AuditService $audit) {}

    public function assign(SeatingTable $table, Guest $guest, ?int $seatNumber, User $actor): TableAssignment
    {
        if ($table->event_id !== $guest->event_id) {
            throw ValidationException::withMessages(['guest_id' => 'Tamu berasal dari event yang berbeda.']);
        }

        return DB::transaction(function () use ($table, $guest, $seatNumber, $actor) {
            $lockedTable = SeatingTable::where('id', $table->id)->lockForUpdate()->firstOrFail();

            $occupiedCount = TableAssignment::where('seating_table_id', $lockedTable->id)
                ->where('guest_id', '!=', $guest->id)
                ->count();

            if ($occupiedCount >= $lockedTable->capacity) {
                throw ValidationException::withMessages(['seating_table_id' => 'Meja ini sudah penuh.']);
            }

            if ($seatNumber !== null) {
                if ($seatNumber < 1 || $seatNumber > $lockedTable->capacity) {
                    throw ValidationException::withMessages([
                        'seat_number' => "Nomor kursi harus antara 1 dan {$lockedTable->capacity}.",
                    ]);
                }

                $taken = TableAssignment::where('seating_table_id', $lockedTable->id)
                    ->where('seat_number', $seatNumber)
                    ->where('guest_id', '!=', $guest->id)
                    ->exists();

                if ($taken) {
                    throw ValidationException::withMessages(['seat_number' => 'Nomor kursi sudah terisi.']);
                }
            }

            $assignment = TableAssignment::updateOrCreate(
                ['event_id' => $lockedTable->event_id, 'guest_id' => $guest->id],
                ['seating_table_id' => $lockedTable->id, 'seat_number' => $seatNumber]
            );

            $this->audit->record(
                'seating.assigned',
                "{$guest->name} ditempatkan di meja {$lockedTable->name}",
                $lockedTable->event,
                $actor,
                $assignment
            );

            return $assignment->fresh();
        });
    }

    public function remove(TableAssignment $assignment, User $actor): void
    {
        $this->audit->record('seating.removed', 'Penempatan tamu dihapus', $assignment->event, $actor, $assignment);
        $assignment->delete();
    }
}
