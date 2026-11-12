<?php

namespace App\Http\Controllers\VendorPortal;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\VendorPortalAccessService;
use Illuminate\Http\Request;

class VendorPortalInvitationController extends Controller
{
    public function __construct(
        private readonly VendorPortalAccessService $access,
        private readonly AuditService $audit,
    ) {}

    public function show(Request $request, string $token)
    {
        $membership = $this->access->findPendingInvitation($token);

        abort_if($membership === null, 404);

        return view('vendor-portal.invitation', [
            'membership' => $membership,
            'token' => $token,
            'expired' => $membership->invitation_expires_at?->isPast() ?? true,
            'emailMatches' => strcasecmp(
                (string) $membership->invited_email,
                (string) $request->user()->email,
            ) === 0,
        ]);
    }

    public function accept(Request $request, string $token)
    {
        $membership = $this->access->accept($token, $request->user());

        $this->audit->record(
            'vendor.portal.invitation.accept',
            'Undangan portal vendor diterima.',
            null,
            $request->user(),
            $membership,
            ['vendor_id' => $membership->vendor_id],
        );

        return redirect()
            ->route('vendor-portal.index')
            ->with('status', 'Undangan diterima. Engagement yang dibagikan kini dapat diakses.');
    }
}
