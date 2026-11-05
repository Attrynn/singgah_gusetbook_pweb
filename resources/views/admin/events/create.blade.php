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
                <label class="label">
                    Nama Event <span class="text-[#b76a73]">*</span>
                </label>
                <input type="text" name="name" value="{{ old('name') }}" class="input" required>
                @error('name')
                    <p class="error-text">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="label">
                    Slug (opsional)
                </label>
                <input type="text" name="slug" value="{{ old('slug') }}" class="input">
                @error('slug')
                    <p class="error-text">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="label">
                    Groom Name <span class="text-[#b76a73]">*</span>
                </label>
                <input type="text" name="groom_name" value="{{ old('groom_name') }}" class="input" required>
                @error('groom_name')
                    <p class="error-text">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="label">
                    Bride Name <span class="text-[#b76a73]">*</span>
                </label>
                <input type="text" name="bride_name" value="{{ old('bride_name') }}" class="input" required>
                @error('bride_name')
                    <p class="error-text">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="label">
                    Groom Profile
                </label>
                <textarea name="groom_profile" rows="3" class="input">{{ old('groom_profile') }}</textarea>
                <p class="hint">Deskripsi singkat tentang groom</p>
            </div>

            <div>
                <label class="label">
                    Bride Profile
                </label>
                <textarea name="bride_profile" rows="3" class="input">{{ old('bride_profile') }}</textarea>
                <p class="hint">Deskripsi singkat tentang bride</p>
            </div>

            <div>
                <label class="label">
                    Couple Description
                </label>
                <textarea name="couple_description" rows="4" class="input">{{ old('couple_description') }}</textarea>
                <p class="hint">Deskripsi lengkap tentang pasangan</p>
            </div>

            <div>
                <label class="label">
                    Cover Image
                </label>
                <input type="file" name="cover" class="input" accept="image/*">
                <p class="hint">Jenis file: jpg, jpeg, png, webp. Maksimal 5MB.</p>
            </div>

            <div>
                <label class="label">
                    Event Date
                </label>
                <input type="date" name="event_date" value="{{ old('event_date') }}" class="input" required>
                <p class="hint">Format: YYYY-MM-DD</p>
            </div>

            <div>
                <label class="label">
                    Start Time
                </label>
                <input type="time" name="start_time" value="{{ old('start_time') }}" class="input">
                <p class="hint">Format: HH:MM</p>
            </div>

            <div>
                <label class="label">
                    End Time
                </label>
                <input type="time" name="end_time" value="{{ old('end_time') }}" class="input">
                <p class="hint">Format: HH:MM</p>
            </div>

            <div>
                <label class="label">
                    Venue Name <span class="text-[#b76a73]">*</span>
                </label>
                <input type="text" name="venue_name" value="{{ old('venue_name') }}" class="input" required>
            </div>

            <div>
                <label class="label">
                    Venue Address
                </label>
                <textarea name="venue_address" rows="3" class="input">{{ old('venue_address') }}</textarea>
            </div>

            <div>
                <label class="label">
                    Google Maps URL
                </label>
                <input type="url" name="google_maps_url" value="{{ old('google_maps_url') }}" class="input">
            </div>

            <div>
                <label class="label">
                    Latitude
                </label>
                <input type="number" name="latitude" step="0.000001" value="{{ old('latitude') }}" class="input">
            </div>

            <div>
                <label class="label">
                    Longitude
                </label>
                <input type="number" name="longitude" step="0.000001" value="{{ old('longitude') }}" class="input">
            </div>

            <div>
                <label class="label">
                    Dress Code
                </label>
                <input type="text" name="dress_code" value="{{ old('dress_code') }}" class="input">
            </div>

            <div>
                <label class="label">
                    Description
                </label>
                <textarea name="description" rows="4" class="input">{{ old('description') }}</textarea>
            </div>

            <div>
                <label class="label">
                    Status
                </label>
                <select name="status" class="input" required>
                    <option value="">Pilih status</option>
                    @foreach (\App\Enums\EventStatus::cases() as $status)
                        <option value="{{ $status->value }}" {{ request('status') == $status->value ? 'selected' : '' }}>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label">
                    RSVP Deadline
                </label>
                <input type="datetime-local" name="rsvp_deadline" value="{{ old('rsvp_deadline') }}" class="input">
            </div>

            <div>
                <label class="label">
                    RSVP Enabled
                </label>
                <input type="checkbox" name="rsvp_enabled" value="1" {{ old('rsvp_enabled') ? 'checked' : '' }}>
            </div>

            <div>
                <label class="label">
                    Guestbook Enabled
                </label>
                <input type="checkbox" name="guestbook_enabled" value="1" {{ old('guestbook_enabled') ? 'checked' : '' }}>
            </div>

            <div>
                <label class="label">
                    Gallery Enabled
                </label>
                <input type="checkbox" name="gallery_enabled" value="1" {{ old('gallery_enabled') ? 'checked' : '' }}>
            </div>

            <div>
                <label class="label">
                    Gift Enabled
                </label>
                <input type="checkbox" name="gift_enabled" value="1" {{ old('gift_enabled') ? 'checked' : '' }}>
            </div>

            <div>
                <label class="label">
                    Live Wall Enabled
                </label>
                <input type="checkbox" name="live_wall_enabled" value="1" {{ old('live_wall_enabled') ? 'checked' : '' }}>
            </div>

            <div>
                <label class="label">
                    Timezone
                </label>
                <input type="text" name="timezone" value="{{ old('timezone') }}" class="input" placeholder="Asia/Jakarta">
            </div>

            <div>
                <label class="label">
                    Schedules
                </label>
                <div class="flex flex-col space-y-2">
                    @php
                        $scheduleRows = old('schedules', $schedules ?? []);
                        if (empty($scheduleRows)) {
                            $scheduleRows = [['title' => '', 'description' => '', 'start_time' => '', 'end_time' => '', 'status' => 'planned']];
                        }
                    @endphp
                    @foreach ($scheduleRows as $i => $schedule)
                        <div class="flex flex-wrap items-center gap-2">
                            <input type="text" name="schedules[{{ $i }}][title]" placeholder="Judul" class="input w-64" value="{{ old("schedules.{$i}.title", $schedule['title'] ?? '') }}">
                            <input type="text" name="schedules[{{ $i }}][description]" placeholder="Deskripsi" class="input w-64" value="{{ old("schedules.{$i}.description", $schedule['description'] ?? '') }}">
                            <input type="datetime-local" name="schedules[{{ $i }}][start_time]" class="input w-48" value="{{ old("schedules.{$i}.start_time", $schedule['start_time'] ?? '') }}">
                            <input type="datetime-local" name="schedules[{{ $i }}][end_time]" class="input w-48" value="{{ old("schedules.{$i}.end_time", $schedule['end_time'] ?? '') }}">
                            <select name="schedules[{{ $i }}][status]" class="input w-48">
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
                <label class="label">
                    Owner Assignment
                </label>
                <div class="flex flex-col space-y-2">
                    <div class="flex items-center gap-2">
                        <input type="text" name="owner_ids[]" placeholder="Cari owner..." class="input w-64">
                        <select name="owner_roles[]" class="input w-48">
                            <option value="">Pilih role</option>
                            <option value="groom">Groom</option>
                            <option value="bride">Bride</option>
                            <option value="partner">Partner</option>
                        </select>
                    </div>
                    @forelse ($owners as $owner)
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="owner_ids[]" value="{{ $owner->id }}" {{ in_array($owner->id, old('owner_ids', [])) ? 'checked' : '' }}>
                            <label class="flex-1">{{ $owner->name }}</label>
                            <select name="owner_roles[{{ $owner->id }}]" class="input w-48">
                                <option value="">Pilih role</option>
                                <option value="groom" {{ old("owner_roles.{$owner->id}", '') == 'groom' ? 'selected' : '' }}>Groom</option>
                                <option value="bride" {{ old("owner_roles.{$owner->id}", '') == 'bride' ? 'selected' : '' }}>Bride</option>
                                <option value="partner" {{ old("owner_roles.{$owner->id}", '') == 'partner' ? 'selected' : '' }}>Partner</option>
                            </select>
                        </div>
                    @empty
                        <div class="flex items-center gap-2">
                            <input type="text" name="owner_ids[]" placeholder="Cari owner..." class="input w-64">
                            <select name="owner_roles[]" class="input w-48">
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