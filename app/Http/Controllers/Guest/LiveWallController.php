<?php

namespace App\Http\Controllers\Guest;

use App\Models\Event;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Throwable;

class LiveWallController extends Controller
{
    public function show(string $slug)
    {
        $event = Event::publiclyVisible()
            ->where('slug', $slug)
            ->where('live_wall_enabled', true)
            ->firstOrFail();

        // Initial render is ascending so the client can append from a stable
        // cursor without re-sorting or skipping messages.
        $messages = $event->messages()
            ->publicVisible()
            ->orderByDesc('id')
            ->take(24)
            ->get()
            ->sortBy('id')
            ->values();

        $cursor = (int) ($messages->max('id') ?? 0);

        return view('live-wall.show', compact('event', 'messages', 'cursor'));
    }

    public function messages(Request $request, string $slug)
    {
        try {
            $event = Event::publiclyVisible()
                ->where('slug', $slug)
                ->where('live_wall_enabled', true)
                ->firstOrFail();

            $since = max(0, $request->integer('since_id'));

            $messages = $event->messages()
                ->publicVisible()
                ->where('id', '>', $since)
                ->orderBy('id')
                ->take(30)
                ->get();

            $nextCursor = (int) ($messages->max('id') ?? $since);

            return response()->json([
                'messages' => $messages->map(fn ($message) => [
                    'id' => $message->id,
                    'sender' => $message->sender_name,
                    'message' => $message->message,
                    'emoji' => $message->emoji,
                    'created_at' => $message->created_at?->toIso8601String(),
                ])->values(),
                'next_cursor' => $nextCursor,
                'has_more' => $messages->count() === 30,
                'server_time' => now()->toIso8601String(),
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['error' => 'not_found'], 404);
        } catch (Throwable $exception) {
            Log::error('Live wall polling failed', [
                'slug' => $slug,
                'exception' => $exception,
            ]);

            return response()->json(['error' => 'temporarily_unavailable'], 503);
        }
    }
}
