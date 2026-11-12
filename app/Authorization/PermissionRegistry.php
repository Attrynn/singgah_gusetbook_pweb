<?php

namespace App\Authorization;

use App\Enums\EventStaffRole;

class PermissionRegistry
{
    // Platform permissions
    const PLATFORM_ACCESS = 'platform.access';

    const PLATFORM_MANAGE_USERS = 'platform.manage_users';

    const PLATFORM_MANAGE_ORGANIZATIONS = 'platform.manage_organizations';

    const PLATFORM_MANAGE_SETTINGS = 'platform.manage_settings';

    // Organization permissions
    const ORG_VIEW = 'organization.view';

    const ORG_UPDATE = 'organization.update';

    const ORG_MANAGE_MEMBERS = 'organization.manage_members';

    const ORG_INVITE_MEMBERS = 'organization.invite_members';

    const ORG_MANAGE_ROLES = 'organization.manage_roles';

    const ORG_MANAGE_EVENTS = 'organization.manage_events';

    // Event permissions
    const EVENT_VIEW = 'event.view';

    const EVENT_UPDATE = 'event.update';

    const EVENT_DELETE = 'event.delete';

    const EVENT_MANAGE_SETTINGS = 'event.manage_settings';

    const EVENT_SETUP_MANAGE = 'event.setup.manage';

    const EVENT_TEAM_MANAGE = 'event.team.manage';

    const EVENT_SCHEDULE_MANAGE = 'event.schedule.manage';

    const EVENT_RUN_OF_SHOW_VIEW = 'event.run_of_show.view';

    const EVENT_RUN_OF_SHOW_MANAGE = 'event.run_of_show.manage';

    const EVENT_ARCHIVE = 'event.archive';

    // Event operations permissions (Milestone 6)
    const OPERATIONS_VIEW = 'operations.view';

    const OPERATIONS_MANAGE = 'operations.manage';

    const OPERATIONS_CLOSE = 'operations.close';

    const STAFF_VIEW = 'staff.view';

    const STAFF_INVITE = 'staff.invite';

    const STAFF_MANAGE = 'staff.manage';

    const STAFF_PERMISSIONS_MANAGE = 'staff.permissions.manage';

    const STATION_VIEW = 'station.view';

    const STATION_MANAGE = 'station.manage';

    const STATION_ASSIGN = 'station.assign';

    const TASK_VIEW = 'task.view';

    const TASK_CREATE = 'task.create';

    const TASK_ASSIGN = 'task.assign';

    const TASK_MANAGE = 'task.manage';

    const INCIDENT_VIEW = 'incident.view';

    const INCIDENT_CREATE = 'incident.create';

    const INCIDENT_MANAGE = 'incident.manage';

    const INCIDENT_RESOLVE = 'incident.resolve';

    const ANNOUNCEMENT_VIEW = 'announcement.view';

    const ANNOUNCEMENT_MANAGE = 'announcement.manage';

    const ANNOUNCEMENT_PUBLISH = 'announcement.publish';

    const PLAYBOOK_VIEW = 'playbook.view';

    const PLAYBOOK_MANAGE = 'playbook.manage';

    // Vendor permissions (Milestone 7)
    const VENDOR_VIEW = 'vendor.view';

    const VENDOR_CREATE = 'vendor.create';

    const VENDOR_UPDATE = 'vendor.update';

    const VENDOR_ARCHIVE = 'vendor.archive';

    const VENDOR_CONTACT_MANAGE = 'vendor.contact.manage';

    const VENDOR_CATALOG_MANAGE = 'vendor.catalog.manage';

    const VENDOR_REQUIREMENT_MANAGE = 'vendor.requirement.manage';

    const VENDOR_PROPOSAL_VIEW = 'vendor.proposal.view';

    const VENDOR_PROPOSAL_MANAGE = 'vendor.proposal.manage';

    const VENDOR_SELECT = 'vendor.select';

    const VENDOR_ENGAGEMENT_VIEW = 'vendor.engagement.view';

    const VENDOR_ENGAGEMENT_MANAGE = 'vendor.engagement.manage';

    const VENDOR_AGREEMENT_VIEW = 'vendor.agreement.view';

