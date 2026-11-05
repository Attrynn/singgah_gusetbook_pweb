@props(['name', 'label' => null, 'value' => null, 'rows' => 4, 'hint' => null, 'required' => false, 'placeholder' => null])

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

    <textarea
        id="{{ $id }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        placeholder="{{ $placeholder }}"
        @if ($required) required @endif
        {{ $attributes->merge(['class' => 'input resize-y']) }}
    >{{ old($name, $value) }}</textarea>

    @error($name)
        <p class="error-text">{{ $message }}</p>
    @enderror

    @if ($hint)
        <p class="hint">{{ $hint }}</p>
    @endif
</div>
