<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M10 — extends database-enforced tenant (event) integrity to the M5–M9
 * tables that carry an event_id alongside a reference to another
 * event-scoped parent row.
 *
 * A child row must reference a parent from the same event. Composite
 * (event_id, <parent_id>) foreign keys enforce this at the database level,
 * mirroring the Milestone 1 hardening in
 * 2026_11_06_000200_enforce_tenant_integrity.php.
 *
 * The migration is additive and reversible. Inconsistent rows (none are
 * expected — verified clean before authoring) are realigned to the
 * authoritative parent event, and any row that cannot be reconciled aborts
 * the migration instead of being deleted.
 */
return new class extends Migration
{
    /**
     * [child table, child foreign-key column, event-scoped parent table]
     *
     * @var array<int,array{0:string,1:string,2:string}>
     */
    protected array $pairs = [
        // Milestone 6 — event operations (stations, schedule, tasks, incidents).
        ['event_run_of_show_items', 'phase_id', 'event_phases'],
        ['event_run_of_show_items', 'event_session_id', 'event_sessions'],
        ['event_run_of_show_items', 'station_id', 'event_stations'],
        ['event_run_of_show_items', 'pic_staff_membership_id', 'event_staff_memberships'],
        ['event_run_of_show_items', 'depends_on_item_id', 'event_run_of_show_items'],
        ['event_run_of_show_assignees', 'staff_membership_id', 'event_staff_memberships'],
        ['event_run_of_show_assignees', 'run_of_show_item_id', 'event_run_of_show_items'],
        ['event_sessions', 'phase_id', 'event_phases'],
        ['event_tasks', 'station_id', 'event_stations'],
        ['event_tasks', 'run_of_show_item_id', 'event_run_of_show_items'],
        ['event_tasks', 'assigned_membership_id', 'event_staff_memberships'],
        ['event_incidents', 'station_id', 'event_stations'],
        ['event_station_assignments', 'station_id', 'event_stations'],
        ['event_station_assignments', 'staff_membership_id', 'event_staff_memberships'],
        ['event_staff_permission_grants', 'event_staff_membership_id', 'event_staff_memberships'],
        ['event_stations', 'supervisor_membership_id', 'event_staff_memberships'],
        ['event_setup_configs', 'primary_pic_staff_membership_id', 'event_staff_memberships'],
        ['event_activities', 'guest_id', 'guests'],

        // Milestone 5 — guest party, passes and individual attribution.
        ['guest_passes', 'guest_party_membership_id', 'guest_party_memberships'],
        ['guest_party_memberships', 'guest_id', 'guests'],
        ['memory_consents', 'guest_party_membership_id', 'guest_party_memberships'],
        ['checkins', 'guest_party_membership_id', 'guest_party_memberships'],
        ['checkins', 'guest_pass_id', 'guest_passes'],
        ['guestbook_messages', 'guest_party_membership_id', 'guest_party_memberships'],
        ['media_assets', 'guest_party_membership_id', 'guest_party_memberships'],

        // Milestone 7 — vendor & service provider management.
        ['event_vendor_requirements', 'owner_membership_id', 'event_staff_memberships'],
        ['event_vendor_engagements', 'coordinator_membership_id', 'event_staff_memberships'],
        ['event_vendor_engagements', 'event_vendor_requirement_id', 'event_vendor_requirements'],
        ['event_vendor_engagements', 'event_vendor_proposal_id', 'event_vendor_proposals'],
        ['event_vendor_deliverables', 'event_vendor_engagement_id', 'event_vendor_engagements'],
        ['event_vendor_deliverables', 'internal_task_id', 'event_tasks'],
        ['event_vendor_deliverables', 'reviewer_membership_id', 'event_staff_memberships'],
        ['event_vendor_documents', 'event_vendor_engagement_id', 'event_vendor_engagements'],
        ['event_vendor_documents', 'event_vendor_proposal_id', 'event_vendor_proposals'],
        ['event_vendor_documents', 'supersedes_id', 'event_vendor_documents'],
        ['event_vendor_communications', 'event_vendor_engagement_id', 'event_vendor_engagements'],
        ['event_vendor_communications', 'event_vendor_deliverable_id', 'event_vendor_deliverables'],
        ['event_vendor_communications', 'event_vendor_change_request_id', 'event_vendor_change_requests'],
        ['event_vendor_change_requests', 'event_vendor_engagement_id', 'event_vendor_engagements'],
        ['event_vendor_issues', 'event_vendor_engagement_id', 'event_vendor_engagements'],
        ['event_vendor_issues', 'event_incident_id', 'event_incidents'],
        ['event_vendor_logistics_requirements', 'event_vendor_engagement_id', 'event_vendor_engagements'],
        ['event_vendor_logistics_requirements', 'event_station_id', 'event_stations'],
        ['event_vendor_performance_reviews', 'event_vendor_engagement_id', 'event_vendor_engagements'],
        ['event_vendor_proposals', 'event_vendor_requirement_id', 'event_vendor_requirements'],
        ['event_vendor_schedule_references', 'event_vendor_engagement_id', 'event_vendor_engagements'],
        ['event_vendor_schedule_references', 'event_run_of_show_item_id', 'event_run_of_show_items'],
        ['event_vendor_schedule_references', 'event_station_id', 'event_stations'],

        // Milestone 9 — memory collections.
        ['memory_collections', 'cover_media_asset_id', 'media_assets'],
        ['memory_collection_items', 'memory_collection_id', 'memory_collections'],
    ];

    public function up(): void
    {
        $this->repairInconsistentRows();

        // Every referenced parent needs a (event_id, id) unique key the
        // composite foreign key can target.
        foreach ($this->parents() as $parent) {
            if ($this->hasUniqueOn($parent, ['event_id', 'id'])) {
                continue;
            }

            Schema::table($parent, function (Blueprint $table) use ($parent): void {
                $table->unique(['event_id', 'id'], 'uq_'.$parent.'_event_id_id');
            });
        }

        foreach ($this->pairs as [$child, $fk, $parent]) {
            if ($this->hasForeignKeyOn($child, ['event_id', $fk])) {
                continue;
            }

            $name = $this->fkName($child, $fk);

            Schema::table($child, function (Blueprint $table) use ($fk, $parent, $name): void {
                $table->foreign(['event_id', $fk], $name)
                    ->references(['event_id', 'id'])->on($parent);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->pairs as [$child, $fk]) {
            if (! $this->hasForeignKeyOn($child, ['event_id', $fk])) {
                continue;
            }

            $name = $this->fkName($child, $fk);

            Schema::table($child, function (Blueprint $table) use ($name): void {
                $table->dropForeign($name);
            });
        }

        foreach ($this->parents() as $parent) {
            if (! $this->hasUniqueNamed($parent, 'uq_'.$parent.'_event_id_id')) {
                continue;
            }

            Schema::table($parent, function (Blueprint $table) use ($parent): void {
                $table->dropUnique('uq_'.$parent.'_event_id_id');
            });
        }
    }

    /**
     * Distinct parent tables across all invariants.
     *
     * @return array<int,string>
     */
    protected function parents(): array
    {
        $parents = [];

        foreach ($this->pairs as [, , $parent]) {
            $parents[$parent] = true;
        }

        return array_keys($parents);
    }

    /**
     * Deterministic constraint name that stays within MySQL's 64-char limit.
     */
    protected function fkName(string $child, string $fk): string
    {
        $name = 'fk_'.$child.'_'.$fk;

        if (strlen($name) <= 64) {
            return $name;
        }

        return 'fk_'.substr($child, 0, 20).'_'.substr($fk, 0, 20).'_'.substr(md5($child.'|'.$fk), 0, 6);
    }

    /**
     * @param  array<int,string>  $columns
     */
    protected function hasUniqueOn(string $table, array $columns): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['unique'] ?? false) && $this->sameColumns($index['columns'] ?? [], $columns)) {
                return true;
            }
        }

        return false;
    }

    protected function hasUniqueNamed(string $table, string $name): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int,string>  $columns
     */
    protected function hasForeignKeyOn(string $table, array $columns): bool
    {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if ($this->sameColumns($foreignKey['columns'] ?? [], $columns)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int,string>  $a
     * @param  array<int,string>  $b
     */
    protected function sameColumns(array $a, array $b): bool
    {
        $a = array_map('strtolower', array_values($a));
        $b = array_map('strtolower', array_values($b));

        sort($a);
        sort($b);

        return $a === $b;
    }

    /**
     * Align child event_id with the authoritative parent row.
     */
    protected function repairInconsistentRows(): void
    {
        foreach ($this->pairs as [$child, $fk, $parent]) {
            $mismatch = DB::table($child.' as c')
                ->join($parent.' as p', 'p.id', '=', 'c.'.$fk)
                ->whereColumn('c.event_id', '!=', 'p.event_id')
                ->count();

            if ($mismatch === 0) {
                continue;
            }

            DB::table($child.' as c')
                ->join($parent.' as p', 'p.id', '=', 'c.'.$fk)
                ->whereColumn('c.event_id', '!=', 'p.event_id')
                ->update(['c.event_id' => DB::raw('p.event_id')]);

            $remaining = DB::table($child.' as c')
                ->join($parent.' as p', 'p.id', '=', 'c.'.$fk)
                ->whereColumn('c.event_id', '!=', 'p.event_id')
                ->count();

            if ($remaining > 0) {
                throw new RuntimeException(
                    "Cannot enforce tenant integrity for {$child}.{$fk}: {$remaining} inconsistent row(s) remain."
                );
            }
        }
    }
};
