@extends('layouts.admin')

@section('title', 'Buat Owner')
@section('subtitle', 'Buat akun owner baru')

@section('content')
    <x-ui.page-header title="Buat Owner" eyebrow="Owner Account">
        <x-ui.button variant="secondary" href="{{ route('admin.owners.index') }}" icon="arrow-left">
            Kembali
        </x-ui.button>
    </x-ui.page-header>

    <div class="mt-6">
        <form method="POST" action="{{ route('admin.owners.store') }}" class="space-y-6">
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
                    Email <span class="text-danger">*</span>
                </label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" class="input" required aria-invalid="true" aria-describedby="email-error">
                @error('email')
                    <p id="email-error" class="error-text">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="phone" class="label">
                    Phone
                </label>
                <input id="phone" type="text" name="phone" value="{{ old('phone') }}" class="input">
            </div>

            <div>
                <label for="password" class="label">
                    Password <span class="text-danger">*</span>
                </label>
                <input id="password" type="password" name="password" class="input" required aria-invalid="true" aria-describedby="password-error">
                @error('password')
                    <p id="password-error" class="error-text">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="label">
                    Password Confirmation <span class="text-danger">*</span>
                </label>
                <input id="password_confirmation" type="password" name="password_confirmation" class="input" required aria-invalid="true" aria-describedby="password_confirmation-error">
                @error('password_confirmation')
                    <p id="password_confirmation-error" class="error-text">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="is_active" class="label">
                    Is Active
                </label>
                <input id="is_active" type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
            </div>

            <div>
                <p class="label">
                    Event Assignment
                </p>
                <div class="flex flex-col space-y-2">
                    @forelse ($events as $event)
                        <div class="flex items-center gap-2">
                            <input id="owner-event-{{ $event->id }}" type="checkbox" name="event_ids[]" value="{{ $event->id }}" {{ in_array($event->id, old('event_ids', [])) ? 'checked' : '' }}>
                            <label for="owner-event-{{ $event->id }}" class="flex-1">{{ $event->name }}</label>
                            <select name="role_labels[{{ $event->id }}]" class="input w-48" aria-label="Role {{ $event->name }}">
                                <option value="">Pilih role</option>
                                <option value="groom" {{ old("role_labels.{$event->id}", '') == 'groom' ? 'selected' : '' }}>Groom</option>
                                <option value="bride" {{ old("role_labels.{$event->id}", '') == 'bride' ? 'selected' : '' }}>Bride</option>
                                <option value="partner" {{ old("role_labels.{$event->id}", '') == 'partner' ? 'selected' : '' }}>Partner</option>
                            </select>
                        </div>
                    @empty
                        <div class="text-center text-muted text-sm">No events available</div>
                    @endforelse
                </div>
            </div>

            <div class="flex justify-between">
                <button type="submit" class="btn btn-primary">
                    <x-ui.icon name="save" class="h-4 w-4" />
                    Simpan
                </button>
                <a href="{{ route('admin.owners.index') }}" class="btn btn-ghost">
                    <x-ui.icon name="arrow-left" class="h-4 w-4" />
                    Batal
                </a>
            </div>
        </form>
    </div>
@endsection