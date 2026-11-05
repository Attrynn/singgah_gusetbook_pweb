<?php

namespace App\Http\Controllers\Guest;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LiveWallController extends Controller
{
    public function show(string $slug)
    {
        $event = Event::publiclyVisible()
            ->where('slug', $slug)
            ->where('live_wall_enabled', true)
            ->firstOrFail();

        $messages = $event->messages()
            ->publicVisible()
            ->latest()
            ->take(24)
            ->get();

        return view('live-wall.show', compact('event', 'messages'));
    }

    public function messages(Request $request, string $slug)
    {
        $event = Event::publiclyVisible()
            ->where('slug', $slug)
            ->where('live_wall_enabled', true)
            ->firstOrFail();

        $query = $event->messages()->publicVisible()->latest();

        if ($since = $request->integer('since_id')) {
            $query->where('id', '>', $since);
        }

        $messages = $query->take(30)->get()->map(fn ($message) => [
            'id' => $message->id,
            'sender' => $message->sender_name,
            'message' => $message->message,
            'emoji' => $message->emoji,
            'created_at' => $message->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'messages' => $messages,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
