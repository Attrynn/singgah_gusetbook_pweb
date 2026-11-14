{{--
    Shared navigation strip for a workspace event. Each tab renders only when
    the acting user passes the matching EventPolicy ability, so a staff member
    never sees links they cannot open. `$active` is one of:
    overview | setup | team | schedule | run-of-show | guests | checkins | playbook
    | venues | floor-plans
    | operations | operations-my-work | operations-staff | operations-stations
    | operations-tasks | operations-incidents | operations-announcements
    | operations-reports | operations-closure
    | vendors | vendors-engagements | vendors-portal | vendors-reports
--}}
@php
    $active = $active ?? 'overview';
    $canSetup = auth()->user()->can('manageSetup', $event);
    $canTeam = auth()->user()->can('manageTeam', $event);
    $canSchedule = auth()->user()->can('manageSchedule', $event);
    $canRunOfShow = auth()->user()->can('viewRunOfShow', $event);
    $canPlaybooks = auth()->user()->can('managePlaybooks', $organization);
    $canGuests = auth()->user()->can('viewGuests', $event);
    $canViewVenues = auth()->user()?->can('viewVenues', $event) ?? false;
    $canViewFloorPlans = auth()->user()?->can('viewFloorPlans', $event) ?? false;

    $appliedPlaybook = ($setup ?? null)?->playbook ?? $event->setupConfig?->playbook;

    $tabClass = fn (bool $isActive): string => $isActive
        ? 'inline-flex items-center gap-2 rounded-xl border border-line/70 bg-surface px-3 py-2 text-xs font-semibold text-accent shadow-raised'
        : 'inline-flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-medium text-muted transition hover:bg-surface-2/60 hover:text-ink';
@endphp

<div class="mt-5 -mx-4 overflow-x-auto border-b border-line/70 px-4 sm:mx-0 sm:px-0">
    <nav class="flex min-w-max items-center gap-1 pb-3" aria-label="Navigasi acara">
        <a href="{{ route('workspace.events.show', [$organization, $event]) }}" class="{{ $tabClass($active === 'overview') }}" @if ($active === 'overview') aria-current="page" @endif>
            <x-ui.icon name="eye" class="h-3.5 w-3.5 shrink-0" />
            <span>Ringkasan</span>
        </a>

        @if ($canSetup)
            <a href="{{ route('workspace.events.setup', [$organization, $event]) }}" class="{{ $tabClass($active === 'setup') }}" @if ($active === 'setup') aria-current="page" @endif>
                <x-ui.icon name="sliders" class="h-3.5 w-3.5 shrink-0" />
                <span>Persiapan</span>
            </a>
        @endif

        @if ($canTeam)
            <a href="{{ route('workspace.events.team.index', [$organization, $event]) }}" class="{{ $tabClass($active === 'team') }}" @if ($active === 'team') aria-current="page" @endif>
                <x-ui.icon name="users" class="h-3.5 w-3.5 shrink-0" />
                <span>Tim</span>
            </a>
        @endif

        @if ($canSchedule)
            <a href="{{ route('workspace.events.schedule.index', [$organization, $event]) }}" class="{{ $tabClass($active === 'schedule') }}" @if ($active === 'schedule') aria-current="page" @endif>
                <x-ui.icon name="clock" class="h-3.5 w-3.5 shrink-0" />
                <span>Jadwal publik</span>
            </a>
        @endif

        @if ($canRunOfShow)
            <a href="{{ route('workspace.events.run-of-show.index', [$organization, $event]) }}" class="{{ $tabClass($active === 'run-of-show') }}" @if ($active === 'run-of-show') aria-current="page" @endif>
                <x-ui.icon name="list" class="h-3.5 w-3.5 shrink-0" />
                <span>Run of show</span>
            </a>
        @endif

        @if ($canViewVenues && Route::has('workspace.events.venues.index'))
            <a href="{{ route('workspace.events.venues.index', [$organization, $event]) }}" class="{{ $tabClass($active === 'venues') }}" @if ($active === 'venues') aria-current="page" @endif>
                <x-ui.icon name="pin" class="h-3.5 w-3.5 shrink-0" />
                <span>Venue & Layout</span>
            </a>
        @endif

        @if ($canViewFloorPlans && Route::has('workspace.events.floor-plans.index'))
            <a href="{{ route('workspace.events.floor-plans.index', [$organization, $event]) }}" class="{{ $tabClass($active === 'floor-plans') }}" @if ($active === 'floor-plans') aria-current="page" @endif>
                <x-ui.icon name="grid" class="h-3.5 w-3.5 shrink-0" />
                <span>Floor Plan</span>
            </a>
        @endif

        @can('viewOperations', $event)
            <a href="{{ route('workspace.events.operations.index', [$organization, $event]) }}" class="{{ $tabClass(str_starts_with($active ?? '', 'operations')) }}" @if (str_starts_with($active ?? '', 'operations')) aria-current="page" @endif>
                <x-ui.icon name="clipboard" class="h-3.5 w-3.5 shrink-0" />
                <span>Operasi</span>
            </a>
        @endcan

        @if ($canGuests && Route::has('workspace.events.vendors.index'))
            <a href="{{ route('workspace.events.vendors.index', [$organization, $event]) }}" class="{{ $tabClass($active === 'vendors') }}" @if ($active === 'vendors') aria-current="page" @endif>
                <x-ui.icon name="clipboard" class="h-3.5 w-3.5 shrink-0" />
                <span>Vendor</span>
            </a>
        @endif

        @if ($canGuests)
            <a href="{{ route('workspace.events.guests.index', [$organization, $event]) }}" class="{{ $tabClass($active === 'guests') }}" @if ($active === 'guests') aria-current="page" @endif>
                <x-ui.icon name="ticket" class="h-3.5 w-3.5 shrink-0" />
                <span>Tamu & Undangan</span>
            </a>
        @endif

        @can('viewCheckins', $event)
            <a href="{{ route('workspace.events.checkins.index', [$organization, $event]) }}" class="{{ $tabClass($active === 'checkins') }}" @if ($active === 'checkins') aria-current="page" @endif>
                <x-ui.icon name="qr" class="h-3.5 w-3.5 shrink-0" />
                <span>Kehadiran & Check-in</span>
            </a>
        @endcan

        @if ($canPlaybooks)
            <a href="{{ $appliedPlaybook ? route('workspace.playbooks.show', [$organization, $appliedPlaybook]) : route('workspace.playbooks.index', $organization) }}" class="{{ $tabClass($active === 'playbook') }}" @if ($active === 'playbook') aria-current="page" @endif>
                <x-ui.icon name="file-text" class="h-3.5 w-3.5 shrink-0" />
                <span>Playbook</span>
            </a>
        @endif
    </nav>
