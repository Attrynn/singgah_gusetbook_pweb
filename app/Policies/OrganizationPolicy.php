<?php

namespace App\Policies;

use App\Authorization\PermissionRegistry;
use App\Models\Organization;
use App\Models\User;
use App\Services\PermissionService;

class OrganizationPolicy
{
    public function __construct(private readonly PermissionService $permissions) {}

    public function view(User $user, Organization $org): bool
    {
        if ($user->isSuperadmin() || $user->isPlatformSuperadmin()) {
            return true;
        }

        return $this->permissions->hasOrgPermission($user, $org, PermissionRegistry::ORG_VIEW);
    }

    public function update(User $user, Organization $org): bool
    {
        if ($user->isSuperadmin() || $user->isPlatformSuperadmin()) {
            return true;
        }

        return $this->permissions->hasOrgPermission($user, $org, PermissionRegistry::ORG_UPDATE);
    }

    public function manageMembers(User $user, Organization $org): bool
    {
        if ($user->isSuperadmin() || $user->isPlatformSuperadmin()) {
            return true;
        }

        return $this->permissions->hasOrgPermission($user, $org, PermissionRegistry::ORG_MANAGE_MEMBERS);
    }

    public function inviteMembers(User $user, Organization $org): bool
    {
        if ($user->isSuperadmin() || $user->isPlatformSuperadmin()) {
            return true;
        }

        return $this->permissions->hasOrgPermission($user, $org, PermissionRegistry::ORG_INVITE_MEMBERS);
    }

    public function manageRoles(User $user, Organization $org): bool
    {
        if ($user->isSuperadmin() || $user->isPlatformSuperadmin()) {
            return true;
        }

        return $this->permissions->hasOrgPermission($user, $org, PermissionRegistry::ORG_MANAGE_ROLES);
    }

    public function managePlaybooks(User $user, Organization $org): bool
    {
        if ($user->isSuperadmin() || $user->isPlatformSuperadmin()) {
            return true;
        }

        return $this->permissions->hasOrgPermission($user, $org, PermissionRegistry::PLAYBOOK_MANAGE);
    }

    // ---------------------------------------------------------------------
    // Milestone 7 — organization vendor directory
    // ---------------------------------------------------------------------

    public function viewVendors(User $user, Organization $org): bool
    {
        return $this->allowsVendorPermission($user, $org, PermissionRegistry::VENDOR_VIEW);
    }

    public function createVendors(User $user, Organization $org): bool
    {
        return $this->allowsVendorPermission($user, $org, PermissionRegistry::VENDOR_CREATE);
    }

    public function updateVendors(User $user, Organization $org): bool
    {
        return $this->allowsVendorPermission($user, $org, PermissionRegistry::VENDOR_UPDATE);
    }

    public function archiveVendors(User $user, Organization $org): bool
    {
        return $this->allowsVendorPermission($user, $org, PermissionRegistry::VENDOR_ARCHIVE);
    }

    public function manageVendorContacts(User $user, Organization $org): bool
    {
        return $this->allowsVendorPermission($user, $org, PermissionRegistry::VENDOR_CONTACT_MANAGE);
    }

    public function manageVendorCatalog(User $user, Organization $org): bool
    {
        return $this->allowsVendorPermission($user, $org, PermissionRegistry::VENDOR_CATALOG_MANAGE);
    }

    protected function allowsVendorPermission(User $user, Organization $org, string $permission): bool
    {
        if ($user->isSuperadmin() || $user->isPlatformSuperadmin()) {
            return true;
        }

        return $this->permissions->hasOrgPermission($user, $org, $permission);
    }
}
