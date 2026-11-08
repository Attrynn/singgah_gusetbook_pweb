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

    const PLAYBOOK_VIEW = 'playbook.view';

    const PLAYBOOK_MANAGE = 'playbook.manage';

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
                self::GUEST_VIEW, self::GUEST_CREATE, self::GUEST_UPDATE, self::GUEST_DELETE,
                self::GUEST_IMPORT, self::GUEST_EXPORT,
                self::CHECKIN_VIEW, self::CHECKIN_SCAN, self::CHECKIN_CREATE,
                self::CHECKIN_CORRECT, self::CHECKIN_REPORT,
                self::SEATING_VIEW, self::SEATING_MANAGE,
                self::MEDIA_VIEW, self::MEDIA_MODERATE, self::MEDIA_MANAGE_ALBUMS, self::MEDIA_PUBLISH,
                self::GIFT_VIEW, self::GIFT_MANAGE,
                self::REPORTS_VIEW, self::REPORTS_EXPORT,
            ],
            EventStaffRole::GuestManager => [
                self::EVENT_VIEW,
                self::EVENT_RUN_OF_SHOW_VIEW,
                self::GUEST_VIEW, self::GUEST_CREATE, self::GUEST_UPDATE,
                self::GUEST_IMPORT, self::GUEST_EXPORT,
                self::CHECKIN_VIEW,
                self::REPORTS_VIEW,
            ],
            EventStaffRole::CheckinSupervisor => [
                self::EVENT_VIEW,
                self::EVENT_RUN_OF_SHOW_VIEW,
                self::GUEST_VIEW,
                self::CHECKIN_VIEW, self::CHECKIN_SCAN, self::CHECKIN_CREATE,
                self::CHECKIN_CORRECT, self::CHECKIN_REPORT,
                self::SEATING_VIEW,
                self::REPORTS_VIEW,
            ],
            EventStaffRole::CheckinStaff => [
                self::EVENT_VIEW,
                self::EVENT_RUN_OF_SHOW_VIEW,
                self::CHECKIN_VIEW, self::CHECKIN_SCAN, self::CHECKIN_CREATE,
                self::SEATING_VIEW,
            ],
            EventStaffRole::SeatingUsher => [
                self::EVENT_VIEW,
                self::EVENT_RUN_OF_SHOW_VIEW,
                self::SEATING_VIEW, self::SEATING_MANAGE,
                self::CHECKIN_VIEW,
            ],
            EventStaffRole::MediaCurator => [
                self::EVENT_VIEW,
                self::EVENT_RUN_OF_SHOW_VIEW,
                self::MEDIA_VIEW, self::MEDIA_MODERATE, self::MEDIA_MANAGE_ALBUMS, self::MEDIA_PUBLISH,
            ],
            EventStaffRole::GiftSteward => [
                self::EVENT_VIEW,
                self::EVENT_RUN_OF_SHOW_VIEW,
                self::GIFT_VIEW, self::GIFT_MANAGE,
            ],
        };
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
            self::PLAYBOOK_VIEW, self::PLAYBOOK_MANAGE,
            self::GUEST_VIEW, self::GUEST_CREATE, self::GUEST_UPDATE, self::GUEST_DELETE,
            self::GUEST_IMPORT, self::GUEST_EXPORT,
            self::CHECKIN_VIEW, self::CHECKIN_SCAN, self::CHECKIN_CREATE,
            self::CHECKIN_CORRECT, self::CHECKIN_REPORT,
            self::SEATING_VIEW, self::SEATING_MANAGE,
            self::MEDIA_VIEW, self::MEDIA_MODERATE, self::MEDIA_MANAGE_ALBUMS, self::MEDIA_PUBLISH,
            self::GIFT_VIEW, self::GIFT_MANAGE,
            self::REPORTS_VIEW, self::REPORTS_EXPORT,
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
