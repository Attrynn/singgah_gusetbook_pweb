<?php

namespace App\Services;

use App\Enums\GuestStatus;
use App\Models\Guest;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollResponse;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PollService
{
    public function __construct(protected AuditService $audit) {}

    public function vote(Poll $poll, Guest $guest, int $optionId): PollResponse
    {
        $poll->loadMissing('event');

        if ($poll->event_id !== $guest->event_id) {
            throw ValidationException::withMessages(['poll' => 'Polling bukan milik acara Anda.']);
        }

        if (! $poll->event->allowsGuestActions()) {
            throw ValidationException::withMessages(['poll' => 'Polling belum dibuka atau sudah ditutup.']);
        }

        if ($guest->status !== GuestStatus::Active) {
            throw ValidationException::withMessages(['poll' => 'Status undangan Anda tidak aktif.']);
        }

        if (! $poll->isOpen()) {
            throw ValidationException::withMessages(['poll' => 'Polling sudah ditutup.']);
        }

        $option = PollOption::where('poll_id', $poll->id)->whereKey($optionId)->first();

        if (! $option) {
            throw ValidationException::withMessages(['poll_option_id' => 'Pilihan tidak valid.']);
        }

        if (PollResponse::where('poll_id', $poll->id)->where('guest_id', $guest->id)->exists()) {
            throw ValidationException::withMessages(['poll' => 'Anda sudah memberikan suara pada polling ini.']);
        }

        try {
            return DB::transaction(function () use ($poll, $guest, $option) {
                $response = PollResponse::create([
                    'poll_id' => $poll->id,
                    'poll_option_id' => $option->id,
                    'guest_id' => $guest->id,
                ]);

                $this->audit->guestAction($poll->event, $guest, 'poll.voted', "{$guest->name} memilih \"{$option->label}\"", ['poll_id' => $poll->id]);

                return $response;
            });
        } catch (UniqueConstraintViolationException|QueryException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), 'UNIQUE')) {
                throw ValidationException::withMessages(['poll' => 'Anda sudah memberikan suara pada polling ini.']);
            }
            throw $e;
        }
    }

    /**
     * @return array{total:int,options:array<int,array{id:int,label:string,count:int,percent:float}>}
     */
    public function results(Poll $poll): array
    {
        $poll->loadMissing('options');
        $counts = $poll->responses()
            ->select('poll_option_id', DB::raw('count(*) as total'))
            ->groupBy('poll_option_id')
            ->pluck('total', 'poll_option_id');

        $total = (int) $counts->sum();

        $options = $poll->options->map(function (PollOption $option) use ($counts, $total) {
            $count = (int) ($counts[$option->id] ?? 0);

            return [
                'id' => $option->id,
                'label' => $option->label,
                'count' => $count,
                'percent' => $total > 0 ? round(($count / $total) * 100, 1) : 0.0,
            ];
        })->values()->all();

        return ['total' => $total, 'options' => $options];
    }
}
