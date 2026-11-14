<?php

namespace App\Services;

use App\Authorization\PermissionRegistry;
use App\Enums\VenueSpaceType;
use App\Enums\VenueStatus;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueSpace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Organization-scoped venue catalog, reached through an event context.
 *
 * Every lookup is constrained to the event's organization, so a manipulated id
 * can never reach another tenant's venue or space.
 */
class VenueService
{
    public function __construct(
        protected AuditService $audit,
        protected PermissionService $permissions,
    ) {}

    /**
     * @return Collection<int, Venue>
     */
    public function listForEvent(Event $event): Collection
    {
        return Venue::query()
            ->where('organization_id', $event->organization_id)
            ->with(['spaces' => fn ($query) => $query->where('status', VenueStatus::Active->value)])
            ->orderBy('name')
            ->get();
    }

    public function create(Event $event, array $data, User $actor): Venue
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::VENUE_MANAGE, 'Anda tidak berwenang mengelola venue.');

        $name = $this->requiredName($data['name'] ?? null, 'Nama venue wajib diisi.');

        $this->assertUniqueVenueName($event, $name);

        return DB::transaction(function () use ($event, $data, $actor, $name): Venue {
            $venue = Venue::create([
                'organization_id' => $event->organization_id,
                'name' => $name,
                'description' => $this->plainText($data['description'] ?? null),
                'address' => $this->plainText($data['address'] ?? null),
                'google_maps_url' => $this->mapsUrl($data['google_maps_url'] ?? null),
                'latitude' => $this->coordinate($data['latitude'] ?? null, 90),
                'longitude' => $this->coordinate($data['longitude'] ?? null, 180),
                'timezone' => $data['timezone'] ?? null,
                'status' => VenueStatus::Active,
                'created_by' => $actor->id,
            ]);

            $this->audit->record(
                'venue.created',
                "Venue {$venue->name} dibuat.",
                $event,
                $actor,
                $venue,
                ['organization_id' => $event->organization_id],
            );

            return $venue->fresh();
        });
    }

    public function update(Event $event, Venue $venue, array $data, User $actor): Venue
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::VENUE_MANAGE, 'Anda tidak berwenang mengelola venue.');
        $this->assertVenueBelongsToEvent($event, $venue);

        if ($venue->status === VenueStatus::Archived) {
            throw ValidationException::withMessages(['venue' => 'Venue yang diarsipkan tidak dapat diubah.']);
        }

        $payload = [];

        if (array_key_exists('name', $data)) {
            $name = $this->requiredName($data['name'], 'Nama venue wajib diisi.');
            $this->assertUniqueVenueName($event, $name, $venue->id);
            $payload['name'] = $name;
        }

        if (array_key_exists('description', $data)) {
            $payload['description'] = $this->plainText($data['description']);
        }

        if (array_key_exists('address', $data)) {
            $payload['address'] = $this->plainText($data['address']);
        }

        if (array_key_exists('google_maps_url', $data)) {
            $payload['google_maps_url'] = $this->mapsUrl($data['google_maps_url']);
        }

        if (array_key_exists('latitude', $data)) {
            $payload['latitude'] = $this->coordinate($data['latitude'], 90);
        }

        if (array_key_exists('longitude', $data)) {
            $payload['longitude'] = $this->coordinate($data['longitude'], 180);
        }

        if (array_key_exists('timezone', $data)) {
            $payload['timezone'] = $data['timezone'];
        }

        return DB::transaction(function () use ($event, $venue, $payload, $actor): Venue {
            $venue->fill($payload)->save();

            $this->audit->record(
                'venue.updated',
                "Venue {$venue->name} diperbarui.",
                $event,
                $actor,
                $venue,
                ['changed' => array_keys($payload)],
            );

            return $venue->fresh();
        });
    }

    public function archive(Event $event, Venue $venue, User $actor): Venue
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::VENUE_MANAGE, 'Anda tidak berwenang mengelola venue.');
        $this->assertVenueBelongsToEvent($event, $venue);

        if ($venue->floorPlans()->exists()) {
            throw ValidationException::withMessages([
                'venue' => 'Venue masih dipakai oleh floor plan dan tidak dapat diarsipkan.',
            ]);
        }

        return DB::transaction(function () use ($event, $venue, $actor): Venue {
            $venue->forceFill([
                'status' => VenueStatus::Archived,
                'archived_at' => now(),
            ])->save();

            $this->audit->record('venue.archived', "Venue {$venue->name} diarsipkan.", $event, $actor, $venue);

            return $venue->fresh();
        });
    }

    public function createSpace(Event $event, Venue $venue, array $data, User $actor): VenueSpace
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::VENUE_MANAGE, 'Anda tidak berwenang mengelola venue.');
        $this->assertVenueBelongsToEvent($event, $venue);

        if ($venue->status === VenueStatus::Archived) {
            throw ValidationException::withMessages(['venue' => 'Venue yang diarsipkan tidak dapat ditambah ruang.']);
        }

        $name = $this->requiredName($data['name'] ?? null, 'Nama ruang wajib diisi.');
        $this->assertUniqueSpaceName($venue, $name);

        return DB::transaction(function () use ($event, $venue, $data, $actor, $name): VenueSpace {
            $space = VenueSpace::create([
                'organization_id' => $event->organization_id,
                'venue_id' => $venue->id,
                'name' => $name,
                'type' => $this->spaceType($data['type'] ?? null),
                'floor_label' => $this->plainText($data['floor_label'] ?? null),
                'description' => $this->plainText($data['description'] ?? null),
                'capacity' => $this->capacity($data['capacity'] ?? null),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'status' => VenueStatus::Active,
            ]);

            $this->audit->record(
                'venue.space.created',
                "Ruang {$space->name} ditambahkan pada venue {$venue->name}.",
                $event,
                $actor,
                $space,
                ['venue_id' => $venue->id],
            );

            return $space->fresh();
        });
    }

    public function updateSpace(Event $event, Venue $venue, VenueSpace $space, array $data, User $actor): VenueSpace
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::VENUE_MANAGE, 'Anda tidak berwenang mengelola venue.');
        $this->assertVenueBelongsToEvent($event, $venue);
        $this->assertSpaceBelongsToVenue($venue, $space);

        if ($space->status === VenueStatus::Archived) {
            throw ValidationException::withMessages(['space' => 'Ruang yang diarsipkan tidak dapat diubah.']);
        }

        $payload = [];

        if (array_key_exists('name', $data)) {
            $name = $this->requiredName($data['name'], 'Nama ruang wajib diisi.');
            $this->assertUniqueSpaceName($venue, $name, $space->id);
            $payload['name'] = $name;
        }

        if (array_key_exists('type', $data)) {
            $payload['type'] = $this->spaceType($data['type']);
        }

        if (array_key_exists('floor_label', $data)) {
            $payload['floor_label'] = $this->plainText($data['floor_label']);
        }

        if (array_key_exists('description', $data)) {
            $payload['description'] = $this->plainText($data['description']);
        }

        if (array_key_exists('capacity', $data)) {
            $payload['capacity'] = $this->capacity($data['capacity']);
        }

        if (array_key_exists('sort_order', $data)) {
            $payload['sort_order'] = (int) $data['sort_order'];
        }

        return DB::transaction(function () use ($event, $venue, $space, $payload, $actor): VenueSpace {
            $space->fill($payload)->save();

            $this->audit->record(
                'venue.space.updated',
                "Ruang {$space->name} pada venue {$venue->name} diperbarui.",
                $event,
                $actor,
                $space,
                ['changed' => array_keys($payload)],
            );

            return $space->fresh();
        });
    }

    public function archiveSpace(Event $event, Venue $venue, VenueSpace $space, User $actor): VenueSpace
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::VENUE_MANAGE, 'Anda tidak berwenang mengelola venue.');
        $this->assertVenueBelongsToEvent($event, $venue);
        $this->assertSpaceBelongsToVenue($venue, $space);

        if ($space->floorPlans()->exists()) {
            throw ValidationException::withMessages([
                'space' => 'Ruang masih dipakai oleh floor plan dan tidak dapat diarsipkan.',
            ]);
        }

        return DB::transaction(function () use ($event, $venue, $space, $actor): VenueSpace {
            $space->forceFill([
                'status' => VenueStatus::Archived,
                'archived_at' => now(),
            ])->save();

            $this->audit->record(
                'venue.space.archived',
                "Ruang {$space->name} pada venue {$venue->name} diarsipkan.",
                $event,
                $actor,
                $space,
            );

            return $space->fresh();
        });
    }

    public function assertVenueBelongsToEvent(Event $event, Venue $venue): void
    {
        abort_unless(
            (int) $venue->organization_id === (int) $event->organization_id,
            404,
        );
    }

    public function assertSpaceBelongsToVenue(Venue $venue, VenueSpace $space): void
    {
        abort_unless((int) $space->venue_id === (int) $venue->id, 404);
    }

    protected function assertActorHas(Event $event, User $actor, string $permission, string $message): void
    {
        abort_unless($this->permissions->hasEventPermission($actor, $event, $permission), 403, $message);
    }

    protected function requiredName(mixed $value, string $message): string
    {
        $name = trim((string) $value);

        if ($name === '' || mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['name' => $message]);
        }

        return $name;
    }

    protected function assertUniqueVenueName(Event $event, string $name, ?int $ignoreId = null): void
    {
        $exists = Venue::query()
            ->where('organization_id', $event->organization_id)
            ->where('name', $name)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['name' => 'Nama venue sudah dipakai pada organisasi ini.']);
        }
    }

    protected function assertUniqueSpaceName(Venue $venue, string $name, ?int $ignoreId = null): void
    {
        $exists = VenueSpace::query()
            ->where('venue_id', $venue->id)
            ->where('name', $name)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['name' => 'Nama ruang sudah dipakai pada venue ini.']);
        }
    }

    protected function spaceType(mixed $value): VenueSpaceType
    {
        $type = $value instanceof VenueSpaceType
            ? $value
            : VenueSpaceType::tryFrom((string) $value);

        return $type ?? VenueSpaceType::Other;
    }

    protected function capacity(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $capacity = (int) $value;

        if ($capacity < 0 || $capacity > 100000) {
            throw ValidationException::withMessages(['capacity' => 'Kapasitas ruang tidak valid.']);
        }

        return $capacity;
    }

    protected function coordinate(mixed $value, float $limit): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            throw ValidationException::withMessages(['coordinate' => 'Koordinat venue tidak valid.']);
        }

        $coordinate = (float) $value;

        if (abs($coordinate) > $limit) {
            throw ValidationException::withMessages(['coordinate' => 'Koordinat venue di luar rentang yang diizinkan.']);
        }

        return $coordinate;
    }

    protected function mapsUrl(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $url = trim((string) $value);

        if (! filter_var($url, FILTER_VALIDATE_URL) || ! str_starts_with($url, 'https://')) {
            throw ValidationException::withMessages(['google_maps_url' => 'URL peta tidak valid.']);
        }

        return mb_substr($url, 0, 2048);
    }

    protected function plainText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = trim(strip_tags((string) $value));

        return $clean === '' ? null : $clean;
    }
}
