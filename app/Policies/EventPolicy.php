<?php

namespace App\Policies;

use App\Authorization\PermissionRegistry;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use App\Services\PermissionService;

class EventPolicy
{
    public function __construct(private readonly PermissionService $permissions) {}

    public function viewAny(User $user, ?Organization $organization = null): bool
    {
        if ($user->isSuperadmin() || $user->isPlatformSuperadmin()) {
            return true;
        }

        if ($organization === null) {
            return $user->organizationMemberships()->where('status', 'active')->exists();
        }

        return $this->permissions->hasOrgPermission($user, $organization, PermissionRegistry::EVENT_VIEW);
    }

    public function view(User $user, Event $event): bool
    {
        return $user->canAccessEvent($event);
    }

    public function create(User $user, ?Organization $organization = null): bool
    {
        if ($user->isSuperadmin() || $user->isPlatformSuperadmin()) {
            return true;
        }

        if ($organization === null) {
            return false;
        }

        return $this->permissions->hasOrgPermission($user, $organization, PermissionRegistry::ORG_MANAGE_EVENTS);
    }

    public function update(User $user, Event $event): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        if ($this->isArchived($event)) {
            return false;
        }

        return $this->permissions->hasEventPermission($user, $event, PermissionRegistry::EVENT_UPDATE);
    }

    public function delete(User $user, Event $event): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return $this->permissions->hasEventPermission($user, $event, PermissionRegistry::EVENT_DELETE);
    }

    public function publish(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::EVENT_UPDATE);
    }

    public function manageSetup(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::EVENT_SETUP_MANAGE)
            && ! $this->isArchived($event);
    }

    public function manageTeam(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::EVENT_TEAM_MANAGE)
            && ! $this->isArchived($event);
    }

    public function manageSchedule(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::EVENT_SCHEDULE_MANAGE)
            && ! $this->isArchived($event);
    }

    public function viewRunOfShow(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::EVENT_RUN_OF_SHOW_VIEW);
    }

    public function manageRunOfShow(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::EVENT_RUN_OF_SHOW_MANAGE)
            && ! $this->isArchived($event);
    }

    public function archive(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::EVENT_ARCHIVE)
            && ! $this->isArchived($event);
    }

    public function unarchive(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::EVENT_ARCHIVE)
            && $this->isArchived($event);
    }

    /**
     * Platform superadmins bypass every event permission check.
     */
    protected function allowsPermission(User $user, Event $event, string $permission): bool
    {
        if ($user->isSuperadmin() || $user->isPlatformSuperadmin()) {
            return true;
        }

        return $this->permissions->hasEventPermission($user, $event, $permission);
    }

    protected function isArchived(Event $event): bool
    {
        return $event->status === EventStatus::Archived;
    }

    public function manageGuests(User $user, Event $event): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return $this->permissions->hasEventPermission($user, $event, PermissionRegistry::GUEST_CREATE);
    }

    public function viewGuests(User $user, Event $event): bool
    {
        if ($user->canAccessEvent($event)) {
            return true;
        }

        return $this->permissions->hasEventPermission($user, $event, PermissionRegistry::GUEST_VIEW);
    }

    public function viewCheckins(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::CHECKIN_VIEW);
    }

    public function scanCheckins(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::CHECKIN_SCAN);
    }

    public function createCheckins(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::CHECKIN_CREATE);
    }

    public function reportCheckins(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::CHECKIN_REPORT);
    }

    public function moderate(User $user, Event $event): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return $this->permissions->hasEventPermission($user, $event, PermissionRegistry::MEDIA_MODERATE);
    }

    public function manageSettings(User $user, Event $event): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return $this->permissions->hasEventPermission($user, $event, PermissionRegistry::EVENT_MANAGE_SETTINGS);
    }

    // ---------------------------------------------------------------------
    // Milestone 6 — Event operations & staff coordination
    // ---------------------------------------------------------------------

    public function viewOperations(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::OPERATIONS_VIEW);
    }

    public function manageOperations(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::OPERATIONS_MANAGE)
            && ! $this->isArchived($event);
    }

    public function closeOperations(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::OPERATIONS_CLOSE)
            && ! $this->isArchived($event);
    }

    public function viewStaff(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::STAFF_VIEW);
    }

    public function inviteStaff(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::STAFF_INVITE)
            && ! $this->isArchived($event);
    }

    public function manageStaff(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::STAFF_MANAGE)
            && ! $this->isArchived($event);
    }

    public function manageStaffPermissions(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::STAFF_PERMISSIONS_MANAGE)
            && ! $this->isArchived($event);
    }

    public function viewStations(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::STATION_VIEW);
    }

    public function manageStations(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::STATION_MANAGE)
            && ! $this->isArchived($event);
    }

    public function assignStations(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::STATION_ASSIGN)
            && ! $this->isArchived($event);
    }

    public function viewTasks(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::TASK_VIEW);
    }

    public function createTasks(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::TASK_CREATE)
            && ! $this->isArchived($event);
    }

    public function assignTasks(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::TASK_ASSIGN)
            && ! $this->isArchived($event);
    }

    public function manageTasks(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::TASK_MANAGE)
            && ! $this->isArchived($event);
    }

    public function viewIncidents(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::INCIDENT_VIEW);
    }

    public function reportIncidents(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::INCIDENT_CREATE)
            && ! $this->isArchived($event);
    }

    public function manageIncidents(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::INCIDENT_MANAGE)
            && ! $this->isArchived($event);
    }

    public function resolveIncidents(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::INCIDENT_RESOLVE)
            && ! $this->isArchived($event);
    }

    public function viewAnnouncements(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::ANNOUNCEMENT_VIEW);
    }

    public function manageAnnouncements(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::ANNOUNCEMENT_MANAGE)
            && ! $this->isArchived($event);
    }

    public function publishAnnouncements(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::ANNOUNCEMENT_PUBLISH)
            && ! $this->isArchived($event);
    }

    public function viewReports(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::REPORTS_VIEW);
    }

    public function exportReports(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::REPORTS_EXPORT);
    }

    // ---------------------------------------------------------------------
    // Milestone 7 — Vendor & service provider management
    // ---------------------------------------------------------------------

    public function viewVendors(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::VENDOR_VIEW);
    }

    public function createVendors(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::VENDOR_CREATE)
            && ! $this->isArchived($event);
    }

    public function updateVendors(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::VENDOR_UPDATE)
            && ! $this->isArchived($event);
    }

    public function archiveVendors(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::VENDOR_ARCHIVE)
            && ! $this->isArchived($event);
    }

    public function manageVendorContacts(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::VENDOR_CONTACT_MANAGE)
            && ! $this->isArchived($event);
    }

    public function manageVendorCatalog(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::VENDOR_CATALOG_MANAGE)
            && ! $this->isArchived($event);
    }

    public function manageVendorRequirements(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::VENDOR_REQUIREMENT_MANAGE)
            && ! $this->isArchived($event);
    }

    public function viewVendorProposals(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::VENDOR_PROPOSAL_VIEW);
    }

    public function manageVendorProposals(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::VENDOR_PROPOSAL_MANAGE)
            && ! $this->isArchived($event);
    }

    public function selectVendors(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::VENDOR_SELECT)
            && ! $this->isArchived($event);
    }

    public function viewVendorEngagements(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::VENDOR_ENGAGEMENT_VIEW);
    }

    public function manageVendorEngagements(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::VENDOR_ENGAGEMENT_MANAGE)
            && ! $this->isArchived($event);
    }

    public function viewVendorAgreements(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::VENDOR_AGREEMENT_VIEW);
    }

    public function manageVendorAgreements(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::VENDOR_AGREEMENT_MANAGE)
            && ! $this->isArchived($event);
    }

    public function viewVendorCommercial(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::VENDOR_COMMERCIAL_VIEW);
    }

    public function approveVendorCommercial(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::VENDOR_COMMERCIAL_APPROVE)
            && ! $this->isArchived($event);
    }

    public function viewVendorDeliverables(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::VENDOR_DELIVERABLE_VIEW);
    }

    public function reviewVendorDeliverables(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::VENDOR_DELIVERABLE_REVIEW)
            && ! $this->isArchived($event);
    }

    public function manageVendorLogistics(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::VENDOR_LOGISTICS_MANAGE)
            && ! $this->isArchived($event);
    }

    public function manageVendorPortal(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::VENDOR_PORTAL_MANAGE)
            && ! $this->isArchived($event);
    }

    public function approveVendorChanges(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::VENDOR_CHANGE_APPROVE)
            && ! $this->isArchived($event);
    }

    public function viewVendorPerformance(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::VENDOR_PERFORMANCE_VIEW);
    }

    public function reviewVendorPerformance(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::VENDOR_PERFORMANCE_REVIEW)
            && ! $this->isArchived($event);
    }

    public function viewVendorReports(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::VENDOR_REPORT_VIEW);
    }

    public function exportVendorReports(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::VENDOR_REPORT_EXPORT);
    }

    public function closeVendorEngagements(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::VENDOR_ENGAGEMENT_CLOSE)
            && ! $this->isArchived($event);
    }
}
