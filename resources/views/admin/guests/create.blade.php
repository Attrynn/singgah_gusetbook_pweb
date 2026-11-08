@extends('layouts.admin')

@section('title', 'Tambah Tamu')
@section('subtitle', 'Tambah tamu baru')

@section('content')
    <x-ui.page-header title="Tambah Tamu" eyebrow="Guest Details">
        <x-ui.button variant="secondary" href="{{ route('admin.guests.index') }}" icon="arrow-left">
            Kembali
        </x-ui.button>
    </x-ui.page-header>

    <div class="mt-6">
        <form method="POST" action="{{ route('admin.guests.store') }}" class="space-y-6">
            <div>
                <label for="event_id" class="label">
                    Event
                </label>
                <select id="event_id" name="event_id" class="input" required>
                    <option value="">Pilih event</option>
                    @foreach ($events as $event)
                        <option value="{{ $event->id }}" {{ request('event_id') == $event->id ? 'selected' : '' }}>
                            {{ $event->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="name" class="label">
                    Name <span class="text-danger">*</span>
                </label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" class="input" required aria-invalid="true" aria-describedby="name-error">
                @error('name')
                    <p id="name-error" class="error-text">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="label">
                    Email
                </label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" class="input">
            </div>

            <div>
                <label for="phone" class="label">
                    Phone
                </label>
                <input id="phone" type="text" name="phone" value="{{ old('phone') }}" class="input">
            </div>

            <div>
                <label for="guest_category_id" class="label">
                    Category
                </label>
                <select id="guest_category_id" name="guest_category_id" class="input">
                    <option value="">Pilih kategori</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" {{ request('guest_category_id') == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="guest_group_id" class="label">
                    Group
                </label>
                <select id="guest_group_id" name="guest_group_id" class="input">
                    <option value="">Pilih kelompok</option>
                    @foreach ($groups as $group)
                        <option value="{{ $group->id }}" {{ request('guest_group_id') == $group->id ? 'selected' : '' }}>
                            {{ $group->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="status" class="label">
                    Status
                </label>
                <select id="status" name="status" class="input" required>
                    <option value="">Pilih status</option>
                    @foreach (\App\Enums\GuestStatus::cases() as $status)
                        <option value="{{ $status->value }}" {{ request('status') == $status->value ? 'selected' : '' }}>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="max_attendees" class="label">
                    Max Attendees <span class="text-danger">*</span>
                </label>
                <input id="max_attendees" type="number" name="max_attendees" value="{{ old('max_attendees', 2) }}" min="1" max="20" class="input" required>
            </div>

            <div>
                <label for="notes" class="label">
                    Notes
                </label>
                <textarea id="notes" name="notes" rows="3" class="input">{{ old('notes') }}</textarea>
                <p class="hint">Catatan tambahan tentang tamu</p>
            </div>

            <div class="flex justify-between">
                <button type="submit" class="btn btn-primary">
                    <x-ui.icon name="save" class="h-4 w-4" />
                    Simpan
                </button>
                <a href="{{ route('admin.guests.index') }}" class="btn btn-ghost">
                    <x-ui.icon name="arrow-left" class="h-4 w-4" />
                    Batal
                </a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-datepicker]').forEach(el => {
                flatpickr(el, {
                    dateFormat: 'Y-m-d',
                    allowInput: true,
                    disableMobile: true,
                });
            });
        });
    </script>
@endpush