@props(['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null, 'required' => false, 'hint' => null])

@php
    $id = $attributes->get('id') ?? $name;
    $selected = old($name, $value);
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

    <select
        id="{{ $id }}"
        name="{{ $name }}"
        @if ($required) required @endif
        {{ $attributes->merge(['class' => 'input appearance-none pr-10']) }}
    >
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach ($options as $optionValue => $optionLabel)
            @php
                $isObject = is_object($optionLabel);
                $val = $isObject ? $optionLabel->value : $optionValue;
                $text = $isObject ? $optionLabel->label() : $optionLabel;
            @endphp
            <option value="{{ $val }}" @selected((string) $selected === (string) $val)>{{ $text }}</option>
        @endforeach
    </select>

    @error($name)
        <p class="error-text">{{ $message }}</p>
    @enderror

    @if ($hint)
        <p class="hint">{{ $hint }}</p>
    @endif
</div>