</div>

{{-- Vendor sub-navigation for vendor hub pages --}}
@php
    $vendorSubTabClass = fn (bool $isActive): string => $isActive
        ? 'inline-flex items-center gap-1.5 rounded-full border border-accent/40 bg-accent/10 px-3 py-1.5 text-[11px] font-semibold text-accent'
        : 'inline-flex items-center gap-1.5 rounded-full border border-line/60 bg-surface px-3 py-1.5 text-[11px] font-medium text-muted transition hover:border-accent/30 hover:text-ink';
@endphp

@if (str_starts_with($active ?? '', 'vendors'))
    <div class="mt-3 -mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        <nav class="flex min-w-max items-center gap-1.5" aria-label="Navigasi vendor">
            @if (Route::has('workspace.events.vendors.index'))
                <a href="{{ route('workspace.events.vendors.index', [$organization, $event]) }}" class="{{ $vendorSubTabClass($active === 'vendors') }}">
                    <x-ui.icon name="clipboard" class="h-3 w-3 shrink-0" />
                    <span>Vendor Hub</span>
                </a>
            @endif
            @if (Route::has('workspace.events.vendors.engagements.index'))
                <a href="{{ route('workspace.events.vendors.engagements.index', [$organization, $event]) }}" class="{{ $vendorSubTabClass($active === 'vendors-engagements') }}">
                    <x-ui.icon name="users" class="h-3 w-3 shrink-0" />
                    <span>Engagements</span>
                </a>
            @endif
            @if (Route::has('workspace.events.vendors.portal.index'))
                <a href="{{ route('workspace.events.vendors.portal.index', [$organization, $event]) }}" class="{{ $vendorSubTabClass($active === 'vendors-portal') }}">
                    <x-ui.icon name="share" class="h-3 w-3 shrink-0" />
                    <span>Portal</span>
                </a>
            @endif
            @can('viewVendorReports', $event)
                @if (Route::has('workspace.events.vendors.reports.index'))
                    <a href="{{ route('workspace.events.vendors.reports.index', [$organization, $event]) }}" class="{{ $vendorSubTabClass($active === 'vendors-reports') }}">
                        <x-ui.icon name="chart" class="h-3 w-3 shrink-0" />
                        <span>Laporan</span>
                    </a>
                @endif
            @endif
        </nav>
    </div>
