<?php

namespace App\Console\Commands;

use App\Services\WorkspaceInvitationService;
use Illuminate\Console\Command;

class ExpireWorkspaceInvitations extends Command
{
    protected $signature = 'vowspace:expire-invitations';

    protected $description = 'Mark stale workspace invitations as expired.';

    public function handle(WorkspaceInvitationService $service): int
    {
        $count = $service->expireStaleInvitations();
        $this->info("Expired {$count} invitation(s).");

        return self::SUCCESS;
    }
}
