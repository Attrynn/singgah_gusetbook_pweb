@extends('layouts.admin')

@section('title', 'Buat Event')
@section('subtitle', 'Buat event baru')

@section('content')
    <x-ui.page-header title="Buat Event" eyebrow="Event Details">
        <x-ui.button variant="secondary" href="{{ route('admin.events.index') }}" icon="arrow-left">
            Kembali
        </x-ui.button>
    </x-ui.page-header>

    <div class="mt-6">
        <form method="POST" action="{{ route('admin.events.store') }}" enctype="multipart/form-data" class="space-y-6">
            <div>
                <label for="name" class="label">
                    Nama Event <span class="text-danger">*</span>
                </label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" class="input" required aria-invalid="true" aria-describedby="name-error">
                @error('name')
                    <p id="name-error" class="error-text">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="slug" class="label">
                    Slug (opsional)
                </label>
                <input id="slug" type="text" name="slug" value="{{ old('slug') }}" class="input" aria-invalid="true" aria-describedby="slug-error">
                @error('slug')
                    <p id="slug-error" class="error-text">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="groom_name" class="label">
                    Groom Name <span class="text-danger">*</span>
                </label>
                <input id="groom_name" type="text" name="groom_name" value="{{ old('groom_name') }}" class="input" required aria-invalid="true" aria-describedby="groom_name-error">
                @error('groom_name')
                    <p id="groom_name-error" class="error-text">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="bride_name" class="label">
                    Bride Name <span class="text-danger">*</span>
                </label>
                <input id="bride_name" type="text" name="bride_name" value="{{ old('bride_name') }}" class="input" required aria-invalid="true" aria-describedby="bride_name-error">
                @error('bride_name')
                    <p id="bride_name-error" class="error-text">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="groom_profile" class="label">
                    Groom Profile
                </label>
                <textarea id="groom_profile" name="groom_profile" rows="3" class="input">{{ old('groom_profile') }}</textarea>
                <p class="hint">Deskripsi singkat tentang groom</p>
            </div>

            <div>
                <label for="bride_profile" class="label">
                    Bride Profile
                </label>
                <textarea id="bride_profile" name="bride_profile" rows="3" class="input">{{ old('bride_profile') }}</textarea>
                <p class="hint">Deskripsi singkat tentang bride</p>
            </div>

            <div>
                <label for="couple_description" class="label">
                    Couple Description
                </label>
                <textarea id="couple_description" name="couple_description" rows="4" class="input">{{ old('couple_description') }}</textarea>
                <p class="hint">Deskripsi lengkap tentang pasangan</p>
            </div>

            <div>
                <label for="cover" class="label">
                    Cover Image
                </label>
                <input id="cover" type="file" name="cover" class="input" accept="image/*">
                <p class="hint">Jenis file: jpg, jpeg, png, webp. Maksimal 5MB.</p>
            </div>

            <div>
                <label for="event_date" class="label">
                    Event Date
                </label>
                <input id="event_date" type="date" name="event_date" value="{{ old('event_date') }}" class="input" required>
                <p class="hint">Format: YYYY-MM-DD</p>
            </div>

            <div>
                <label for="start_time" class="label">
                    Start Time
                </label>
                <input id="start_time" type="time" name="start_time" value="{{ old('start_time') }}" class="input">
                <p class="hint">Format: HH:MM</p>
            </div>

            <div>
                <label for="end_time" class="label">
                    End Time
                </label>
                <input id="end_time" type="time" name="end_time" value="{{ old('end_time') }}" class="input">
                <p class="hint">Format: HH:MM</p>
            </div>

            <div>
                <label for="venue_name" class="label">
                    Venue Name <span class="text-danger">*</span>
                </label>
                <input id="venue_name" type="text" name="venue_name" value="{{ old('venue_name') }}" class="input" required>
            </div>

            <div>
                <label for="venue_address" class="label">
                    Venue Address
                </label>
                <textarea id="venue_address" name="venue_address" rows="3" class="input">{{ old('venue_address') }}</textarea>
            </div>

            <div>
                <label for="google_maps_url" class="label">
                    Google Maps URL
                </label>
                <input id="google_maps_url" type="url" name="google_maps_url" value="{{ old('google_maps_url') }}" class="input">
            </div>

            <div>
                <label for="latitude" class="label">
                    Latitude
                </label>
                <input id="latitude" type="number" name="latitude" step="0.000001" value="{{ old('latitude') }}" class="input">
            </div>

            <div>
                <label for="longitude" class="label">
                    Longitude
                </label>
                <input id="longitude" type="number" name="longitude" step="0.000001" value="{{ old('longitude') }}" class="input">
            </div>

            <div>
                <label for="dress_code" class="label">
                    Dress Code
                </label>
                <input id="dress_code" type="text" name="dress_code" value="{{ old('dress_code') }}" class="input">
            </div>

            <div>
                <label for="description" class="label">
                    Description
                </label>
                <textarea id="description" name="description" rows="4" class="input">{{ old('description') }}</textarea>
            </div>

            <div>
                <label for="status" class="label">
                    Status
                </label>
                <select id="status" name="status" class="input" required>
                    <option value="">Pilih status</option>
                    @foreach (\App\Enums\EventStatus::cases() as $status)
                        <option value="{{ $status->value }}" {{ request('status') == $status->value ? 'selected' : '' }}>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="rsvp_deadline" class="label">
                    RSVP Deadline
                </label>
                <input id="rsvp_deadline" type="datetime-local" name="rsvp_deadline" value="{{ old('rsvp_deadline') }}" class="input">
            </div>

            <div>
                <label for="rsvp_enabled" class="label">
                    RSVP Enabled
                </label>
                <input id="rsvp_enabled" type="checkbox" name="rsvp_enabled" value="1" {{ old('rsvp_enabled') ? 'checked' : '' }}>
            </div>

            <div>
                <label for="guestbook_enabled" class="label">
                    Guestbook Enabled
                </label>
                <input id="guestbook_enabled" type="checkbox" name="guestbook_enabled" value="1" {{ old('guestbook_enabled') ? 'checked' : '' }}>
            </div>

            <div>
                <label for="gallery_enabled" class="label">
                    Gallery Enabled
                </label>
                <input id="gallery_enabled" type="checkbox" name="gallery_enabled" value="1" {{ old('gallery_enabled') ? 'checked' : '' }}>
            </div>

            <div>
                <label for="gift_enabled" class="label">
                    Gift Enabled
                </label>
                <input id="gift_enabled" type="checkbox" name="gift_enabled" value="1" {{ old('gift_enabled') ? 'checked' : '' }}>
            </div>

            <div>
                <label for="live_wall_enabled" class="label">
                    Live Wall Enabled
                </label>
                <input id="live_wall_enabled" type="checkbox" name="live_wall_enabled" value="1" {{ old('live_wall_enabled') ? 'checked' : '' }}>
            </div>

            <div>
                <label for="timezone" class="label">
                    Timezone
                </label>
                <input id="timezone" type="text" name="timezone" value="{{ old('timezone') }}" class="input" placeholder="Asia/Jakarta">
            </div>

            <div>
                <p class="label">
                    Schedules
                </p>
                <div class="flex flex-col space-y-2">
                    @php
                        $scheduleRows = old('schedules', $schedules ?? []);
                        if (empty($scheduleRows)) {
                            $scheduleRows = [['title' => '', 'description' => '', 'start_time' => '', 'end_time' => '', 'status' => 'planned']];
                        }
                    @endphp
                    @foreach ($scheduleRows as $i => $schedule)
                        <div class="flex flex-wrap items-center gap-2">
                            <input type="text" name="schedules[{{ $i }}][title]" placeholder="Judul" class="input w-64" aria-label="Judul jadwal" value="{{ old("schedules.{$i}.title", $schedule['title'] ?? '') }}">
                            <input type="text" name="schedules[{{ $i }}][description]" placeholder="Deskripsi" class="input w-64" aria-label="Deskripsi jadwal" value="{{ old("schedules.{$i}.description", $schedule['description'] ?? '') }}">
                            <input type="datetime-local" name="schedules[{{ $i }}][start_time]" class="input w-48" aria-label="Waktu mulai jadwal" value="{{ old("schedules.{$i}.start_time", $schedule['start_time'] ?? '') }}">
                            <input type="datetime-local" name="schedules[{{ $i }}][end_time]" class="input w-48" aria-label="Waktu selesai jadwal" value="{{ old("schedules.{$i}.end_time", $schedule['end_time'] ?? '') }}">
                            <select name="schedules[{{ $i }}][status]" class="input w-48" aria-label="Status jadwal">
                                <option value="">Pilih status</option>
                                @foreach (['planned', 'ongoing', 'done'] as $status)
                                    <option value="{{ $status }}" @selected(($schedule['status'] ?? 'planned') === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
                <p class="text-xs text-muted">Tambahkan baris jadwal untuk menentukan waktu dan status event</p>
            </div>

            <div>
                <p class="label">
                    Owner Assignment
                </p>
                <div class="flex flex-col space-y-2">
                    <div class="flex items-center gap-2">
                        <input type="text" name="owner_ids[]" placeholder="Cari owner..." class="input w-64" aria-label="Cari owner">
                        <select name="owner_roles[]" class="input w-48" aria-label="Pilih role">
                            <option value="">Pilih role</option>
                            <option value="groom">Groom</option>
                            <option value="bride">Bride</option>
                            <option value="partner">Partner</option>
                        </select>
                    </div>
                    @forelse ($owners as $owner)
                        <div class="flex items-center gap-2">
                            <input id="owner-{{ $owner->id }}" type="checkbox" name="owner_ids[]" value="{{ $owner->id }}" {{ in_array($owner->id, old('owner_ids', [])) ? 'checked' : '' }}>
                            <label for="owner-{{ $owner->id }}" class="flex-1">{{ $owner->name }}</label>
                            <select name="owner_roles[{{ $owner->id }}]" class="input w-48" aria-label="Role {{ $owner->name }}">
                                <option value="">Pilih role</option>
                                <option value="groom" {{ old("owner_roles.{$owner->id}", '') == 'groom' ? 'selected' : '' }}>Groom</option>
                                <option value="bride" {{ old("owner_roles.{$owner->id}", '') == 'bride' ? 'selected' : '' }}>Bride</option>
                                <option value="partner" {{ old("owner_roles.{$owner->id}", '') == 'partner' ? 'selected' : '' }}>Partner</option>
                            </select>
                        </div>
                    @empty
                        <div class="flex items-center gap-2">
                            <input type="text" name="owner_ids[]" placeholder="Cari owner..." class="input w-64" aria-label="Cari owner">
                            <select name="owner_roles[]" class="input w-48" aria-label="Pilih role">
                                <option value="">Pilih role</option>
                                <option value="groom">Groom</option>
                                <option value="bride">Bride</option>
                                <option value="partner">Partner</option>
                            </select>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="flex justify-between">
                <button type="submit" class="btn btn-primary">
                    <x-ui.icon name="save" class="h-4 w-4" />
                    Simpan
                </button>
                <a href="{{ route('admin.events.index') }}" class="btn btn-ghost">
                    <x-ui.icon name="arrow-left" class="h-4 w-4" />
                    Batal
                </a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-datepicker]').forEach(el => {
                flatpickr(el, {
                    dateFormat: 'Y-m-d',
                    allowInput: true,
                    disableMobile: true,
                });
            });

            document.querySelectorAll('[data-datetimepicker]').forEach(el => {
                flatpickr(el, {
                    enableTime: true,
                    dateFormat: 'Y-m-d H:i',
                    time_24hr: true,
                    allowInput: true,
                    disableMobile: true,
                });
            });
        });
    </script>
@endpush