    const VENDOR_AGREEMENT_MANAGE = 'vendor.agreement.manage';

    const VENDOR_COMMERCIAL_VIEW = 'vendor.commercial.view';

    const VENDOR_COMMERCIAL_APPROVE = 'vendor.commercial.approve';

    const VENDOR_DELIVERABLE_VIEW = 'vendor.deliverable.view';

    const VENDOR_DELIVERABLE_REVIEW = 'vendor.deliverable.review';

    const VENDOR_LOGISTICS_MANAGE = 'vendor.logistics.manage';

    const VENDOR_PORTAL_MANAGE = 'vendor.portal.manage';

    const VENDOR_CHANGE_APPROVE = 'vendor.change.approve';

    const VENDOR_PERFORMANCE_VIEW = 'vendor.performance.view';

    const VENDOR_PERFORMANCE_REVIEW = 'vendor.performance.review';

    const VENDOR_REPORT_VIEW = 'vendor.report.view';

    const VENDOR_REPORT_EXPORT = 'vendor.report.export';

    const VENDOR_ENGAGEMENT_CLOSE = 'vendor.engagement.close';

    // Vendor portal permissions (external actors, never part of staff role maps)
    const VENDOR_PORTAL_ENGAGEMENT_VIEW = 'vendor.portal.engagement.view';

    const VENDOR_PORTAL_DOCUMENT_VIEW = 'vendor.portal.document.view';

    const VENDOR_PORTAL_DELIVERABLE_SUBMIT = 'vendor.portal.deliverable.submit';

    const VENDOR_PORTAL_COMMUNICATION_SUBMIT = 'vendor.portal.communication.submit';

    const VENDOR_PORTAL_CHANGE_REQUEST_SUBMIT = 'vendor.portal.change_request.submit';

    const VENDOR_PORTAL_LOGISTICS_CONFIRM = 'vendor.portal.logistics.confirm';

    // Guest permissions
    const GUEST_VIEW = 'guest.view';

    const GUEST_CREATE = 'guest.create';

    const GUEST_UPDATE = 'guest.update';

    const GUEST_DELETE = 'guest.delete';

    const GUEST_IMPORT = 'guest.import';

    const GUEST_EXPORT = 'guest.export';

    // Check-in permissions
    const CHECKIN_VIEW = 'checkin.view';

    const CHECKIN_SCAN = 'checkin.scan';

    const CHECKIN_CREATE = 'checkin.create';

    const CHECKIN_CORRECT = 'checkin.correct';

    const CHECKIN_REPORT = 'checkin.report';

    // Seating permissions
    const SEATING_VIEW = 'seating.view';

    const SEATING_MANAGE = 'seating.manage';

    // Media permissions
    const MEDIA_VIEW = 'media.view';

    const MEDIA_MODERATE = 'media.moderate';

    const MEDIA_MANAGE_ALBUMS = 'media.manage_albums';

    const MEDIA_PUBLISH = 'media.publish';

    // Gift permissions
    const GIFT_VIEW = 'gift.view';

    const GIFT_MANAGE = 'gift.manage';

    // Report permissions
    const REPORTS_VIEW = 'reports.view';

    const REPORTS_EXPORT = 'reports.export';

