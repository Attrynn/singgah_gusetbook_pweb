<?php

namespace App\Services;

use App\Enums\PlaybookStatus;
use App\Models\Organization;
use App\Models\Playbook;
use App\Models\PlaybookVersion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Manages reusable event configuration playbooks.
 *
 * A playbook is either a platform template (organization_id === null) or an
 * organization-owned playbook. Both are composed of immutable versions so that
 * events can snapshot the exact configuration they were created from.
 */
class PlaybookService
{
    /**
     * Config keys every playbook version must expose. Missing keys default to [].
     *
     * @var array<int, string>
     */
    public const CONFIG_KEYS = [
        'phases',
        'checklist',
        'public_schedule',
        'run_of_show',
        'team_roles',
        'vendor_categories',
        'reception',
        'invitation_defaults',
        'guestbook_defaults',
    ];

    public function __construct(protected AuditService $audit) {}

    /**
     * @return Collection<int, Playbook>
     */
    public function platformTemplates(): Collection
    {
        return Playbook::query()
            ->platform()
            ->where('status', PlaybookStatus::Active)
            ->with('latestVersion')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Playbook>
     */
    public function forOrganization(Organization $org): Collection
    {
        return Playbook::query()
            ->forOrganization($org)
            ->where('status', PlaybookStatus::Active)
            ->with('latestVersion')
            ->orderBy('name')
            ->get();
    }

    public function create(?Organization $org, array $data, User $actor): Playbook
    {
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Nama playbook wajib diisi.']);
        }

        $config = $this->normalizeConfig($data['config'] ?? []);

        return DB::transaction(function () use ($org, $data, $actor, $name, $config): Playbook {
            $playbook = Playbook::create([
                'organization_id' => $org?->id,
                'name' => $name,
                'slug' => $this->uniqueSlug($data['slug'] ?? $name),
                'description' => $data['description'] ?? null,
                'event_type' => $data['event_type'] ?? null,
                'days' => (int) ($data['days'] ?? 1),
                'status' => $data['status'] ?? PlaybookStatus::Active,
                'created_by' => $actor->id,
            ]);

            $playbook->versions()->create([
                'version' => 1,
                'config' => $config,
                'notes' => $data['notes'] ?? null,
            ]);

            $scope = $playbook->isPlatform() ? 'platform' : 'organisasi';

            $this->audit->record(
                'playbook.created',
                "Playbook {$scope} dibuat: {$playbook->name}",
                null,
                $actor,
                $playbook,
                ['organization_id' => $org?->id],
            );

            return $playbook->fresh(['latestVersion']);
        });
    }

    public function update(Playbook $playbook, array $data, User $actor): Playbook
    {
        return DB::transaction(function () use ($playbook, $data, $actor): Playbook {
            $payload = [];

            foreach (['name', 'description', 'event_type', 'days', 'status'] as $key) {
                if (array_key_exists($key, $data)) {
                    $payload[$key] = $data[$key];
                }
            }

            if (! empty($playbook->slug) && array_key_exists('slug', $data) && $data['slug'] !== $playbook->slug) {
                $payload['slug'] = $this->uniqueSlug($data['slug'], $playbook->id);
            }

            if ($payload !== []) {
                $playbook->update($payload);
            }

            // A config change is a new immutable version, never a mutation.
            if (array_key_exists('config', $data)) {
                $this->createVersion($playbook, $data['config'] ?? [], $actor, $data['notes'] ?? null);
            }

            $this->audit->record(
                'playbook.updated',
                "Playbook diperbarui: {$playbook->name}",
                null,
                $actor,
                $playbook,
            );

            return $playbook->fresh(['latestVersion']);
        });
    }

    public function duplicate(Playbook $playbook, ?Organization $targetOrg, User $actor): Playbook
    {
        $latest = $this->latestVersion($playbook);
        $config = $latest?->config ?? [];

        return DB::transaction(function () use ($playbook, $targetOrg, $actor, $config): Playbook {
            $name = $playbook->name.' (Salinan)';
            $organizationId = $targetOrg?->id ?? $playbook->organization_id;

            $copy = Playbook::create([
                'organization_id' => $organizationId,
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
                'description' => $playbook->description,
                'event_type' => $playbook->event_type,
                'days' => $playbook->days,
                'status' => PlaybookStatus::Active,
                'created_by' => $actor->id,
            ]);

            $copy->versions()->create([
                'version' => 1,
                'config' => $this->normalizeConfig($config),
                'notes' => 'Salinan dari '.$playbook->name,
            ]);

            $this->audit->record(
                'playbook.duplicated',
                "Playbook diduplikasi: {$playbook->name} -> {$copy->name}",
                null,
                $actor,
                $copy,
                ['source_playbook_id' => $playbook->id],
            );

            return $copy->fresh(['latestVersion']);
        });
    }

    public function deactivate(Playbook $playbook, User $actor): void
    {
        $playbook->update(['status' => PlaybookStatus::Inactive]);

        $this->audit->record(
            'playbook.deactivated',
            "Playbook dinonaktifkan: {$playbook->name}",
            null,
            $actor,
            $playbook,
        );
    }

    public function latestVersion(Playbook $playbook): ?PlaybookVersion
    {
        return $playbook->latestVersion()->first();
    }

    public function createVersion(Playbook $playbook, array $config, User $actor, ?string $notes = null): PlaybookVersion
    {
        $nextVersion = ((int) $playbook->versions()->max('version')) + 1;

        $version = $playbook->versions()->create([
            'version' => $nextVersion,
            'config' => $this->normalizeConfig($config),
            'notes' => $notes,
        ]);

        $this->audit->record(
            'playbook.version_created',
            "Versi {$nextVersion} playbook dibuat: {$playbook->name}",
            null,
            $actor,
            $version,
        );

        return $version;
    }

    /**
     * Normalize a playbook config to the canonical key set. Unknown keys are
     * dropped and each known key is guaranteed to be an array.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, array<int|string, mixed>>
     */
    protected function normalizeConfig(array $config): array
    {
        $normalized = [];

        foreach (self::CONFIG_KEYS as $key) {
            $value = $config[$key] ?? [];

            if (! is_array($value)) {
                throw ValidationException::withMessages([
                    "config.{$key}" => "Bagian konfigurasi '{$key}' harus berupa array.",
                ]);
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    protected function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'playbook';
        $slug = $base;
        $suffix = 2;

        while (Playbook::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
