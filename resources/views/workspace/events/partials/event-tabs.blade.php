{{--
    Shared navigation strip for a workspace event. Each tab renders only when
    the acting user passes the matching EventPolicy ability, so a staff member
    never sees links they cannot open. `$active` is one of:
    overview | setup | team | schedule | run-of-show | playbook | guests
--}}
@php
    $active = $active ?? 'overview';
    $canSetup = auth()->user()->can('manageSetup', $event);
    $canTeam = auth()->user()->can('manageTeam', $event);
    $canSchedule = auth()->user()->can('manageSchedule', $event);
    $canRunOfShow = auth()->user()->can('viewRunOfShow', $event);
    $canPlaybooks = auth()->user()->can('managePlaybooks', $organization);
    $canGuests = auth()->user()->can('viewGuests', $event);

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