    /**
     * Returns all permissions granted to a given EventStaffRole.
     * Used for permission checks in event-scoped policies.
     *
     * @return string[]
     */
    public static function forEventStaffRole(EventStaffRole $role): array
    {
        return match ($role) {
            EventStaffRole::EventManager => [
                self::EVENT_VIEW, self::EVENT_UPDATE, self::EVENT_MANAGE_SETTINGS,
                self::EVENT_SETUP_MANAGE, self::EVENT_TEAM_MANAGE, self::EVENT_SCHEDULE_MANAGE,
                self::EVENT_RUN_OF_SHOW_VIEW, self::EVENT_RUN_OF_SHOW_MANAGE,
                self::OPERATIONS_VIEW, self::OPERATIONS_MANAGE, self::OPERATIONS_CLOSE,
                self::STAFF_VIEW, self::STAFF_INVITE, self::STAFF_MANAGE, self::STAFF_PERMISSIONS_MANAGE,
                self::STATION_VIEW, self::STATION_MANAGE, self::STATION_ASSIGN,
                self::TASK_VIEW, self::TASK_CREATE, self::TASK_ASSIGN, self::TASK_MANAGE,
                self::INCIDENT_VIEW, self::INCIDENT_CREATE, self::INCIDENT_MANAGE, self::INCIDENT_RESOLVE,
                self::ANNOUNCEMENT_VIEW, self::ANNOUNCEMENT_MANAGE, self::ANNOUNCEMENT_PUBLISH,
                self::GUEST_VIEW, self::GUEST_CREATE, self::GUEST_UPDATE, self::GUEST_DELETE,
                self::GUEST_IMPORT, self::GUEST_EXPORT,
                self::CHECKIN_VIEW, self::CHECKIN_SCAN, self::CHECKIN_CREATE,
                self::CHECKIN_CORRECT, self::CHECKIN_REPORT,
                self::SEATING_VIEW, self::SEATING_MANAGE,
                self::MEDIA_VIEW, self::MEDIA_MODERATE, self::MEDIA_MANAGE_ALBUMS, self::MEDIA_PUBLISH,
                self::GIFT_VIEW, self::GIFT_MANAGE,
                self::REPORTS_VIEW, self::REPORTS_EXPORT,
                self::VENDOR_VIEW, self::VENDOR_CREATE, self::VENDOR_UPDATE, self::VENDOR_ARCHIVE,
                self::VENDOR_CONTACT_MANAGE, self::VENDOR_CATALOG_MANAGE,
                self::VENDOR_REQUIREMENT_MANAGE, self::VENDOR_PROPOSAL_VIEW, self::VENDOR_PROPOSAL_MANAGE,
                self::VENDOR_SELECT, self::VENDOR_ENGAGEMENT_VIEW, self::VENDOR_ENGAGEMENT_MANAGE,
                self::VENDOR_AGREEMENT_VIEW, self::VENDOR_AGREEMENT_MANAGE,
                self::VENDOR_COMMERCIAL_VIEW, self::VENDOR_COMMERCIAL_APPROVE,
                self::VENDOR_DELIVERABLE_VIEW, self::VENDOR_DELIVERABLE_REVIEW,
                self::VENDOR_LOGISTICS_MANAGE, self::VENDOR_PORTAL_MANAGE,
                self::VENDOR_CHANGE_APPROVE, self::VENDOR_PERFORMANCE_VIEW, self::VENDOR_PERFORMANCE_REVIEW,
                self::VENDOR_REPORT_VIEW, self::VENDOR_REPORT_EXPORT, self::VENDOR_ENGAGEMENT_CLOSE,
            ],
            EventStaffRole::OperationsCoordinator => [
                self::EVENT_VIEW, self::EVENT_RUN_OF_SHOW_VIEW, self::EVENT_RUN_OF_SHOW_MANAGE,
                self::OPERATIONS_VIEW, self::OPERATIONS_MANAGE,
                self::STAFF_VIEW, self::STAFF_INVITE, self::STAFF_MANAGE,
                self::STATION_VIEW, self::STATION_MANAGE, self::STATION_ASSIGN,
                self::TASK_VIEW, self::TASK_CREATE, self::TASK_ASSIGN, self::TASK_MANAGE,
                self::INCIDENT_VIEW, self::INCIDENT_CREATE, self::INCIDENT_MANAGE, self::INCIDENT_RESOLVE,
                self::ANNOUNCEMENT_VIEW, self::ANNOUNCEMENT_MANAGE, self::ANNOUNCEMENT_PUBLISH,
                self::CHECKIN_VIEW,
                self::REPORTS_VIEW,
                self::VENDOR_VIEW, self::VENDOR_REQUIREMENT_MANAGE,
                self::VENDOR_PROPOSAL_VIEW, self::VENDOR_ENGAGEMENT_VIEW, self::VENDOR_ENGAGEMENT_MANAGE,
                self::VENDOR_AGREEMENT_VIEW, self::VENDOR_DELIVERABLE_VIEW, self::VENDOR_DELIVERABLE_REVIEW,
                self::VENDOR_LOGISTICS_MANAGE, self::VENDOR_PORTAL_MANAGE,
                self::VENDOR_CHANGE_APPROVE, self::VENDOR_PERFORMANCE_VIEW,
                self::VENDOR_REPORT_VIEW,
            ],
            EventStaffRole::StationSupervisor => [
                self::EVENT_VIEW, self::EVENT_RUN_OF_SHOW_VIEW,
                self::OPERATIONS_VIEW,
                self::STAFF_VIEW,
                self::STATION_VIEW, self::STATION_ASSIGN,
                self::TASK_VIEW, self::TASK_CREATE, self::TASK_ASSIGN, self::TASK_MANAGE,
                self::INCIDENT_VIEW, self::INCIDENT_CREATE, self::INCIDENT_MANAGE,
                self::ANNOUNCEMENT_VIEW,
                self::REPORTS_VIEW,
                self::VENDOR_VIEW, self::VENDOR_DELIVERABLE_VIEW, self::VENDOR_LOGISTICS_MANAGE,
            ],
            EventStaffRole::GuestManager => [
                self::EVENT_VIEW,
                self::EVENT_RUN_OF_SHOW_VIEW,
                self::OPERATIONS_VIEW,
                self::STAFF_VIEW,
                self::TASK_VIEW, self::TASK_CREATE,
                self::INCIDENT_VIEW, self::INCIDENT_CREATE,
                self::ANNOUNCEMENT_VIEW,
                self::GUEST_VIEW, self::GUEST_CREATE, self::GUEST_UPDATE,
                self::GUEST_IMPORT, self::GUEST_EXPORT,
                self::CHECKIN_VIEW,
                self::REPORTS_VIEW,
                self::VENDOR_VIEW,
            ],
            EventStaffRole::GuestRelations => [
                self::EVENT_VIEW,
                self::EVENT_RUN_OF_SHOW_VIEW,
                self::OPERATIONS_VIEW,
                self::STAFF_VIEW,
                self::TASK_VIEW, self::TASK_CREATE,
                self::INCIDENT_VIEW, self::INCIDENT_CREATE,
                self::ANNOUNCEMENT_VIEW,
                self::GUEST_VIEW,
                self::CHECKIN_VIEW,
                self::VENDOR_VIEW,
            ],
            EventStaffRole::CheckinSupervisor => [
                self::EVENT_VIEW,
                self::EVENT_RUN_OF_SHOW_VIEW,
                self::OPERATIONS_VIEW,
                self::STAFF_VIEW,
                self::STATION_VIEW,
                self::TASK_VIEW,
                self::INCIDENT_VIEW, self::INCIDENT_CREATE,
                self::ANNOUNCEMENT_VIEW,
                self::GUEST_VIEW,
                self::CHECKIN_VIEW, self::CHECKIN_SCAN, self::CHECKIN_CREATE,
                self::CHECKIN_CORRECT, self::CHECKIN_REPORT,
                self::SEATING_VIEW,
                self::REPORTS_VIEW,
                self::VENDOR_VIEW, self::VENDOR_DELIVERABLE_VIEW,
            ],
            EventStaffRole::CheckinStaff => [
                self::EVENT_VIEW,
                self::EVENT_RUN_OF_SHOW_VIEW,
                self::OPERATIONS_VIEW,
                self::STATION_VIEW,
                self::TASK_VIEW,
                self::INCIDENT_VIEW, self::INCIDENT_CREATE,
                self::ANNOUNCEMENT_VIEW,
                self::CHECKIN_VIEW, self::CHECKIN_SCAN, self::CHECKIN_CREATE,
                self::SEATING_VIEW,
                self::VENDOR_VIEW,
            ],
            EventStaffRole::SeatingUsher => [
                self::EVENT_VIEW,
                self::EVENT_RUN_OF_SHOW_VIEW,
                self::OPERATIONS_VIEW,
                self::STATION_VIEW,
                self::TASK_VIEW,
                self::INCIDENT_VIEW, self::INCIDENT_CREATE,
                self::ANNOUNCEMENT_VIEW,
                self::SEATING_VIEW, self::SEATING_MANAGE,
                self::CHECKIN_VIEW,
                self::VENDOR_VIEW,
            ],
            EventStaffRole::MediaCurator => [
                self::EVENT_VIEW,
                self::EVENT_RUN_OF_SHOW_VIEW,
                self::OPERATIONS_VIEW,
                self::TASK_VIEW,
                self::INCIDENT_VIEW, self::INCIDENT_CREATE,
                self::ANNOUNCEMENT_VIEW,
                self::MEDIA_VIEW, self::MEDIA_MODERATE, self::MEDIA_MANAGE_ALBUMS, self::MEDIA_PUBLISH,
                self::VENDOR_VIEW,
            ],
            EventStaffRole::GiftSteward => [
                self::EVENT_VIEW,
                self::EVENT_RUN_OF_SHOW_VIEW,
                self::OPERATIONS_VIEW,
                self::TASK_VIEW,
                self::INCIDENT_VIEW, self::INCIDENT_CREATE,
                self::ANNOUNCEMENT_VIEW,
                self::GIFT_VIEW, self::GIFT_MANAGE,
                self::VENDOR_VIEW,
            ],
            EventStaffRole::GeneralStaff => [
                self::EVENT_VIEW,
                self::EVENT_RUN_OF_SHOW_VIEW,
                self::OPERATIONS_VIEW,
                self::STATION_VIEW,
                self::TASK_VIEW,
                self::INCIDENT_VIEW, self::INCIDENT_CREATE,
                self::ANNOUNCEMENT_VIEW,
                self::VENDOR_VIEW, self::VENDOR_DELIVERABLE_VIEW,
            ],
            EventStaffRole::Observer => [
                self::EVENT_VIEW,
                self::EVENT_RUN_OF_SHOW_VIEW,
                self::OPERATIONS_VIEW,
                self::STAFF_VIEW,
                self::STATION_VIEW,
                self::TASK_VIEW,
                self::INCIDENT_VIEW,
                self::ANNOUNCEMENT_VIEW,
                self::REPORTS_VIEW,
                self::VENDOR_VIEW, self::VENDOR_REPORT_VIEW,
            ],
        };
    }

