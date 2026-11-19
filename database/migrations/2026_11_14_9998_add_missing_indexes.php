<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M10 — indexes on foreign-key columns that previously had none.
 *
 * Index names are table-qualified (`{table}_{column}_index`) because SQLite
 * uses a single global index namespace per database, so identical names
 * across tables would collide on `migrate:fresh`. MySQL tolerates the same
 * names across tables, but the qualified form keeps both engines identical.
 *
 * Additive only: no columns or data are changed.
 */
return new class extends Migration
{
    /**
     * [table, column]
     *
     * @var array<int,array{0:string,1:string}>
     */
    protected array $indexes = [
        ['audit_logs', 'target_id'],
        ['guestbook_messages', 'client_submission_id'],
        ['media_assets', 'client_submission_id'],
        ['memory_collection_items', 'item_id'],
        ['memory_consents', 'contribution_id'],
        ['seating_assignments', 'floor_plan_id'],
        ['seating_assignments', 'table_object_id'],
        ['seating_assignments', 'seat_object_id'],
        ['seating_assignments', 'guest_id'],
        ['seating_assignments', 'guest_party_membership_id'],
        ['spatial_references', 'floor_plan_id'],
        ['spatial_references', 'object_id'],
        ['spatial_references', 'referenceable_id'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as [$table, $column]) {
            $name = $table.'_'.$column.'_index';

            if (Schema::hasIndex($table, $name)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($column, $name): void {
                $blueprint->index($column, $name);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as [$table, $column]) {
            $name = $table.'_'.$column.'_index';

            if (! Schema::hasIndex($table, $name)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($name): void {
                $blueprint->dropIndex($name);
            });
        }
    }
};
