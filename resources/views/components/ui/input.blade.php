@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'hint' => null, 'required' => false, 'placeholder' => null])

@php
    $id = $attributes->get('id') ?? $name;
    $hasError = $errors->has($name);
    $errorId = $id.'-error';
    $hintId = $id.'-hint';
    $describedBy = trim(($hasError ? $errorId : '').($hint ? ($hasError ? ' ' : '').$hintId : ''));
@endphp

<div>
    @if ($label)
        <label for="{{ $id }}" class="label">
            {{ $label }}
            @if ($required)
                <span class="text-danger" aria-hidden="true">*</span>
                <span class="sr-only">(wajib)</span>
            @endif
        </label>
    @endif

    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ old($name, $value) }}"
        placeholder="{{ $placeholder }}"
        @if ($required) required @endif
        @if ($hasError) aria-invalid="true" @endif
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->merge(['class' => 'input']) }}
    >

    @error($name)
        <p id="{{ $errorId }}" class="error-text">{{ $message }}</p>
    @enderror

    @if ($hint)
        <p id="{{ $hintId }}" class="hint">{{ $hint }}</p>
    @endif
</div>
