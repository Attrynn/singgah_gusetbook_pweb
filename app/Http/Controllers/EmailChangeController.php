<?php

namespace App\Http\Controllers;

use App\Services\EmailChangeService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class EmailChangeController extends Controller
{
    public function __construct(protected EmailChangeService $emails) {}

    public function confirm(Request $request, string $token)
    {
        try {
            $user = $this->emails->confirm($token);
        } catch (ValidationException $exception) {
            return redirect()->route('login')->withErrors($exception->errors());
        }

        if ($request->user()?->is($user)) {
            $target = $user->isSuperadmin()
                ? route('admin.profile.edit')
                : route('owner.profile.edit');

            return redirect($target)->with('status', 'Email berhasil diverifikasi dan diperbarui.');
        }

        return redirect()->route('login')->with(
            'status',
            'Email berhasil diverifikasi. Silakan login menggunakan email baru Anda.'
        );
    }
}
