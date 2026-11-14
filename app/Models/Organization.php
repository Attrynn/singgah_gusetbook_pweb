<?php

namespace App\Models;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    // Milestone 8 — organization venue catalog.

    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class);
    }

    public function floorPlans(): HasMany
    {
        return $this->hasMany(FloorPlan::class);
    }

    // Milestone 7 — organization vendor directory.

    public function vendorCategories(): HasMany
    {
        return $this->hasMany(VendorCategory::class);
    }

    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class);
    }

    public function vendorPortalMemberships(): HasMany
    {
        return $this->hasMany(VendorPortalMembership::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'organization_memberships')
            ->using(OrganizationMembership::class)
            ->withTimestamps();
    }

    public function activeMembers(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class)
            ->where('status', OrganizationMembershipStatus::Active);
    }

    public function owners(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class)
            ->where('role', OrganizationRole::Owner)
            ->where('status', OrganizationMembershipStatus::Active);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasOwner(): bool
    {
        return $this->owners()->exists();
    }

    /**
     * Returns the OrganizationMembership for the given user, or null if none exists.
     */
    public function membershipFor(User|int $user): ?OrganizationMembership
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $this->memberships()
            ->where('user_id', $userId)
            ->first();
    }
}