@endif

{{-- Secondary navigation for the operations workspace. Rendered only while
     one of the operations pages is active so the event tabs stay compact. --}}
@if (str_starts_with($active ?? '', 'operations'))
    @php
        $subTabClass = fn (bool $isActive): string => $isActive
            ? 'inline-flex items-center gap-1.5 rounded-full border border-accent/40 bg-accent/10 px-3 py-1.5 text-[11px] font-semibold text-accent'
            : 'inline-flex items-center gap-1.5 rounded-full border border-line/60 bg-surface px-3 py-1.5 text-[11px] font-medium text-muted transition hover:border-accent/30 hover:text-ink';
    @endphp

    <div class="mt-3 -mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        <nav class="flex min-w-max items-center gap-1.5" aria-label="Navigasi operasi">
            <a href="{{ route('workspace.events.operations.index', [$organization, $event]) }}" class="{{ $subTabClass($active === 'operations') }}">
                <x-ui.icon name="grid" class="h-3 w-3 shrink-0" />
                <span>Ruang kendali</span>
            </a>
            @can('viewTasks', $event)
                <a href="{{ route('workspace.events.operations.my-work', [$organization, $event]) }}" class="{{ $subTabClass($active === 'operations-my-work') }}">
                    <x-ui.icon name="clipboard" class="h-3 w-3 shrink-0" />
                    <span>Tugas saya</span>
                </a>
            @endcan
            @can('viewStaff', $event)
                <a href="{{ route('workspace.events.operations.staff.index', [$organization, $event]) }}" class="{{ $subTabClass($active === 'operations-staff') }}">
                    <x-ui.icon name="users" class="h-3 w-3 shrink-0" />
                    <span>Staf</span>
                </a>
            @endcan
            @can('viewStations', $event)
                <a href="{{ route('workspace.events.operations.stations.index', [$organization, $event]) }}" class="{{ $subTabClass($active === 'operations-stations') }}">
                    <x-ui.icon name="pin" class="h-3 w-3 shrink-0" />
                    <span>Stasiun</span>
                </a>
            @endcan
            @can('viewTasks', $event)
                <a href="{{ route('workspace.events.operations.tasks.index', [$organization, $event]) }}" class="{{ $subTabClass($active === 'operations-tasks') }}">
                    <x-ui.icon name="list" class="h-3 w-3 shrink-0" />
                    <span>Tugas</span>
                </a>
            @endcan
            @can('viewIncidents', $event)
                <a href="{{ route('workspace.events.operations.incidents.index', [$organization, $event]) }}" class="{{ $subTabClass($active === 'operations-incidents') }}">
                    <x-ui.icon name="alert" class="h-3 w-3 shrink-0" />
                    <span>Insiden</span>
                </a>
            @endcan
            @can('viewAnnouncements', $event)
                <a href="{{ route('workspace.events.operations.announcements.index', [$organization, $event]) }}" class="{{ $subTabClass($active === 'operations-announcements') }}">
                    <x-ui.icon name="send" class="h-3 w-3 shrink-0" />
                    <span>Pengumuman</span>
                </a>
            @endcan
            @can('viewReports', $event)
                <a href="{{ route('workspace.events.operations.reports.index', [$organization, $event]) }}" class="{{ $subTabClass($active === 'operations-reports') }}">
                    <x-ui.icon name="chart" class="h-3 w-3 shrink-0" />
                    <span>Laporan</span>
                </a>
            @endcan
            @can('closeOperations', $event)
                <a href="{{ route('workspace.events.operations.closure', [$organization, $event]) }}" class="{{ $subTabClass($active === 'operations-closure') }}">
                    <x-ui.icon name="check" class="h-3 w-3 shrink-0" />
                    <span>Penutupan</span>
                </a>
            @endcan
        </nav>
    </div>
@endif
