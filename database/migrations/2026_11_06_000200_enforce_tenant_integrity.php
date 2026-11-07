<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enforces tenant (event) consistency at the database level.
 *
 * Child rows that carry an event_id must reference parents from the same
 * event. Before constraints are added, inconsistent rows are detected and
 * repaired from the authoritative parent row; unresolved rows abort the
 * migration instead of being deleted.
 */
return new class extends Migration
{
    /**
     * @var array<int,array{child:string,childKey:string,parent:string,parentKey:string,parentColumn:string,label:string}>
     */
    protected array $invariants = [
        ['child' => 'guests', 'childKey' => 'event_id', 'parent' => 'guest_categories', 'parentKey' => 'guest_category_id', 'parentColumn' => 'event_id', 'label' => 'guest category'],
        ['child' => 'guests', 'childKey' => 'event_id', 'parent' => 'guest_groups', 'parentKey' => 'guest_group_id', 'parentColumn' => 'event_id', 'label' => 'guest group'],
        ['child' => 'rsvps', 'childKey' => 'event_id', 'parent' => 'guests', 'parentKey' => 'guest_id', 'parentColumn' => 'event_id', 'label' => 'rsvp guest'],
        ['child' => 'checkins', 'childKey' => 'event_id', 'parent' => 'guests', 'parentKey' => 'guest_id', 'parentColumn' => 'event_id', 'label' => 'checkin guest'],
        ['child' => 'guest_tokens', 'childKey' => 'event_id', 'parent' => 'guests', 'parentKey' => 'guest_id', 'parentColumn' => 'event_id', 'label' => 'guest token guest'],
        ['child' => 'invitations', 'childKey' => 'event_id', 'parent' => 'guests', 'parentKey' => 'guest_id', 'parentColumn' => 'event_id', 'label' => 'invitation guest'],
        ['child' => 'guestbook_messages', 'childKey' => 'event_id', 'parent' => 'guests', 'parentKey' => 'guest_id', 'parentColumn' => 'event_id', 'label' => 'guestbook guest'],
        ['child' => 'song_requests', 'childKey' => 'event_id', 'parent' => 'guests', 'parentKey' => 'guest_id', 'parentColumn' => 'event_id', 'label' => 'song request guest'],
        ['child' => 'table_assignments', 'childKey' => 'event_id', 'parent' => 'guests', 'parentKey' => 'guest_id', 'parentColumn' => 'event_id', 'label' => 'assignment guest'],
        ['child' => 'table_assignments', 'childKey' => 'event_id', 'parent' => 'seating_tables', 'parentKey' => 'seating_table_id', 'parentColumn' => 'event_id', 'label' => 'assignment table'],
        ['child' => 'media_assets', 'childKey' => 'event_id', 'parent' => 'media_albums', 'parentKey' => 'media_album_id', 'parentColumn' => 'event_id', 'label' => 'media album'],
        ['child' => 'media_assets', 'childKey' => 'event_id', 'parent' => 'guests', 'parentKey' => 'guest_id', 'parentColumn' => 'event_id', 'label' => 'media guest'],
    ];

    public function up(): void
    {
        $this->repairInconsistentRows();

        // Composite unique keys required as FK targets.
        foreach ([
            ['guests', ['event_id', 'id']],
            ['seating_tables', ['event_id', 'id']],
            ['media_albums', ['event_id', 'id']],
            ['guest_categories', ['event_id', 'id']],
            ['guest_groups', ['event_id', 'id']],
            ['poll_options', ['poll_id', 'id']],
        ] as [$table, $columns]) {
            Schema::table($table, fn (Blueprint $t) => $t->unique($columns, 'uq_'.$table.'_'.implode('_', $columns)));
        }

        Schema::table('guests', function (Blueprint $table): void {
            $table->foreign(['event_id', 'guest_category_id'], 'fk_guests_event_category')
                ->references(['event_id', 'id'])->on('guest_categories');
            $table->foreign(['event_id', 'guest_group_id'], 'fk_guests_event_group')
                ->references(['event_id', 'id'])->on('guest_groups');
        });

        foreach (['rsvps', 'checkins', 'guest_tokens', 'invitations', 'guestbook_messages', 'song_requests'] as $child) {
            Schema::table($child, function (Blueprint $table) use ($child): void {
                $table->foreign(['event_id', 'guest_id'], 'fk_'.$child.'_event_guest')
                    ->references(['event_id', 'id'])->on('guests');
            });
        }

        Schema::table('table_assignments', function (Blueprint $table): void {
            $table->foreign(['event_id', 'guest_id'], 'fk_assignments_event_guest')
                ->references(['event_id', 'id'])->on('guests');
            $table->foreign(['event_id', 'seating_table_id'], 'fk_assignments_event_table')
                ->references(['event_id', 'id'])->on('seating_tables');
        });

        Schema::table('media_assets', function (Blueprint $table): void {
            $table->foreign(['event_id', 'media_album_id'], 'fk_media_assets_event_album')
                ->references(['event_id', 'id'])->on('media_albums');
            $table->foreign(['event_id', 'guest_id'], 'fk_media_assets_event_guest')
                ->references(['event_id', 'id'])->on('guests');
        });

        Schema::table('poll_responses', function (Blueprint $table): void {
            $table->foreign(['poll_id', 'poll_option_id'], 'fk_poll_responses_poll_option')
                ->references(['poll_id', 'id'])->on('poll_options');
        });
    }

    public function down(): void
    {
        Schema::table('poll_responses', fn (Blueprint $t) => $t->dropForeign('fk_poll_responses_poll_option'));
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropForeign('fk_media_assets_event_album');
            $table->dropForeign('fk_media_assets_event_guest');
        });
        Schema::table('table_assignments', function (Blueprint $table): void {
            $table->dropForeign('fk_assignments_event_guest');
            $table->dropForeign('fk_assignments_event_table');
        });
        foreach (['rsvps', 'checkins', 'guest_tokens', 'invitations', 'guestbook_messages', 'song_requests'] as $child) {
            Schema::table($child, fn (Blueprint $t) => $t->dropForeign('fk_'.$child.'_event_guest'));
        }
        Schema::table('guests', function (Blueprint $table): void {
            $table->dropForeign('fk_guests_event_category');
            $table->dropForeign('fk_guests_event_group');
        });

        foreach ([
            ['guests', ['event_id', 'id']],
            ['seating_tables', ['event_id', 'id']],
            ['media_albums', ['event_id', 'id']],
            ['guest_categories', ['event_id', 'id']],
            ['guest_groups', ['event_id', 'id']],
            ['poll_options', ['poll_id', 'id']],
        ] as [$table, $columns]) {
            Schema::table($table, fn (Blueprint $t) => $t->dropUnique('uq_'.$table.'_'.implode('_', $columns)));
        }
    }

    /**
     * Align child event_id with the authoritative parent row.
     */
    protected function repairInconsistentRows(): void
    {
        foreach ($this->invariants as $invariant) {
            $mismatch = DB::table($invariant['child'].' as c')
                ->join($invariant['parent'].' as p', 'p.id', '=', 'c.'.$invariant['parentKey'])
                ->whereColumn('c.'.$invariant['childKey'], '!=', 'p.'.$invariant['parentColumn'])
                ->count();

            if ($mismatch === 0) {
                continue;
            }

            DB::table($invariant['child'].' as c')
                ->join($invariant['parent'].' as p', 'p.id', '=', 'c.'.$invariant['parentKey'])
                ->whereColumn('c.'.$invariant['childKey'], '!=', 'p.'.$invariant['parentColumn'])
                ->update(['c.'.$invariant['childKey'] => DB::raw('p.'.$invariant['parentColumn'])]);

            $remaining = DB::table($invariant['child'].' as c')
                ->join($invariant['parent'].' as p', 'p.id', '=', 'c.'.$invariant['parentKey'])
                ->whereColumn('c.'.$invariant['childKey'], '!=', 'p.'.$invariant['parentColumn'])
                ->count();

            if ($remaining > 0) {
                throw new RuntimeException(
                    "Cannot enforce tenant integrity for {$invariant['label']}: {$remaining} inconsistent row(s) remain in {$invariant['child']}."
                );
            }
        }
    }
};
