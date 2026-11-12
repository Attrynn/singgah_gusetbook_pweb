<?php

namespace App\Services;

use App\Enums\VendorContactPurpose;
use App\Enums\VendorStatus;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorCategory;
use App\Models\VendorContact;
use App\Models\VendorService;
use App\Models\VendorServicePackage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Organization-scoped vendor directory and service catalog.
 *
 * All lookups in this service assume the caller has already resolved the
 * vendor/category from the owning organization. Engagements keep their own
 * package snapshots, so catalog edits never rewrite historical agreements.
 */
class VendorDirectoryService
{
    private const VENDOR_FIELDS = [
        'vendor_category_id',
        'name',
        'legal_name',
        'type',
        'description',
        'email',
        'phone',
        'website',
        'address',
        'internal_notes',
    ];

    private const CONTACT_FIELDS = [
        'name',
        'position',
        'email',
        'phone',
        'purpose',
        'is_primary',
        'is_active',
        'notes',
    ];

    private const CATEGORY_FIELDS = [
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    private const SERVICE_FIELDS = [
        'vendor_category_id',
        'name',
        'description',
        'scope',
        'capacity',
        'conditions',
        'is_active',
    ];

    private const PACKAGE_FIELDS = [
        'name',
        'description',
        'price_amount',
        'currency',
        'inclusions',
        'exclusions',
        'capacity',
        'conditions',
        'is_active',
    ];

    /**
     * @param  array{q?: string|null, category_id?: int|null, status?: string|null, type?: string|null, sort?: string|null, direction?: string|null}  $filters
     */
    public function paginate(Organization $org, array $filters): LengthAwarePaginator
    {
        $query = $org->vendors()
            ->with('category')
            ->withCount('engagements');

        $search = trim((string) ($filters['q'] ?? ''));

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $like = '%'.$search.'%';

                $builder->where('name', 'like', $like)
                    ->orWhere('legal_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        if (! empty($filters['category_id'])) {
            $query->where('vendor_category_id', $filters['category_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $sort = in_array($filters['sort'] ?? null, ['name', 'created_at', 'status'], true)
            ? $filters['sort']
            : 'name';

        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $query->orderBy($sort, $direction);

        if ($sort !== 'name') {
            $query->orderBy('name', 'asc');
        }

        return $query->paginate(20)->withQueryString();
    }

    public function create(Organization $org, array $data, User $actor): Vendor
    {
        return DB::transaction(function () use ($org, $data, $actor): Vendor {
            return Vendor::create(array_merge(
                $this->only($data, self::VENDOR_FIELDS),
                [
                    'organization_id' => $org->id,
                    'status' => $data['status'] ?? VendorStatus::PendingVerification->value,
                    'created_by' => $actor->id,
                ],
            ));
        });
    }

    public function update(Vendor $vendor, array $data, User $actor): Vendor
    {
        return DB::transaction(function () use ($vendor, $data): Vendor {
            $vendor->update($this->only($data, self::VENDOR_FIELDS));

            return $vendor->fresh();
        });
    }

    public function changeStatus(Vendor $vendor, VendorStatus $target, User $actor): Vendor
    {
        $current = $vendor->status;

        if (! $current->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => "Status vendor tidak dapat diubah dari {$current->label()} ke {$target->label()}.",
            ]);
        }

        return DB::transaction(function () use ($vendor, $current, $target): Vendor {
            $attributes = ['status' => $target];

            if ($target === VendorStatus::Archived) {
                $attributes['archived_at'] = now();
            } elseif ($current === VendorStatus::Archived) {
                $attributes['archived_at'] = null;
            }

            $vendor->update($attributes);

            return $vendor->fresh();
        });
    }

    /**
     * Archiving is always allowed, even with active engagements. Historical
     * engagement records are preserved and continue to reference the vendor.
     */
    public function archive(Vendor $vendor, User $actor): Vendor
    {
        return DB::transaction(function () use ($vendor): Vendor {
            $vendor->update([
                'status' => VendorStatus::Archived,
                'archived_at' => now(),
            ]);

            return $vendor->fresh();
        });
    }

    public function restore(Vendor $vendor, User $actor): Vendor
    {
        return DB::transaction(function () use ($vendor): Vendor {
            $vendor->update([
                'status' => VendorStatus::Active,
                'archived_at' => null,
            ]);

            return $vendor->fresh();
        });
    }

    public function createContact(Vendor $vendor, array $data, User $actor): VendorContact
    {
        return DB::transaction(function () use ($vendor, $data): VendorContact {
            return $vendor->contacts()->create(array_merge(
                $this->only($data, self::CONTACT_FIELDS),
                [
                    'purpose' => $data['purpose'] ?? VendorContactPurpose::Business->value,
                    'is_active' => $data['is_active'] ?? true,
                ],
            ));
        });
    }

    public function updateContact(Vendor $vendor, VendorContact $contact, array $data, User $actor): VendorContact
    {
        return DB::transaction(function () use ($contact, $data): VendorContact {
            $contact->update($this->only($data, self::CONTACT_FIELDS));

            return $contact->fresh();
        });
    }

    /**
     * Contacts referenced by engagements, deliverables or change requests are
     * deactivated instead of deleted so historical records keep their link.
     */
    public function destroyContact(Vendor $vendor, VendorContact $contact, User $actor): void
    {
        $referenced = DB::table('event_vendor_engagements')
            ->where('vendor_contact_id', $contact->id)
            ->exists()
            || DB::table('event_vendor_deliverables')
                ->where('vendor_contact_id', $contact->id)
                ->exists()
            || DB::table('event_vendor_change_requests')
                ->where('requested_by_contact_id', $contact->id)
                ->exists();

        DB::transaction(function () use ($contact, $referenced): void {
            if ($referenced) {
                $contact->update(['is_active' => false]);

                return;
            }

            $contact->delete();
        });
    }

    public function listCategories(Organization $org): Collection
    {
        return $org->vendorCategories()
            ->withCount('vendors')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function createCategory(Organization $org, array $data, User $actor): VendorCategory
    {
        return DB::transaction(function () use ($org, $data): VendorCategory {
            $name = (string) $data['name'];

            return $org->vendorCategories()->create(array_merge(
                $this->only($data, self::CATEGORY_FIELDS),
                [
                    'name' => $name,
                    'slug' => $this->uniqueCategorySlug($org, $name),
                    'is_active' => $data['is_active'] ?? true,
                ],
            ));
        });
    }

    public function updateCategory(Organization $org, VendorCategory $category, array $data, User $actor): VendorCategory
    {
        return DB::transaction(function () use ($category, $data): VendorCategory {
            $category->update($this->only($data, self::CATEGORY_FIELDS));

            return $category->fresh();
        });
    }

    public function destroyCategory(Organization $org, VendorCategory $category, User $actor): void
    {
        if ($category->vendors()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Kategori masih dipakai vendor.',
            ]);
        }

        DB::transaction(function () use ($category): void {
            $category->delete();
        });
    }

    public function createService(Vendor $vendor, array $data, User $actor): VendorService
    {
        return DB::transaction(function () use ($vendor, $data, $actor): VendorService {
            return $vendor->services()->create(array_merge(
                $this->only($data, self::SERVICE_FIELDS),
                [
                    'is_active' => $data['is_active'] ?? true,
                    'version' => 1,
                    'created_by' => $actor->id,
                ],
            ));
        });
    }

    public function updateService(Vendor $vendor, VendorService $service, array $data, User $actor): VendorService
    {
        return DB::transaction(function () use ($service, $data): VendorService {
            $service->update($this->only($data, self::SERVICE_FIELDS));

            return $service->fresh();
        });
    }

    /**
     * Services referenced by an engagement are deactivated rather than
     * deleted so the engagement keeps a resolvable catalog link.
     */
    public function destroyService(Vendor $vendor, VendorService $service, User $actor): void
    {
        $referenced = DB::table('event_vendor_engagements')
            ->where('vendor_service_id', $service->id)
            ->exists();

        DB::transaction(function () use ($service, $referenced): void {
            if ($referenced) {
                $service->update(['is_active' => false]);

                return;
            }

            $service->delete();
        });
    }

    public function createPackage(Vendor $vendor, VendorService $service, array $data, User $actor): VendorServicePackage
    {
        return DB::transaction(function () use ($service, $data): VendorServicePackage {
            return $service->packages()->create(array_merge(
                $this->only($data, self::PACKAGE_FIELDS),
                [
                    'currency' => $data['currency'] ?? 'IDR',
                    'is_active' => $data['is_active'] ?? true,
                    'version' => 1,
                ],
            ));
        });
    }

    /**
     * Every catalog edit bumps the package version. Existing engagements keep
     * their immutable package_snapshot, so historical pricing is preserved.
     */
    public function updatePackage(Vendor $vendor, VendorService $service, VendorServicePackage $package, array $data, User $actor): VendorServicePackage
    {
        return DB::transaction(function () use ($package, $data): VendorServicePackage {
            $package->update(array_merge(
                $this->only($data, self::PACKAGE_FIELDS),
                ['version' => ((int) $package->version) + 1],
            ));

            return $package->fresh();
        });
    }

    /**
     * Packages referenced by an engagement or proposal are deactivated so
     * historical selections keep their catalog reference.
     */
    public function destroyPackage(Vendor $vendor, VendorService $service, VendorServicePackage $package, User $actor): void
    {
        $referenced = DB::table('event_vendor_engagements')
            ->where('vendor_service_package_id', $package->id)
            ->exists()
            || DB::table('event_vendor_proposals')
                ->where('vendor_service_package_id', $package->id)
                ->exists();

        DB::transaction(function () use ($package, $referenced): void {
            if ($referenced) {
                $package->update(['is_active' => false]);

                return;
            }

            $package->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $allowed
     * @return array<string, mixed>
     */
    private function only(array $data, array $allowed): array
    {
        return array_intersect_key($data, array_flip($allowed));
    }

    private function uniqueCategorySlug(Organization $org, string $name): string
    {
        $base = Str::slug($name) ?: 'kategori';
        $slug = $base;
        $suffix = 2;

        while ($org->vendorCategories()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
