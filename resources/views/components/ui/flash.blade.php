@php
    $flashType = session('status') ? 'success' : ($errors->any() ? 'error' : null);
    $flashMessage = session('status') ?? ($errors->any() ? $errors->first() : null);
@endphp

@if ($flashMessage)
    <div id="flash-data" data-type="{{ $flashType }}" data-message="{{ $flashMessage }}" class="hidden"></div>
@endif

@if ($errors->any())
    <div class="mb-5 rounded-2xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger" data-auto-dismiss="9000" role="alert">
        <div class="flex items-start gap-3">
            <x-ui.icon name="alert" class="mt-0.5 h-4 w-4 shrink-0" />
            <div>
                <p class="font-semibold">Periksa kembali data Anda.</p>
                <ul class="mt-1 list-inside list-disc space-y-0.5 text-xs">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endif
