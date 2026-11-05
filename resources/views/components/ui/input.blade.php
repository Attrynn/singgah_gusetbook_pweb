@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'hint' => null, 'required' => false, 'placeholder' => null])

@php
    $id = $attributes->get('id') ?? $name;
@endphp

<div>
    @if ($label)
        <label for="{{ $id }}" class="label">
            {{ $label }}
            @if ($required)
                <span class="text-[#b76a73]">*</span>
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
        {{ $attributes->merge(['class' => 'input']) }}
    >

    @error($name)
        <p class="error-text">{{ $message }}</p>
    @enderror

    @if ($hint)
        <p class="hint">{{ $hint }}</p>
    @endif
</div>
