<?php

namespace App\Services;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class WorkspaceRegistrationService
{
    /**
     * Create a new user + organization atomically.
     * Returns the created User (already authenticated after this call).
     */
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => strtolower(trim($data['email'])),
                'password' => Hash::make($data['password']),
                'role' => UserRole::Owner,
                'is_active' => true,
            ]);

            $slug = $this->uniqueSlug($data['workspace_name']);

            $org = Organization::create([
                'name' => $data['workspace_name'],
                'slug' => $slug,
                'status' => 'active',
            ]);

            OrganizationMembership::create([
                'organization_id' => $org->id,
                'user_id' => $user->id,
                'role' => OrganizationRole::Owner->value,
                'status' => OrganizationMembershipStatus::Active->value,
                'accepted_at' => now(),
            ]);

            return $user;
        });
    }

    /**
     * Create a new organization for an existing authenticated user,
     * making them Workspace Owner.
     */
    public function createWorkspaceForExistingUser(User $user, array $data): Organization
    {
        return DB::transaction(function () use ($user, $data): Organization {
            $slug = $this->uniqueSlug($data['workspace_name']);

            $org = Organization::create([
                'name' => $data['workspace_name'],
                'slug' => $slug,
                'status' => 'active',
            ]);

            OrganizationMembership::create([
                'organization_id' => $org->id,
                'user_id' => $user->id,
                'role' => OrganizationRole::Owner->value,
                'status' => OrganizationMembershipStatus::Active->value,
                'accepted_at' => now(),
            ]);

            return $org;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $i = 2;

        while (Organization::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
