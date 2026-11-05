<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\SettingsRequest;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    public function __construct(protected AuditService $audit)
    {
    }

    public function index()
    {
        $settings = array_merge([
            'app_name' => config('vowspace.brand.name'),
            'support_email' => config('vowspace.brand.support_email'),
            'default_timezone' => 'Asia/Jakarta',
            'guestbook_auto_approve' => '0',
        ], AppSetting::allSettings());

        return view('admin.settings.index', [
            'settings' => $settings,
            'counts' => [
                'events' => Event::count(),
                'owners' => User::owners()->count(),
                'audit_logs' => AuditLog::count(),
            ],
            'recentLogs' => AuditLog::with(['user', 'event'])->latest()->take(8)->get(),
        ]);
    }

    public function update(SettingsRequest $request)
    {
        foreach (['app_name', 'support_email', 'default_timezone'] as $key) {
            AppSetting::set($key, $request->input($key));
        }

        AppSetting::set('guestbook_auto_approve', $request->boolean('guestbook_auto_approve') ? '1' : '0');
        Cache::forget('app_settings');

        $this->audit->record('settings.updated', 'Pengaturan aplikasi diperbarui', null, $request->user());

        return back()->with('status', 'Pengaturan disimpan.');
    }
}