    /**
     * Permissions that an event manager may delegate to staff beyond their profile.
     *
     * @return string[]
     */
    public static function delegatableEventPermissions(): array
    {
        return [
            self::OPERATIONS_VIEW, self::OPERATIONS_MANAGE,
            self::STAFF_VIEW, self::STAFF_INVITE, self::STAFF_MANAGE,
            self::STATION_VIEW, self::STATION_MANAGE, self::STATION_ASSIGN,
            self::TASK_VIEW, self::TASK_CREATE, self::TASK_ASSIGN, self::TASK_MANAGE,
            self::INCIDENT_VIEW, self::INCIDENT_CREATE, self::INCIDENT_MANAGE, self::INCIDENT_RESOLVE,
            self::ANNOUNCEMENT_VIEW, self::ANNOUNCEMENT_MANAGE, self::ANNOUNCEMENT_PUBLISH,
            self::EVENT_RUN_OF_SHOW_VIEW, self::EVENT_RUN_OF_SHOW_MANAGE,
            self::REPORTS_VIEW,
            self::VENDOR_VIEW, self::VENDOR_REQUIREMENT_MANAGE,
            self::VENDOR_PROPOSAL_VIEW, self::VENDOR_ENGAGEMENT_VIEW, self::VENDOR_ENGAGEMENT_MANAGE,
            self::VENDOR_AGREEMENT_VIEW, self::VENDOR_COMMERCIAL_VIEW,
            self::VENDOR_DELIVERABLE_VIEW, self::VENDOR_DELIVERABLE_REVIEW,
            self::VENDOR_LOGISTICS_MANAGE, self::VENDOR_PORTAL_MANAGE,
            self::VENDOR_CHANGE_APPROVE, self::VENDOR_PERFORMANCE_VIEW, self::VENDOR_PERFORMANCE_REVIEW,
            self::VENDOR_REPORT_VIEW,
        ];
    }

