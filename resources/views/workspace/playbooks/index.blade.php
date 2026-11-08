@extends('layouts.owner')

@section('title', 'Playbook')

@php
    use App\Enums\PlaybookStatus;

    $platformPlaybooks = collect($platformPlaybooks ?? []);
    $organizationPlaybooks = collect($organizationPlaybooks ?? []);

    $canManage = auth()->user()->can('managePlaybooks', $organization);

    $eventTypeLabel = fn ($type) => $type ? ucfirst(str_replace('_', ' ', $type)) : 'Umum';
@endphp

@section('content')
    <x-ui.page-header
        title="Playbook"
        eyebrow="Workspace"
        :subtitle="'Template struktur acara untuk '.$organization->name.'. Gunakan template platform atau susun playbook workspace sendiri.'"
    >
        @if ($canManage)
            <x-ui.button icon="plus" x-on:click="$dispatch('open-modal-playbook-create')">Buat playbook</x-ui.button>
        @endif
    </x-ui.page-header>

    {{-- Template platform --}}
    <section class="mt-6">
        <div class="mb-3 flex items-center gap-2">
            <h2 class="section-title">Template platform</h2>
            <x-ui.badge variant="info" class="text-[10px]">{{ $platformPlaybooks->count() }}</x-ui.badge>
        </div>

        @if ($platformPlaybooks->isEmpty())
            <x-ui.empty-state icon="file-text" title="Belum ada template platform" description="Template resmi dari VOWSPACE akan muncul di sini." />
        @else
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($platformPlaybooks as $playbook)
                    <article class="card flex flex-col p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-ink">{{ $playbook->name }}</p>
                                <p class="mt-0.5 text-[11px] text-faint">{{ $eventTypeLabel($playbook->event_type) }} · {{ $playbook->days }} hari</p>
                            </div>
                            <x-ui.badge :variant="$playbook->status->badge()" class="shrink-0 text-[10px]">{{ $playbook->status->label() }}</x-ui.badge>
                        </div>

                        @if ($playbook->description)
                            <p class="mt-3 line-clamp-2 text-xs text-muted">{{ $playbook->description }}</p>
                        @endif

                        <p class="mt-3 text-[11px] text-faint">Versi terbaru: v{{ $playbook->latestVersion?->version ?? '—' }}</p>

                        <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-line/60 pt-4">
                            <a href="{{ route('workspace.playbooks.show', [$organization, $playbook]) }}" class="btn btn-secondary btn-sm">
                                <x-ui.icon name="eye" class="h-3.5 w-3.5" /> Lihat
                            </a>
                            @if ($canManage)
                                <form method="POST" action="{{ route('workspace.playbooks.duplicate', [$organization, $playbook]) }}" data-confirm="Duplikat template ini menjadi playbook workspace?" data-confirm-button="Ya, duplikat">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost btn-sm">
                                        <x-ui.icon name="copy" class="h-3.5 w-3.5" /> Duplikat
                                    </button>
                                </form>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Playbook workspace --}}
    <section class="mt-8">
        <div class="mb-3 flex items-center gap-2">
            <h2 class="section-title">Playbook workspace</h2>
            <x-ui.badge variant="neutral" class="text-[10px]">{{ $organizationPlaybooks->count() }}</x-ui.badge>
        </div>

        @if ($organizationPlaybooks->isEmpty())
            <x-ui.empty-state icon="file-text" title="Belum ada playbook workspace" description="Buat playbook sendiri atau duplikat template platform untuk mulai menyusun struktur acara.">
                @if ($canManage)
                    <x-ui.button size="sm" icon="plus" x-on:click="$dispatch('open-modal-playbook-create')">Buat playbook</x-ui.button>
                @endif
            </x-ui.empty-state>
        @else
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($organizationPlaybooks as $playbook)
                    <article class="card flex flex-col p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-ink">{{ $playbook->name }}</p>
                                <p class="mt-0.5 text-[11px] text-faint">{{ $eventTypeLabel($playbook->event_type) }} · {{ $playbook->days }} hari</p>
                            </div>
                            <x-ui.badge :variant="$playbook->status->badge()" class="shrink-0 text-[10px]">{{ $playbook->status->label() }}</x-ui.badge>
                        </div>

                        @if ($playbook->description)
                            <p class="mt-3 line-clamp-2 text-xs text-muted">{{ $playbook->description }}</p>
                        @endif

                        <p class="mt-3 text-[11px] text-faint">Versi terbaru: v{{ $playbook->latestVersion?->version ?? '—' }}</p>

                        <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-line/60 pt-4">
                            <a href="{{ route('workspace.playbooks.show', [$organization, $playbook]) }}" class="btn btn-secondary btn-sm">
                                <x-ui.icon name="eye" class="h-3.5 w-3.5" /> Lihat
                            </a>
                            @if ($canManage)
                                <form method="POST" action="{{ route('workspace.playbooks.duplicate', [$organization, $playbook]) }}" data-confirm="Duplikat playbook ini?" data-confirm-button="Ya, duplikat">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost btn-sm">
                                        <x-ui.icon name="copy" class="h-3.5 w-3.5" /> Duplikat
                                    </button>
                                </form>
                                @if ($playbook->status === PlaybookStatus::Active)
                                    <form method="POST" action="{{ route('workspace.playbooks.deactivate', [$organization, $playbook]) }}" data-confirm="Nonaktifkan playbook ini? Playbook tidak akan muncul saat membuat acara baru." data-confirm-button="Ya, nonaktifkan">
                                        @csrf
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <x-ui.icon name="close" class="h-3.5 w-3.5" /> Nonaktifkan
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Modal buat playbook --}}
    @if ($canManage)
        <x-ui.modal name="playbook-create" title="Buat playbook" description="Playbook menyimpan struktur acara yang dapat dipakai ulang.">
            <form method="POST" action="{{ route('workspace.playbooks.store', $organization) }}" class="space-y-4">
                @csrf
                <x-ui.input name="name" label="Nama playbook" required placeholder="Contoh: Pernikahan adat Sunda" />
                <x-ui.textarea name="description" label="Deskripsi" rows="2" placeholder="Ringkasan singkat playbook (opsional)" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input name="event_type" label="Jenis acara" placeholder="Contoh: pernikahan" />
                    <x-ui.input name="days" label="Jumlah hari" type="number" :value="1" min="1" max="30" />
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" class="btn btn-ghost" x-on:click="$dispatch('close-modal-playbook-create')">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <x-ui.icon name="save" class="h-4 w-4" /> Simpan playbook
                    </button>
                </div>
            </form>
        </x-ui.modal>
    @endif
@endsection