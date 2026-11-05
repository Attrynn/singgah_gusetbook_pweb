@extends('layouts.admin')

@section('title', 'Edit Owner')
@section('subtitle', 'Perbarui akun owner')

@section('content')
    <x-ui.page-header title="Edit Owner" eyebrow="Owner Account">
        <x-ui.button variant="secondary" href="{{ route('admin.owners.index') }}" icon="arrow-left">
            Kembali
        </x-ui.button>
    </x-ui.page-header>

    <div class="mt-6">
        <form method="PUT" action="{{ route('admin.owners.update', $owner) }}" class="space-y-6">
            <div>
                <label class="label">
                    Name <span class="text-[#b76a73]">*</span>
                </label>
                <input type="text" name="name" value="{{ old('name', $owner->name) }}" class="input" required>
                @error('name')
                    <p class="error-text">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="label">
                    Email <span class="text-[#b76a73]">*</span>
                </label>
                <input type="email" name="email" value="{{ old('email', $owner->email) }}" class="input" required>
                @error('email')
                    <p class="error-text">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="label">
                    Phone
                </label>
                <input type="text" name="phone" value="{{ old('phone', $owner->phone) }}" class="input">
            </div>

            <div>
                <label class="label">
                    Password (opsional)
                </label>
                <input type="password" name="password" class="input">
                <p class="hint">Kosongkan jika tidak ingin mengubah password</p>
            </div>

            <div>
                <label class="label">
                    Password Confirmation
                </label>
                <input type="password" name="password_confirmation" class="input">
            </div>

            <div>
                <label class="label">
                    Is Active
                </label>
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $owner->is_active) ? 'checked' : '' }}>
            </div>

            <div>
                <label class="label">
                    Event Assignment
                </label>
                <div class="flex flex-col space-y-2">
                    @forelse ($events as $event)
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="event_ids[]" value="{{ $event->id }}" {{ in_array($event->id, old('event_ids', $owner->eventIds())) ? 'checked' : '' }}>
                            <label class="flex-1">{{ $event->name }}</label>
                            <select name="role_labels[{{ $event->id }}]" class="input w-48">
                                <option value="">Pilih role</option>
                                <option value="groom" {{ old("role_labels.{$event->id}", $owner->events->firstWhere('id', $event->id)->pivot->role_label ?? '') == 'groom' ? 'selected' : '' }}>Groom</option>
                                <option value="bride" {{ old("role_labels.{$event->id}", $owner->events->firstWhere('id', $event->id)->pivot->role_label ?? '') == 'bride' ? 'selected' : '' }}>Bride</option>
                                <option value="partner" {{ old("role_labels.{$event->id}", $owner->events->firstWhere('id', $event->id)->pivot->role_label ?? '') == 'partner' ? 'selected' : '' }}>Partner</option>
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