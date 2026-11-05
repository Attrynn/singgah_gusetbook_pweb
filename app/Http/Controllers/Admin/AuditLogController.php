<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AuditLogController extends Controller
{
    public function __invoke(Request $request)
    {
        $query = AuditLog::with(['user', 'event']);

        if ($action = $request->string('action')->value()) {
            $query->where('action', $action);
        }

        if ($userId = $request->integer('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($eventId = $request->integer('event_id')) {
            $query->where('event_id', $eventId);
        }

        if ($date = $request->string('date')->value()) {
            $query->whereDate('created_at', $date);
        }

        return view('admin.audit-logs.index', [
            'logs' => $query->latest()->paginate(30)->withQueryString(),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'users' => User::orderBy('name')->get(),
            'events' => Event::orderBy('event_date')->get(),
            'filters' => $request->only(['action', 'user_id', 'event_id', 'date']),
        ]);
    }
}
