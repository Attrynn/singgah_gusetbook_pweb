@extends('layouts.admin')

@section('title', 'Profil Saya')
@section('subtitle', 'Kelola akun dan keamanan')

@section('content')
    <x-ui.page-header title="Profil Saya" eyebrow="Account" subtitle="Perbarui identitas, foto, dan password akun administrator." />

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="space-y-6">
            <form method="POST" action="{{ route('admin.profile.update') }}" enctype="multipart/form-data" class="card space-y-5 p-6" x-data="{ preview: null }">
                @csrf
                @method('PUT')

                <div>
                    <h2 class="section-title">Informasi akun</h2>
                    <p class="mt-1 text-xs text-muted">Nama dan email digunakan untuk login serta jejak audit.</p>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.input name="name" label="Nama lengkap" :value="$user->name" required />

                    <div>
                        <x-ui.input name="email" type="email" label="Email" :value="$user->email" required />
                        @if ($user->pending_email)
                            <p class="hint">Menunggu verifikasi: <span class="font-semibold text-ink">{{ $user->pending_email }}</span> — email login belum berubah.</p>
                        @endif
                    </div>

                    <div class="sm:col-span-2">
                        <x-ui.input name="current_password" type="password" label="Password saat ini"
                            hint="Wajib diisi hanya jika Anda mengubah email login." autocomplete="current-password" />
                    </div>

                    <x-ui.input name="phone" label="Telepon" :value="$user->phone" hint="Opsional, maksimal 30 karakter." />

                    <div>
                        <label for="avatar" class="label">Foto profil</label>
                        <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp"
                            class="input py-2 file:mr-3 file:rounded-xl file:border-0 file:bg-blue-soft file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-ink"
                            x-on:change="const file = $event.target.files[0]; preview = file ? URL.createObjectURL(file) : null" aria-invalid="true" aria-describedby="avatar-error">
                        @error('avatar')
                            <p id="avatar-error" class="error-text">{{ $message }}</p>
                        @enderror
                        <p class="hint">JPG, PNG, atau WebP. Maksimal 2 MB.</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line/70 pt-5">
                    <button type="submit" class="btn btn-primary">
                        <x-ui.icon name="save" class="h-4 w-4" /> Simpan profil
                    </button>
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost">Kembali ke dashboard</a>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.profile.password') }}" class="card space-y-5 p-6">
                @csrf
                @method('PUT')

                <div>
                    <h2 class="section-title">Ganti password</h2>
                    <p class="mt-1 text-xs text-muted">Gunakan minimal 8 karakter dan kombinasikan huruf serta angka.</p>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-ui.input name="current_password" type="password" label="Password saat ini" required autocomplete="current-password" />
                    </div>
                    <x-ui.input name="password" type="password" label="Password baru" required autocomplete="new-password" />
                    <x-ui.input name="password_confirmation" type="password" label="Konfirmasi password baru" required autocomplete="new-password" />
                </div>

                <div class="border-t border-line/70 pt-5">
                    <button type="submit" class="btn btn-secondary">
                        <x-ui.icon name="lock" class="h-4 w-4" /> Perbarui password
                    </button>
                </div>
            </form>
        </div>

        <aside class="space-y-4">
            <div class="card p-6 text-center">
                <div class="mx-auto flex h-24 w-24 items-center justify-center overflow-hidden rounded-3xl border border-line bg-surface-2">
                    <template x-if="preview">
                        <img :src="preview" alt="Pratinjau foto" class="h-full w-full object-cover">
                    </template>
                    <template x-if="! preview">
                        @if ($user->avatar_path)
                            <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($user->avatar_path) }}" alt="{{ $user->name }}" class="h-full w-full object-cover">
                        @else
                            <x-ui.avatar :name="$user->name" size="xl" />
                        @endif
                    </template>
                </div>
                <p class="mt-4 text-sm font-semibold text-ink">{{ $user->name }}</p>
                <p class="text-xs text-muted">{{ $user->email }}</p>
                <div class="mt-3 flex justify-center">
                    <x-ui.badge :variant="$user->role?->value === 'superadmin' ? 'success' : 'info'">{{ $user->role?->label() ?? 'User' }}</x-ui.badge>
                </div>
            </div>

            @if ($user->pending_email)
                <div class="card p-5">
                    <h2 class="section-title">Verifikasi email tertunda</h2>
                    <p class="mt-2 text-xs leading-relaxed text-muted">
                        Tautan verifikasi dikirim ke <span class="font-semibold text-ink">{{ $user->pending_email }}</span>.
                        Email login tetap <span class="font-semibold text-ink">{{ $user->email }}</span> sampai Anda mengonfirmasi melalui tautan tersebut.
                    </p>
                    <form method="POST" action="{{ route('admin.profile.email.resend') }}" class="mt-4 space-y-3">
                        @csrf
                        <x-ui.input name="current_password" id="resend_current_password" type="password" label="Password saat ini" required autocomplete="current-password" />
                        <button type="submit" class="btn btn-secondary w-full">
                            <x-ui.icon name="mail" class="h-4 w-4" /> Kirim ulang tautan verifikasi
                        </button>
                    </form>
                </div>
            @endif

            <div class="card p-5">
                <h2 class="section-title">Detail akun</h2>
                <dl class="mt-3 space-y-2.5 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">Status</dt>
                        <dd><x-ui.badge :variant="$user->is_active ? 'success' : 'muted'">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</x-ui.badge></dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">Telepon</dt>
                        <dd class="text-ink">{{ $user->phone ?: '—' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">Login terakhir</dt>
                        <dd class="text-ink">{{ $user->last_login_at?->translatedFormat('d M Y H:i') ?? '—' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">Bergabung</dt>
                        <dd class="text-ink">{{ $user->created_at?->translatedFormat('d M Y') }}</dd>
                    </div>
                </dl>
            </div>

            <div class="card p-5">
                <h2 class="section-title">Keamanan</h2>
                <p class="mt-2 text-xs leading-relaxed text-muted">
                    Semua perubahan profil dan password tercatat di audit log. Jika Anda mencurigai aktivitas mencurigakan,
                    segera ganti password dan periksa halaman audit log.
                </p>
                <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-ghost btn-sm mt-3 w-full">
                    <x-ui.icon name="shield" class="h-3.5 w-3.5" /> Buka audit log
                </a>
            </div>
        </aside>
    </div>
@endsection
