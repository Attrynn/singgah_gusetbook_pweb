@extends('layouts.owner')

@section('title', 'Buat Workspace')

@section('content')
    <div class="mx-auto w-full max-w-xl">
        <div class="card p-6 sm:p-8 animate-float-in">
            <p class="section-kicker">Workspace baru</p>
            <h1 class="mt-2 font-serif text-2xl font-semibold text-ink">Buat workspace tambahan</h1>
            <p class="mt-2 text-sm leading-relaxed text-muted">
                Anda akan menjadi Workspace Owner untuk organization baru ini.
            </p>

            <form method="POST" action="{{ route('workspace.store') }}" class="mt-7 space-y-5">
                @csrf

                <x-ui.input
                    name="workspace_name"
                    label="Nama workspace"
                    type="text"
                    placeholder="Contoh: Pernikahan Andi & Sari"
                    required
                    autocomplete="organization"
                    autofocus
                />

                <x-ui.button type="submit" icon="arrow-up-right" class="w-full">
                    Buat workspace
                </x-ui.button>
            </form>
        </div>
    </div>
@endsection