    /**
     * Returns all permissions for a Workspace Owner (org scope).
     *
     * @return string[]
     */
    public static function forWorkspaceOwner(): array
    {
        return [
            self::ORG_VIEW, self::ORG_UPDATE, self::ORG_MANAGE_MEMBERS,
            self::ORG_INVITE_MEMBERS, self::ORG_MANAGE_ROLES, self::ORG_MANAGE_EVENTS,
            self::EVENT_VIEW, self::EVENT_UPDATE, self::EVENT_DELETE, self::EVENT_MANAGE_SETTINGS,
            self::EVENT_SETUP_MANAGE, self::EVENT_TEAM_MANAGE, self::EVENT_SCHEDULE_MANAGE,
            self::EVENT_RUN_OF_SHOW_VIEW, self::EVENT_RUN_OF_SHOW_MANAGE, self::EVENT_ARCHIVE,
            self::OPERATIONS_VIEW, self::OPERATIONS_MANAGE, self::OPERATIONS_CLOSE,
            self::STAFF_VIEW, self::STAFF_INVITE, self::STAFF_MANAGE, self::STAFF_PERMISSIONS_MANAGE,
            self::STATION_VIEW, self::STATION_MANAGE, self::STATION_ASSIGN,
            self::TASK_VIEW, self::TASK_CREATE, self::TASK_ASSIGN, self::TASK_MANAGE,
            self::INCIDENT_VIEW, self::INCIDENT_CREATE, self::INCIDENT_MANAGE, self::INCIDENT_RESOLVE,
            self::ANNOUNCEMENT_VIEW, self::ANNOUNCEMENT_MANAGE, self::ANNOUNCEMENT_PUBLISH,
            self::PLAYBOOK_VIEW, self::PLAYBOOK_MANAGE,
            self::GUEST_VIEW, self::GUEST_CREATE, self::GUEST_UPDATE, self::GUEST_DELETE,
            self::GUEST_IMPORT, self::GUEST_EXPORT,
            self::CHECKIN_VIEW, self::CHECKIN_SCAN, self::CHECKIN_CREATE,
            self::CHECKIN_CORRECT, self::CHECKIN_REPORT,
            self::SEATING_VIEW, self::SEATING_MANAGE,
            self::MEDIA_VIEW, self::MEDIA_MODERATE, self::MEDIA_MANAGE_ALBUMS, self::MEDIA_PUBLISH,
            self::GIFT_VIEW, self::GIFT_MANAGE,
            self::REPORTS_VIEW, self::REPORTS_EXPORT,
            self::VENDOR_VIEW, self::VENDOR_CREATE, self::VENDOR_UPDATE, self::VENDOR_ARCHIVE,
            self::VENDOR_CONTACT_MANAGE, self::VENDOR_CATALOG_MANAGE,
            self::VENDOR_REQUIREMENT_MANAGE, self::VENDOR_PROPOSAL_VIEW, self::VENDOR_PROPOSAL_MANAGE,
            self::VENDOR_SELECT, self::VENDOR_ENGAGEMENT_VIEW, self::VENDOR_ENGAGEMENT_MANAGE,
            self::VENDOR_AGREEMENT_VIEW, self::VENDOR_AGREEMENT_MANAGE,
            self::VENDOR_COMMERCIAL_VIEW, self::VENDOR_COMMERCIAL_APPROVE,
            self::VENDOR_DELIVERABLE_VIEW, self::VENDOR_DELIVERABLE_REVIEW,
            self::VENDOR_LOGISTICS_MANAGE, self::VENDOR_PORTAL_MANAGE,
            self::VENDOR_CHANGE_APPROVE, self::VENDOR_PERFORMANCE_VIEW, self::VENDOR_PERFORMANCE_REVIEW,
            self::VENDOR_REPORT_VIEW, self::VENDOR_REPORT_EXPORT, self::VENDOR_ENGAGEMENT_CLOSE,
        ];
    }

    /**
     * Returns all permissions for a Workspace Admin (org scope).
     * Same as Owner minus the ability to manage roles.
     *
     * @return string[]
     */
    public static function forWorkspaceAdmin(): array
    {
        return array_values(array_diff(self::forWorkspaceOwner(), [
            self::ORG_MANAGE_ROLES,
        ]));
    }
}
